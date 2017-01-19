<?php
/*==============================================================================

	MyMediaGrab

	mmg\SourceStatus class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Fs;
use mm\Log;


//------------------------------------------------------------------------------
//! Status of the source (generic class)
//------------------------------------------------------------------------------
class SourceStatus
{
	const GRABOUTCOME_OK = 'ok';
	const GRABOUTCOME_FAIL = 'fail';

	//! [string] Name of the file where the Job status is saved
	protected $fname = NULL;

	//! [\DateTime] Date and time when the source was last "seen" and examined
	public $lastSeenDt = NULL;

	//! [string] Outcome of the last grab attempt. Either 'ok' or 'fail'.
	public $lastGrabOutcome = NULL;

	//! [\DateTime] Date and time when the source was succesfully examined
	public $lastSuccesfulGrabDt = NULL;

	//! [array(path => SubsourceStatus)] Status of the subsource
	public $subsourceStatuses = array();

    //--------------------------------------------------------------------------
    //! Factory method: load existing object from file
    /*! If the file does not exist create a new status object. */
    //--------------------------------------------------------------------------
	public static function load($name, $class)
	{
		$sStatus = NULL;

		$fname = Fs::joinPath(Main::$sourceStatusDir, $name.".status");
		if (file_exists($fname)) {
			$s = file_get_contents($fname);
			$sStatus = unserialize($s);
			if ($sStatus === FALSE) {
				Log::submit(LOG_WARNING, "Invalid source status file '$fname', discarding");
				$sStatus = NULL;
			}
			elseif (!$sStatus instanceof $class) {
				Log::submit(LOG_WARNING, "Invalid source status file '$fname', discarding");
				$sStatus = NULL;
			}
		}

		if (!$sStatus) {
			Log::submit(LOG_DEBUG, "Creating new status file '$fname'");
			$sStatus = new $class($fname);
		}

		$sStatus->fname = $fname;
		return $sStatus;
	}

    //--------------------------------------------------------------------------
    //! Constructor
    //--------------------------------------------------------------------------
    public function __construct($fname)
    {
		$this->fname = $fname;
    }

    //--------------------------------------------------------------------------
    //! Magic method, called before serialization
    //--------------------------------------------------------------------------
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('fname'));
    }

	//--------------------------------------------------------------------------
	//! Save this object to file
	//--------------------------------------------------------------------------
	public function save()
	{
		file_put_contents($this->fname, serialize($this));
	}
};
