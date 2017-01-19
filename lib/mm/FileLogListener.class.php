<?php
/*==============================================================================

	MM library

	mm\FileLogListener class implementation.

	$Id$

==============================================================================*/

namespace mm;


//------------------------------------------------------------------------------
//! Log listener which stores logs to file
//------------------------------------------------------------------------------
class FileLogListener
	extends LogListener
{
	//! [string] Name of the logfile
	private $logFileName = NULL;

	//! [resource] Logfile handle
	private $fp = NULL;

	//------------------------------------------------------------------------------
	//! Set the logfile to use
	//------------------------------------------------------------------------------
	public function setLogFile($fileName)
	{
		$this->logFileName = $fileName;
	}

	//------------------------------------------------------------------------------
	//! Open the logfile for appending
	//------------------------------------------------------------------------------
	private function openLogFile()
	{
		if (!$this->logFileName)
			return FALSE;

		if (!is_resource($this->fp)) {
			$dir = dirname($this->logFileName);
			@mkdir($dir, 0777, TRUE);
			$this->fp = @fopen($this->logFileName, "a");
		}

		return is_resource($this->fp);
	}

	//------------------------------------------------------------------------------
	//! Log a new message
	/*! \param $level [int] The log level. One among log levels defined for
			the syslog() PHP function.
		\param $ts [string] The message timestamp
		\param $session [string] The run session ID
		\param $msg [string] The message to log.
		\param $context [string] Context where the log message has been produced. */
	//------------------------------------------------------------------------------
	public function submit($level, $ts, $session, $msg, $context = "")
	{
		if ($level > $this->minLogLevel)
			return;

		if (!$this->openLogFile())
			return;

		if ($context)
			$msg = "$context: $msg";
		if (is_resource($this->fp)) {
			fprintf($this->fp,
					"%s [%s] %04d: %s\n",
					$ts,
					Log::getLevelStr($level),
					$session,
					$msg);
			fflush($this->fp);
		}
	}
};
