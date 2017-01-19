<?php
/*==============================================================================

	MyMediaGrab

	mmg\Main class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Console;
use mm\ConsoleLogListener;
use mm\Email;
use mm\EmailLogListener;
use mm\FileLogListener;
use mm\Fs;
use mm\Log;
use mm\MainConf;
use mm\SmartyBuilder;
use mm\System;


//------------------------------------------------------------------------------
//! Main program class
//------------------------------------------------------------------------------
abstract class Main
{
	public static $confDir = NULL;
	public static $statusDir = NULL;
	public static $runDir = NULL;
	public static $sourceStatusDir = NULL;
	public static $lockFile = NULL;
	public static $force = NULL;
	public static $cmd = NULL;

	protected static $notificationsLanguage;
	protected static $notificationsManagers;

	protected static $consoleLogLevel = 6;
	protected static $verbose = FALSE;
	protected static $disableReport = FALSE;

	//--------------------------------------------------------------------------
	//! Initialize global parameters
	//--------------------------------------------------------------------------
	protected static function init()
	{
		global $argv;

		// Lock file
		self::$lockFile = "/var/lock/mymediagrab.lock";

		// Status directory
		self::$statusDir = "/var/lib/mymediagrab";
		if (!is_dir(self::$statusDir))
			Fs::mkdir(self::$statusDir);

		// Source status directory
		self::$sourceStatusDir = Fs::joinPath(self::$statusDir, "sources");
		if (!is_dir(self::$sourceStatusDir))
			Fs::mkdir(self::$sourceStatusDir);

		// Run directory
		self::$runDir = "/var/run/mymediagrab";
		if (!is_dir(self::$runDir))
			Fs::mkdir(self::$runDir);

		// Smarty configuration
		SmartyBuilder::$templateDir = dirname(dirname(dirname(__FILE__))).'/templates';
		SmartyBuilder::$compileDir = Fs::joinPath(self::$runDir, 'templates_c');

		// Parse command-line parameters
		array_shift($argv);	// skip 0
		while (!empty($argv)) {
			$arg = array_shift($argv);
			switch ($arg) {
				case '-c':
				case '--condfdir':
					self::$confDir = array_shift($argv);
					if (!self::$confDir)
						throw new \Exception("Missing parameter to -c/--confdir option");
					break;

				case '-d':
				case '--disable-report':
					self::$disableReport = TRUE;
					break;

				case '-f':
				case '--force':
					self::$force = TRUE;
					break;

				case '-l':
				case '--loglevel':
					$logLevel = (int) array_shift($argv);
					if (($logLevel < LOG_EMERG) || ($logLevel > LOG_DEBUG))
						throw new \Exception("Invalid log level: $logLevel");

					self::$consoleLogLevel = $logLevel;
					self::$verbose = TRUE;
					break;

				case '-v':
				case '--verbose':
					self::$verbose = TRUE;
					break;

				case 'grab':
					self::$cmd = 'grab';
					break;

				case 'status':
					self::$cmd = 'status';
					break;

				default:
					throw new \Exception("Unexpected command-line argument: $arg");
			}
		}

		// Default command is 'grab'
		if (!self::$cmd)
			self::$cmd = 'grab';

		if (!self::$confDir) {
			// Set the configuration directory
			if (dirname(__FILE__) == '/usr/share/mymediagrab/lib/mmg')
				self::$confDir = "/etc/mymediagrab";
			elseif (dirname(__FILE__) == '/usr/local/share/mymediagrab/lib/mmg')
				self::$confDir = "/usr/local/etc/mymediagrab";
			else
				throw new \Exception("Can't locate configuration directory, use -c/--confdir parameter");
		}

		// Initialize console logging
		if (self::$verbose) {
			$listener = new ConsoleLogListener();
			$listener->setMinLogLevel(self::$consoleLogLevel);
			Log::attachListener($listener);
		}

		// Read main configuration
		$mainConfFile = Fs::joinPath(self::$confDir, "main.conf");
		$mainConf = MainConf::load($mainConfFile);

		// Initialize file logging
		$listener = new FileLogListener();
		if (!is_null($s = $mainConf->get("log", "file")))
			$listener->setLogFile($s);
		else
			$listener->setLogFile("/var/log/mymediagrab/mymediagrab.log");
		if (!is_null($s = $mainConf->get("log", "min_level")))
			$listener->setMinLogLevel($s);
		else
			$listener->setMinLogLevel(LOG_INFO);
		Log::attachListener($listener);

		// Initialize common temporary directory
		if (!is_null($s = $mainConf->get("common", "temp_dir")))
			Fs::$tempDir = $s;

		//! Notifications
		self::$notificationsLanguage = $mainConf->get('notifications', 'language', 'en');
		self::$notificationsManagers = $mainConf->get('notifications', 'managers');

		// Load all destinations
		$dir = Fs::joinPath(self::$confDir, "destinations.d");
		Destinations::load($dir);

		// Load all sources
		$dir = Fs::joinPath(self::$confDir, "sources.d");
		Sources::load($dir);
	}

	//--------------------------------------------------------------------------
	//! Run all grab actions
	//--------------------------------------------------------------------------
	protected static function wrappedGrab()
	{
		$sources = Sources::get();

		if (empty($sources)) {
			Log::submit(LOG_DEBUG, "No sources configured, terminating.");
			return;
		}

		foreach (array_keys($sources) as $k) {
			$source = $sources[$k];
			$source->grabAndDeliver();
		}
	}

	//--------------------------------------------------------------------------
	//! Send a notification email to the managers
	//--------------------------------------------------------------------------
	protected static function sendEmail($templateName, array $vars)
	{
		$smarty = SmartyBuilder::getSmarty();
		$smarty->assign('fqdn', System::getFqdn());
		$smarty->assign($vars);
		$language = self::$notificationsLanguage;
		$templatePath = Fs::joinPath("email/{$language}", $templateName);
		$htmlBody = $smarty->fetch($templatePath);

		$email = Email::buildHtml($htmlBody);
		$email->setRecipients(self::$notificationsManagers);

		try {
			Mailer::send($email);
			Log::submit(LOG_DEBUG, "Report email sent.");
		}
		catch (Exception $e) {
			Log::submit(LOG_WARNING, "Failed to send report email.");
		}
	}

	//--------------------------------------------------------------------------
	//! Run the grab action, using a lock to guarantee unique execution
	//--------------------------------------------------------------------------
	protected static function grab()
	{
		$mainConf = MainConf::getGlobal();

		// Initialize email logging
		$emailLogListener = new EmailLogListener();
//		$emailLogListener->setMinLogLevel(LOG_DEBUG);
		Log::attachListener($emailLogListener);

		// Use lock to guarantee unique execution
		Log::submit(LOG_DEBUG, "***** Start *****");
		$fp = Fs::fopen(self::$lockFile, "w");
		if (flock($fp, LOCK_EX | LOCK_NB)) {
			self::wrappedGrab();

			// Release and remove lock
			fclose($fp);
			@unlink(self::$lockFile);
		}
		else {
			Log::submit(LOG_DEBUG, "Already running, skipping.");
			fclose($fp);
		}

		Log::submit(LOG_DEBUG, "***** End *****");

		// Send notifications by email
		if (self::$disableReport) {
			Log::submit(LOG_DEBUG, "Report email disabled.");
		}
		else {
			$messages = $emailLogListener->getMessages();
			if (!empty($messages)) {
				$vars = array(
						'report' => implode("\n", $messages),
					);
				self::sendEmail('report.html', $vars);
			}
		}
	}

	//--------------------------------------------------------------------------
	//! Main program
	//--------------------------------------------------------------------------
	public static function run()
	{
		try {
			self::init();
		}
		catch (\Exception $e) {
			Console::displayError($e->getMessage());
			exit(1);
		}

		try {
			switch (self::$cmd) {
				case 'grab':
					self::grab();
					break;

				case 'status':
					self::status();
					break;
			}
		}
		catch (\Exception $e) {
			Console::displayError($e);
			Log::submit(LOG_ERR, $e->getMessage());
			exit(1);
		}
	}
};

