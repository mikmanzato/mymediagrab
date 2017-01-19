<?php
/*==============================================================================

	MM library

	mm\LocalFilesystem class implementation.

	$Id$

==============================================================================*/

namespace mm;


//! Exception for fs-related errors
class LocalFilesystemException extends FilesystemException {}


//------------------------------------------------------------------------------
//! A local filesystem
//------------------------------------------------------------------------------
class LocalFilesystem
	extends Filesystem
{
	//! The root path
	protected $path;

	//--------------------------------------------------------------------------
	//! Factory method
	//--------------------------------------------------------------------------
	public static function build($location, $subdir)
	{
		$pattern = '|^(file://)?(/.*)$|';
		if (preg_match($pattern, $location, $regs))
			return new LocalFilesystem($regs[2], $subdir);
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($path, $subdir)
	{
		parent::__construct($subdir);
		$this->path = $path;

		$p = $this->_realPath(".");
		if (!file_exists($p))
			Fs::mkdir($p, 0777, TRUE);
	}

	//--------------------------------------------------------------------------
	//! Produce the actual path
	//--------------------------------------------------------------------------
	protected function _realPath($path)
	{
		return Fs::joinPath(Fs::joinPath($this->path, $this->subdir), $path);
	}

	//--------------------------------------------------------------------------
	//! Create a directory, throw an exception upon failure
	//--------------------------------------------------------------------------
	public function mkdir($path, $mode = 0777, $recursive = FALSE)
	{
		$realPath = $this->_realPath($path);
		Fs::mkdir($realPath, $mode, $recursive);
	}

	//--------------------------------------------------------------------------
	//! Return the available space at the location $path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	public function getAvailableSpace($path)
	{
		return Fs::getAvailableSpace($path);
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	//--------------------------------------------------------------------------
	public function listFiles($path, $pattern = NULL)
	{
		$realPath = $this->_realPath($path);
		$entries = array();
		$dir = opendir($realPath);
		while ($entry = readdir($dir)) {
			if (!$pattern || preg_match($pattern, $entry))
				$entries[] = $entry;
		}
		closedir($dir);
		return $entries;
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern, adding extra information
	//--------------------------------------------------------------------------
	public function listFilesEx($path, $pattern = NULL)
	{
		$realPath = $this->_realPath($path);
		$fnames = $this->listFiles($path, $pattern);
		$entries = array();
		foreach ($fnames as $fname) {
			$fpath = Fs::joinPath($realPath, $fname);
			$type = '';

			if (is_dir($fpath))
				$type = 'dir';
			elseif (is_file($fpath))
				$type = 'file';

			$mtime = new \DateTime();
			$mtime->setTimestamp(filemtime($fpath));
			$entries[] = array(
					'name'  => $fname,
					'path'  => Fs::joinPath($path, $fname),
					'size'  => filesize($fpath),
					'type'  => $type,
					'mtime' => $mtime,
				);
		}
		return $entries;
	}

	//--------------------------------------------------------------------------
	//! Tells whether file exists
	//--------------------------------------------------------------------------
	public function exists($path)
	{
		$realPath = $this->_realPath($path);
		return file_exists($realPath);
	}

	//--------------------------------------------------------------------------
	//! Tells whether the given path is a directory
	//--------------------------------------------------------------------------
	public function isDir($path)
	{
		$realPath = $this->_realPath($path);
		return is_dir($realPath);
	}

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	public function fileSize($path)
	{
		$realPath = $this->_realPath($path);
		return filesize($realPath);
	}

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	public function putFile($source, $destination, $mode = 0777)
	{
		$realPath = $this->_realPath($destination);
		Log::submit(LOG_DEBUG, "Copying '$source' to '$realPath'");
		copy($source, $realPath);
	}

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	public function getFile($source, $destination, $mode = 0777)
	{
		$realPath = $this->_realPath($source);
		Log::submit(LOG_DEBUG, "Copying '$realPath' to '$destination'");
		copy($realPath, $destination);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function supportsLinks()
	{
		return TRUE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function isLink($path)
	{
		$realPath = $this->_realPath($path);
		return Fs::isLink($realPath);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function readLink($path)
	{
		$realPath = $this->_realPath($path);
		return readlink($realPath);
	}

	//--------------------------------------------------------------------------
	//! Creates a symbolic link $target which points to $path
	//--------------------------------------------------------------------------
	public function symlink($path, $target)
	{
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "Local: Symlink $realPath --> $target");
		symlink($target, $realPath);
	}

	//--------------------------------------------------------------------------
	//! Deletes file $path
	//--------------------------------------------------------------------------
	public function unlink($path)
	{
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "Deleting: '$realPath'");
		Fs::unlink($realPath);
	}

	//--------------------------------------------------------------------------
	//! Delete directory $path
	//--------------------------------------------------------------------------
	public function rmdir($path)
	{
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "Deleting directory: '$realPath'");
		Fs::rmdir($realPath);
	}

	//--------------------------------------------------------------------------
	//! Returns a DateTime object representing the file's last modification time
	//--------------------------------------------------------------------------
	public function getLastModificationDt($path)
	{
		$realPath = $this->_realPath($path);
		if (!Fs::exists($realPath))
			throw new \Exception("File '$path' does not exist on this Filesystem");

		$t = filemtime($realPath);
		$dt = new \DateTime();
		$dt->setTimestamp($t);
		return $dt;
	}

	//--------------------------------------------------------------------------
	//! Tells whether the location is available
	//--------------------------------------------------------------------------
	public function isAvailable()
	{
		$realPath = $this->_realPath(".");
		return file_exists($realPath);
	}
};

