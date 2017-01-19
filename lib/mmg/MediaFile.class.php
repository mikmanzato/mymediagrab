<?php
/*==============================================================================

	MyMediaGrab

	mmg\MediaFile class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Fs;
use mm\Log;


class MediaFileException extends \Exception {};


//------------------------------------------------------------------------------
//! MediaFile (generic class)
//------------------------------------------------------------------------------
abstract class MediaFile
{
	//! [bool] Whether the file is temporary
	/*! Temporary files will be deleted when the objecy is destroyed. */
	protected $temp = FALSE;

	//! [string] Full path to file
	protected $fname = FALSE;

	//--------------------------------------------------------------------------
	//! Build a concrete Filesystem class starting from the location specification
	//--------------------------------------------------------------------------
	public static function build($fname, $temp = FALSE/*, $mtime = FALSE*/)
	{
		if (!is_file($fname))
			return new MediaFileException("File not found: $fname");

		$mf = ImageMediaFile::build($fname, $temp);
		if ($mf)
			return $mf;

		$fs = VideoMediaFile::build($fname, $temp);
		if ($fs)
			return $fs;

		return UnknownMediaFile::build($fname, $temp);
	}

	//--------------------------------------------------------------------------
	//! Constructor
	/*! \param $fname [string] Name of file
		\param $temp [bool] Whether the file is temporary. If TRUE the file is
			deleted when this object is destructed. */
	//--------------------------------------------------------------------------
	public function __construct($fname, $temp = FALSE)
	{
		$this->fname = $fname;
		$this->temp = $temp;
	}

	//--------------------------------------------------------------------------
	//! Destructor
	//--------------------------------------------------------------------------
	public function __destruct()
	{
		if ($this->temp)
			Fs::unlink($this->fname);
	}

	//--------------------------------------------------------------------------
	//! Return the base name of the media file
	//--------------------------------------------------------------------------
	public function getBasename()
	{
		return basename($this->fname);
	}

	//--------------------------------------------------------------------------
	//! Returns the datetime of the media file
	/*! Generic method, recognize a number of date/time patterns */
	//--------------------------------------------------------------------------
	public function getDateTime()
	{
		$name = basename($this->fname);
		if (preg_match('/^([0-9]{4})[_.-]?([0-9]{2})[_.-]?([0-9]{2})([_-])?([0-9]{2})[.-]?([0-9]{2})[.-]?([0-9]{2})(.*)\\.[a-z]+/i', $name, $regs)) {
			Log::submit(LOG_DEBUG, "Getting date and time from formatted filename");
			$dt = new \Datetime();
			$dt->setDate((int) $regs[1], (int) $regs[2], (int) $regs[3]);
			$dt->setTime((int) $regs[5], (int) $regs[6], (int) $regs[7]);
			return $dt;
		}
		elseif (preg_match('/^([0-9]{4})[_.-]?([0-9]{2})[_.-]?([0-9]{2})[^0-9]?/i', $name, $regs)) {
			Log::submit(LOG_DEBUG, "Getting date from formatted filename, time set to 12:00");
			$dt = new \Datetime();
			$dt->setDate((int) $regs[1], (int) $regs[2], (int) $regs[3]);
			$dt->setTime(12, 0, 0);
			return $dt;
		}
		else {
			Log::submit(LOG_DEBUG, "Can't find unequivocal file date/time");
			return NULL;

			// TODO: Manage "reliable datetime" (from SourceFile relationship)
			$t = filemtime($this->fname);
			$dt = new \Datetime();
			$dt->setTimestamp($t);
			return $dt;
		}
	}

	//--------------------------------------------------------------------------
	//! Tells whether this file is the same file as another
	//--------------------------------------------------------------------------
	public function isSameAs($fname)
	{
		if (filesize($this->fname) != filesize($fname))
			return FALSE;

		if (md5_file($this->fname) != md5_file($fname))
			return FALSE;

		return TRUE;
	}

	//--------------------------------------------------------------------------
	//! Save a copy of this file to $dest, assign ownership & permissions
	//--------------------------------------------------------------------------
	public function saveTo($dest, $owner = NULL, $group = NULL, $mode = 0664)
	{
		if (!copy($this->fname, $dest))
			throw new \Exception("Failed to copy file to $dest");

		if ($owner)
			chown($dest, $owner);

		if ($group)
			chgrp($dest, $group);

		chmod($dest, $mode);

		Log::submit(LOG_INFO, "File saved to destination: $dest");
	}
};
