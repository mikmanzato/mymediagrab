<?php
/*==============================================================================

	MyMediaGrab

	mmg\SubsourceStatus class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Fs;
use mm\Log;


//------------------------------------------------------------------------------
//! Status of the subsource
//------------------------------------------------------------------------------
class SubsourceStatus
{
	//! [\DateTime] Date and time of the last imported file
	public $lastFileDt = NULL;

	//! [array(path => fsize)] Map of the processed files
	public $processedFiles = array();

	//! [string] Name of the file where the Job status is saved
	protected $fname = NULL;

    //--------------------------------------------------------------------------
    //! Factory method: load existing object from file
    /*! If the file does not exist create a new object. */
    //--------------------------------------------------------------------------
	public static function load($name)
	{
		$ssStatus = NULL;

		$fname = Fs::joinPath(Main::$sourceStatusDir, $name.".status");
		if (file_exists($fname)) {
			$s = file_get_contents($fname);
			$ssStatus = unserialize($s);
			if ($ssStatus === FALSE) {
				Log::submit(LOG_WARNING, "Invalid subsource status file '$fname', discarding");
				$ssStatus = NULL;
			}
			elseif (!$ssStatus instanceof self) {
				Log::submit(LOG_WARNING, "Invalid subsource status file '$fname', discarding");
				$ssStatus = NULL;
			}
		}

		if (!$ssStatus) {
			Log::submit(LOG_DEBUG, "Creating new subsource status file '$fname'");
			$ssStatus = new self($fname);
		}

		$ssStatus->fname = $fname;
		return $ssStatus;
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

	//--------------------------------------------------------------------------
	//! Add file to the list of processed files
	/*! \param $sf The SourceFile to add */
	//--------------------------------------------------------------------------
	public function addProcessed(SourceFile $sf)
	{
		$fname = $sf->getFName();
		//~ echo __method__."(): fname=$fname\n";
		$this->processedFiles[$fname] = new ProcessedFile($sf);
		$this->save();
	}

	//--------------------------------------------------------------------------
	//! Clear a file from the list of processed files
	/*! \param $sf The SourceFile to clear */
	//--------------------------------------------------------------------------
	public function clearProcessed(SourceFile $sf)
	{
		$fname = $sf->getFName();
		//~ echo __method__."(): fname=$fname\n";
		unset($this->processedFiles[$fname]);
		$this->save();
	}

	//--------------------------------------------------------------------------
	//! Get file from processed file
	/*! \param $sf The SourceFile to add */
	//--------------------------------------------------------------------------
	public function getProcessed(SourceFile $sf)
	{
		$fname = $sf->getFName();
		if (isset($this->processedFiles[$fname])) {
			$p = $this->processedFiles[$fname];
			if (!$p instanceof ProcessedFile) {
				$p = $this->processedFiles[$fname] = new ProcessedFile($sf);
				$this->save();
			}
			return $p;
		}
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Return TRUE if the file has been already processed
	/*! \param $sf The SourceFile to test */
	//--------------------------------------------------------------------------
	public function alreadyProcessed(SourceFile $sf)
	{
		//~ echo __method__."(): fname=$fname\n";
		$p = $this->getProcessed($sf);
		if (!$p)
			return FALSE;

		// Size not checked. It has been found that files change from time
		// to time on the device so the timestamp of the ProcessedFile $p is
		// not the same as the SourceFile $sf.
//		// Check size
//		$size = $p->size;
//		if ($size != $sf->getSize()) {
//			//~ echo __method__."(): different size: $size != $sfSize\n";
//			return FALSE;
//		}

		//~ echo __method__."(): Already processed\n";
		return TRUE;
	}
};


