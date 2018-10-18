<?php
/*==============================================================================

	MyMediaGrab

	mmg\Source class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Shell;
use mm\ShellException;
use mm\Log;
use mmg\Mailer;


//------------------------------------------------------------------------------
//! Source located on a mobile phone
/*! A mobile phone is typically connected to the LAN by WiFi */
//------------------------------------------------------------------------------
class PhoneSource
	extends Source
{
	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct($name, Conf $conf)
	{
		parent::__construct($name, $conf);
		if ($this->destination instanceof mm\FtpFilesystem)
			throw new \Exception("Invalid phone source configuration: location must be a FTP source");
	}

	//--------------------------------------------------------------------------
	//! Provide the source status file
	//--------------------------------------------------------------------------
	protected function mkSourceStatus($name)
	{
		return SourceStatus::load($name, 'mmg\PhoneSourceStatus');
	}

	//--------------------------------------------------------------------------
	//! Tells whether the source is available
	/*! If the source is not available send an email notification to the user. */
	//--------------------------------------------------------------------------
	public function isAvailable()
	{
		$a = parent::isAvailable();
		if ($a)
			return TRUE;

		// Check if the device is present in the LAN
		$hostname = $this->location->getHostname();
		Log::submit(LOG_DEBUG, "Testing if device is in reach");
		$cmd = sprintf("/bin/ping %s -c 1 2> /dev/null", escapeshellarg($hostname));
		$inTheLan = FALSE;
		try {
			$result = Shell::exec($cmd, $output, $exitValue);
			Log::submit(LOG_DEBUG, "Device is in the LAN");
			$inTheLan = TRUE;
		}
		catch (ShellException $e) {
			Log::submit(LOG_DEBUG, "Device is not in the LAN");
			return FALSE;
		}

		if ($inTheLan) {
			$dt = (new \DateTime())->sub(new \DateInterval("P7D")); 	// 7 days ago
			if (!$this->sStatus->lastConnectionRequestDt
			    || ($this->sStatus->lastConnectionRequestDt < $dt)) {

				$vars = array(
						'source' => $this->label,
					);

				if ($this->sendEmail("ftp-server-off.html", $vars)) {
					$this->sStatus->lastConnectionRequestDt = new \DateTime();
					$this->sStatus->save();
				}
			}
		}

		return FALSE;
	}

	//--------------------------------------------------------------------------
	//! Get or calculate date/time of next grab attempt
	//--------------------------------------------------------------------------
	protected function getNextGrabAttemptDt()
	{
		if (!$this->sStatus->lastSeenDt)
			return NULL;
		else {
			switch ($this->sStatus->lastGrabOutcome) {
				case SourceStatus::GRABOUTCOME_OK:
					// In case of success retry at least 6 hours later
					$dt = clone $this->sStatus->lastSeenDt;
					return $dt->add(new \DateInterval("PT6H"));
				case SourceStatus::GRABOUTCOME_FAIL:
					// In case of error retry at least 2 hours later
					$dt = clone $this->sStatus->lastSeenDt;
					return $dt->add(new \DateInterval("PT2H"));
				default:
					// Unknown, retry immediately
					return NULL;
			}
		}
	}
};
