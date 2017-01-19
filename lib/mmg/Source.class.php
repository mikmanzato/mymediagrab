<?php
/*==============================================================================

	MyMediaGrab

	mmg\Source class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Email;
use mm\Filesystem;
use mm\FilesystemException;
use mm\Fs;
use mm\Log;
use mm\SmartyBuilder;
use mm\System;


//------------------------------------------------------------------------------
//! Source (generic class)
//------------------------------------------------------------------------------
abstract class Source
{
	//! [string] Name of the source, comes from the filename
	protected $name;

	//! [string] Descriptive label
	protected $label;

	//! [mm\Filesystem] The filesystem location where media files are found
	protected $location;

	//! [string] Name of the destination for files grabbed from this source
	protected $destination;

	//! [string] Username of the user who will own the files (optional)
	protected $owner;

	//! [array(Subsource)] Configuration of subsources in this source
	protected $subsources = array();

	//! [bool] Delete files from the source once they have been copied to the destination
	protected $delete = FALSE;

	//! [bool] Age of files to delete, in days
	protected $deleteAge = NULL;

	//! [string] List of email addresses which are notified of events on this source
	protected $notificationsRecipients;

	//! [string] Code of language for notifications from this source
	protected $notificationsLanguage;

	//! [SourceStatus] The source status object
	protected $sStatus;

	//! Statistics on the last grab operation
	protected $numScanned = 0;
	protected $numAlreadyProcessed = 0;
	protected $numDeleted = 0;
	protected $numProcessed = 0;
	protected $numStored = 0;
	protected $numParked = 0;
	protected $numErrors = 0;

	//--------------------------------------------------------------------------
	// Instantiate source and load configuration
	//--------------------------------------------------------------------------
	public static function load($confFname)
	{
		$conf = new Conf($confFname);
		$name = basename($confFname, '.conf');
		$class = $conf->getRequired('source', 'type');
		$source = new $class($name, $conf);
		if (!$source instanceof self)
			throw new \Exception("Bad source: $class");

		return $source;
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct($name, Conf $conf)
	{
		$this->name = $name;
		$this->enabled = (bool) $conf->get('source', 'enabled', TRUE);
		$this->label = $conf->getRequired('source', 'label');
		$loc = $conf->getRequired('source', 'location');
		$this->location = Filesystem::build($loc);
		$destinationName = $conf->getRequired('source', 'destination');
		$this->destination = Destinations::getDestinationByName($destinationName);
		$this->owner = $conf->get('source', 'owner');
		$this->delete = (bool) $conf->get('source', 'delete', FALSE);
		$this->deleteAge = (int) $conf->get('source', 'delete_age', 60);
		$this->notificationsRecipients = $conf->get('notifications', 'recipients');
		$this->notificationsLanguage = $conf->get('notifications', 'language', 'en');
		$this->sStatus = $this->mkSourceStatus($name);

		$ss = $conf->get('source', 'subsources');
		if ($ss) {
			$ss = explode(",", $ss);

			foreach ($ss as $s) {
				$s = trim($s);
				$v = explode(':', $s);
				if (empty($v))
					throw new \Exception("Bad subsource configuration: $s");

				if (isset($v[1]))
					$this->subsources[] = new Subsource($this, trim($v[0]), trim($v[1]));
				else
					$this->subsources[] = new Subsource($this, trim($v[0]));
			}
		}

		if (empty($this->subsources))
			$this->subsources[] = new Subsource($this);
	}

	//--------------------------------------------------------------------------
	//! Destructor. Save the source status.
	//--------------------------------------------------------------------------
	public function __destruct()
	{
		$this->sStatus->save();
	}

	//--------------------------------------------------------------------------
	//! Instantiate a new source status file
	//--------------------------------------------------------------------------
	protected function mkSourceStatus($name)
	{
		return SourceStatus::load($name, 'mmg\SourceStatus');
	}

	//--------------------------------------------------------------------------
	//! Return the source name
	//--------------------------------------------------------------------------
	public function getName()
	{
		return $this->name;
	}

	//--------------------------------------------------------------------------
	//! Return the configured owner
	//--------------------------------------------------------------------------
	public function getOwner()
	{
		return $this->owner;
	}

	//--------------------------------------------------------------------------
	//! Return the value of the `delete` config option
	//--------------------------------------------------------------------------
	public function getDelete()
	{
		return $this->delete;
	}

	//--------------------------------------------------------------------------
	//! Return the value of the `delete_age` config option
	//--------------------------------------------------------------------------
	public function getDeleteAge()
	{
		return $this->deleteAge;
	}

	//--------------------------------------------------------------------------
	//! Tell whether to prune empty subdirectories
	//--------------------------------------------------------------------------
	public function pruneEmptyDirs()
	{
		return $this->delete;
	}

	//--------------------------------------------------------------------------
	//! Returns the location
	//--------------------------------------------------------------------------
	public function getLocation()
	{
		return $this->location;
	}

	//--------------------------------------------------------------------------
	//! Returns the source status object
	//--------------------------------------------------------------------------
	public function getStatus()
	{
		return $this->sStatus;
	}

	//--------------------------------------------------------------------------
	//! Get the status of the subsource
	//--------------------------------------------------------------------------
	public function getSubsourceStatus(Subsource $subsource)
	{
		$name = $this->name."-".md5($subsource->path);
		return SubsourceStatus::load($name);
	}

	//--------------------------------------------------------------------------
	//! Returns the related destination
	//--------------------------------------------------------------------------
	public function getDestination()
	{
		return $this->destination;
	}

	//--------------------------------------------------------------------------
	//! Get or calculate date/time of next grab attempt
	//--------------------------------------------------------------------------
	abstract protected function getNextGrabAttemptDt();

	//--------------------------------------------------------------------------
	//! Tells whether the source is available
	//--------------------------------------------------------------------------
	public function isAvailable()
	{
		Log::submit(LOG_DEBUG, "Testing if source is available");
		return $this->location->isAvailable();
	}

	//--------------------------------------------------------------------------
	//! Tells whether the source can (and should) be processed
	//--------------------------------------------------------------------------
	public function canProcess()
	{
		$now = new \DateTime();
		$nextGrabAttemptDt = $this->getNextGrabAttemptDt();
		if ($nextGrabAttemptDt && ($nextGrabAttemptDt > $now)) {
			Log::submit(LOG_DEBUG, "Source will be tried after ".$nextGrabAttemptDt->format("Y-m-d H:i:s"));
			return FALSE;
		}

		if (!$this->isAvailable()) {
			Log::submit(LOG_DEBUG, "Source not available, skipping");
			return FALSE;
		}

		Log::submit(LOG_DEBUG, "Source is available");
		return TRUE;
	}

	//--------------------------------------------------------------------------
	//! Send a notification email to the configured recipients
	//--------------------------------------------------------------------------
	protected function sendEmail($templateName, array $vars)
	{
		//~ echo __method__."(): Sending email\n";

		$smarty = SmartyBuilder::getSmarty();
		$smarty->assign('fqdn', System::getFqdn());
		$smarty->assign('source', $this->label);
		$smarty->assign($vars);
		$language = $this->notificationsLanguage;
		$templatePath = Fs::joinPath("email/{$language}", $templateName);
		$htmlBody = $smarty->fetch($templatePath);

		$email = Email::buildHtml($htmlBody);
		$email->setRecipients($this->notificationsRecipients);

		try {
			Mailer::send($email);
			Log::submit(LOG_DEBUG, "Notification email sent.");
			return TRUE;
		}
		catch (Exception $e) {
			Log::submit(LOG_WARNING, "Failed to send notification email.");
			return FALSE;
		}
	}

	//--------------------------------------------------------------------------
	//! Grab files from this source and deliver to the configured destination
	//--------------------------------------------------------------------------
	public function grabAndDeliver()
	{
		Log::submit(LOG_DEBUG, sprintf("Source: %s", $this->getName()));
		if (!$this->enabled) {
			Log::submit(LOG_DEBUG, "Source is disabled.");
			return;
		}

		if (!$this->canProcess()) {
			Log::submit(LOG_DEBUG, "Not processing source.");
			return;
		}

		Log::submit(LOG_DEBUG, "Processing source.");

		$now = new \DateTime("now");
		$this->sStatus->lastSeenDt = $now;
		$this->sStatus->lastGrabOutcome = NULL;
		$this->sStatus->save();

		try {
			$interrupted = FALSE;

			try {
				foreach (array_keys($this->subsources) as $k) {
					$subsource = $this->subsources[$k];
					$subsource->grabAndDeliver();

					$this->numScanned += $subsource->numScanned;
					$this->numAlreadyProcessed += $subsource->numAlreadyProcessed;
					$this->numDeleted += $subsource->numDeleted;
					$this->numProcessed += $subsource->numProcessed;
					$this->numStored += $subsource->numStored;
					$this->numParked += $subsource->numParked;
					$this->numErrors += $subsource->numErrors;
				}

				// Save date/time of succesful grab & plan next attempt
				$this->sStatus->lastSuccesfulGrabDt = $now;
				$this->sStatus->lastGrabOutcome = SourceStatus::GRABOUTCOME_OK;
				$this->sStatus->save();
			}
			catch (FilesystemException $e) {
				Log::submit(LOG_ERR, "Filesystem error during transfer, aborting: ".$e->getMessage());
				$this->sStatus->lastGrabOutcome = SourceStatus::GRABOUTCOME_FAIL;
				$this->sStatus->save();
				$interrupted = TRUE;
			}

			// Stats
			Log::submit(LOG_DEBUG, "{$this->numScanned} files scanned");
			Log::submit(LOG_DEBUG, "{$this->numAlreadyProcessed} files already processed");
			Log::submit(LOG_DEBUG, "{$this->numDeleted} files deleted");
			Log::submit(LOG_DEBUG, "{$this->numProcessed} files processed");
			Log::submit(LOG_DEBUG, "{$this->numStored} files stored to destination");
			Log::submit(LOG_DEBUG, "{$this->numParked} files parked");
			Log::submit(LOG_DEBUG, "{$this->numErrors} errors");

			if ($this->numStored > 0) {

				// Send grab notification
				$vars = array(
						'numScanned' => $this->numScanned,
						'numProcessed' => $this->numProcessed,
						'numDeleted' => $this->numDeleted,
						'numStored' => $this->numStored,
						'numParked' => $this->numParked,
						'numErrors' => $this->numErrors,
						'interrupted' => $interrupted,
					);

				$this->sendEmail("grab-notification.html", $vars);
				Log::submit(LOG_DEBUG, "Grab notification sent");
			}
			else
				Log::submit(LOG_DEBUG, "No new files, grab notification not sent");
		}
		catch (Exception $e) {
			Log::submit(LOG_ERR, "Error during grab: ".$e->getMessage());
			$this->sStatus->save();
		}
	}
};
