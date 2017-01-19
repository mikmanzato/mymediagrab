<?php
/*==============================================================================

	MM library

	mm\Filesystem class implementation.

	$Id$

==============================================================================*/

namespace mm;


//! Exception for target fs-related errors
class FilesystemException extends \Exception {}


//------------------------------------------------------------------------------
//! Abstraction for a generic filesystems
/*! A filesystem is a place where to store files. Filesystem class offers an
	abstraction layer for different types of filesystems (local, ftp, ssh).

	TODO: Consider using ready-made filesystem abstraction layers such as
	Flysystem (https://github.com/thephpleague/flysystem). */
//------------------------------------------------------------------------------
abstract class Filesystem
{
	//! [string] Subdirectory in the target FS
	protected $subdir = NULL;

	//--------------------------------------------------------------------------
	//! Joins path
	//--------------------------------------------------------------------------
	public static function joinPath($p1, $p2)
	{
		return Fs::joinPath($p1, $p2);
	}

	//--------------------------------------------------------------------------
	//! Build a concrete Filesystem class starting from the location specification
	//--------------------------------------------------------------------------
	public static function build($location, $subdir = NULL)
	{
		$fs = FtpFilesystem::build($location, $subdir);
		if ($fs)
			return $fs;

		$fs = Ssh2Filesystem::build($location, $subdir);
		if ($fs)
			return $fs;

		$fs = LocalFilesystem::build($location, $subdir);
		if ($fs)
			return $fs;

		throw new FilesystemException("Unknown location: $location");
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($subdir)
	{
		$this->subdir = $subdir;
	}

	//--------------------------------------------------------------------------
	//! Create a directory, throw an exception upon failure
	//--------------------------------------------------------------------------
	abstract public function mkdir($dir, $mode = 0777, $recursive = FALSE);

	//--------------------------------------------------------------------------
	//! Return the available space at the location %path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	abstract public function getAvailableSpace($path);

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	//--------------------------------------------------------------------------
	abstract public function listFiles($path, $pattern = NULL);

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern, adding extra information
	//--------------------------------------------------------------------------
	abstract public function listFilesEx($path, $pattern = NULL);

	//--------------------------------------------------------------------------
	//! Tells whether the given path exists
	//--------------------------------------------------------------------------
	abstract public function exists($path);

	//--------------------------------------------------------------------------
	//! Tells whether the given path is a directory
	//--------------------------------------------------------------------------
	abstract public function isDir($path);

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	abstract public function fileSize($path);

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	abstract public function putFile($source, $destination, $mode = 0777);

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	abstract public function getFile($source, $destination, $mode = 0777);

	//------------------------------------------------------------------------------
	//! Get a file from target and save to a local temporary file
	//------------------------------------------------------------------------------
	public function getFileToTemp($path, &$tmpFName)
	{
		$tmpFName = Fs::mkExactTempName(basename($path));
		$this->getFile($path, $tmpFName);
	}

	//--------------------------------------------------------------------------
	//! Tell whether the target filesystem supports (symbolic) links
	//--------------------------------------------------------------------------
	abstract public function supportsLinks();

	//--------------------------------------------------------------------------
	//! Tell whether $path is a (symbolic) link
	//--------------------------------------------------------------------------
	abstract public function isLink($path);

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	abstract public function readLink($path);

	//--------------------------------------------------------------------------
	//! Create a symbolic link $target which points to $path
	//--------------------------------------------------------------------------
	abstract public function symlink($path, $target);

	//--------------------------------------------------------------------------
	//! Unlink (delete) file $path from target
	//--------------------------------------------------------------------------
	abstract public function unlink($path);

	//--------------------------------------------------------------------------
	//! Remove directory $path from target
	//--------------------------------------------------------------------------
	abstract public function rmdir($path);

	//--------------------------------------------------------------------------
	//! Returns a DateTime object representing the file's last modification time
	//--------------------------------------------------------------------------
	abstract public function getLastModificationDt($path);

	//--------------------------------------------------------------------------
	//! Tells whether the location is available
	//--------------------------------------------------------------------------
	abstract public function isAvailable();
};

