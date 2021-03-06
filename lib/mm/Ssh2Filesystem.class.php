<?php
/*==============================================================================

	MM library

	mm\Ssh2Filesystem class implementation.

	$Id$

==============================================================================*/

namespace mm;


//! Exception for fs-related errors
class Ssh2FilesystemException extends FilesystemException {}


//------------------------------------------------------------------------------
//! Filesystem located on a SSH2/SCP/SFTP server
//------------------------------------------------------------------------------
class Ssh2Filesystem
	extends Filesystem
{
	protected $username;
	protected $password;
	protected $hostname;
	protected $port;
	protected $path;

	protected $connection = FALSE;
	protected $sftp = FALSE;

	//--------------------------------------------------------------------------
	//! Factory method
	//--------------------------------------------------------------------------
	public static function build($location, $subdir = NULL)
	{
		$pattern = '|^ssh2://(\w+):(\w+)@([\w-_.]+)(:(\d+))?(/.*)$|';
		if (preg_match($pattern, $location, $regs)) {
			$username = $regs[1];
			$password = $regs[2];
			$hostname = $regs[3];
			$port = $regs[4] ? (int) $regs[4] : NULL;
			$path = $regs[6];
			return new Ssh2Filesystem($username, $password, $hostname, $port, $path, $subdir);
		}
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($username, $password, $hostname, $port, $path, $subdir)
	{
		parent::__construct($subdir);

		$this->username = $username;
		$this->password = $password;
		$this->hostname = $hostname;
		$this->port = $port ? $port : 22;
		$this->path = $path;
	}

	//--------------------------------------------------------------------------
	//! Open connection to target
	//--------------------------------------------------------------------------
	protected function _connect()
	{
		if (!$this->connection) {
			$connection = @ssh2_connect($this->hostname, $this->port);
			if (!$connection)
				throw new Ssh2FilesystemException("FTP connection to {$this->hostname}:{$this->port} failed");

			$result = ssh2_auth_password($connection, $this->username, $this->password);
			if (!$result)
				throw new Ssh2FilesystemException("SSH2 Authentication failed while connecting to {$this->hostname}:{$this->port} with user '{$this->username}'");
			$this->connection = $connection;

			$sftp = ssh2_sftp($connection);
			if (!$sftp)
				throw new Ssh2FilesystemException("SSH2 Failed to get sftp connection");

			$handle = opendir("ssh2.sftp://".$sftp.$this->path);
			if (!is_resource($handle))
				throw new Ssh2FilesystemException("SSH2 Bad path ('{$this->path}'), try using an absolute path");

			$this->sftp = $sftp;

			// Create destination directory
			$d = $this->_realPath(".");
			$this->_mkdir($d, TRUE);
		}
	}

	//--------------------------------------------------------------------------
	//! List files on target
	//--------------------------------------------------------------------------
	protected function _listFiles($path)
	{
		if (!$this->connection || !$this->sftp)
			throw new Ssh2FilesystemException("SSH2 Not connected");

		$handle = opendir("ssh2.sftp://".$this->sftp.$path);
		if (!is_resource($handle))
			throw new Ssh2FilesystemException("SSH2 failed to open directory: $path");
		$entries = array();
		while (($entry = readdir($handle)) !== FALSE)
			$entries[] = $entry;

		return $entries;
	}

	//--------------------------------------------------------------------------
	//! Check if file exists on target
	//--------------------------------------------------------------------------
	protected function _exists($path)
	{
		if (!$this->connection || !$this->sftp)
			throw new Ssh2FilesystemException("SSH2 Not connected");

		return file_exists("ssh2.sftp://".$this->sftp.$path);
	}

	//--------------------------------------------------------------------------
	//! Create a directory on the target
	//--------------------------------------------------------------------------
	protected function _mkdir($path, $recursive = FALSE)
	{
		if (!$this->connection || !$this->sftp)
			throw new Ssh2FilesystemException("SSH2 Not connected");

		if ($this->_exists($path))
			return;
		$result = ssh2_sftp_mkdir($this->sftp, $path, 0777, $recursive);
		if (!$result)
			throw new Ssh2FilesystemException("SSH2 failed to create directory: $path");
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
		$this->_connect();
		$realPath = $this->_realPath($path);
		$this->_mkdir($path, $recursive);
	}

	//--------------------------------------------------------------------------
	//! Return the available space at the location $path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	public function getAvailableSpace($path)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	//--------------------------------------------------------------------------
	public function listFiles($path, $pattern = NULL)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		$fnames = $this->_listFiles($realPath);

		$entries = array();
		foreach ($fnames as $entry) {
			if (!$pattern || preg_match($pattern, $entry))
				$entries[] = $entry;
		}

		return $entries;
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern, adding extra information
	//--------------------------------------------------------------------------
	public function listFilesEx($path, $pattern = NULL)
	{
		throw new \Exception(__method__."(): Not implemented");
	}

	//--------------------------------------------------------------------------
	//! Tells whether file exists
	//--------------------------------------------------------------------------
	public function exists($path)
	{
		$this->_connect();
		$d = dirname($path);
		$n = basename($path);
		$realPath = $this->_realPath($d);
		$fnames = $this->_listFiles($realPath);
		return in_array($n, $fnames);
	}

	//--------------------------------------------------------------------------
	//! Tells whether the given path is a directory
	//--------------------------------------------------------------------------
	public function isDir($path)
	{
		return is_dir("ssh2.sftp://".$this->sftp.$realPath);
	}

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	public function fileSize($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		return filesize("ssh2.sftp://".$this->sftp.$realPath);
	}

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	public function putFile($source, $destination, $mode = 0644)
	{
		$this->_connect();
		$realPath = $this->_realPath($destination);
		Log::submit(LOG_DEBUG, "SSH2: Copying '$source' to '$realPath'");

		if (!ssh2_scp_send($this->connection, $source, $realPath, $mode))
			throw new Ssh2FilesystemException("SSH2 failed to PUT file to remote $realPath");
	}

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	public function getFile($source, $destination, $mode = 0777)
	{
		$this->_connect();
		$realPath = $this->_realPath($source);
		Log::submit(LOG_DEBUG, "SSH2: Copying '$realPath' to '$destination'");

		if (!ssh2_scp_recv($this->connection, $realPath, $destination))
			throw new Ssh2FilesystemException("SSH2 failed to GET remote file $realPath");
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
		$this->_connect();
		$realPath = $this->_realPath($path);
		return is_link("ssh2.sftp://".$this->sftp.$realPath);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function readLink($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		return ssh2_sftp_readlink($this->sftp, $realPath);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function symlink($path, $target)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "SSH2: Symlink $realPath --> $target");
		ssh2_sftp_symlink($this->sftp, $target, $realPath);
	}

	//--------------------------------------------------------------------------
	//! Remove file $path
	//--------------------------------------------------------------------------
	public function unlink($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "SSH2: Deleting: $realPath");
		if (!ssh2_sftp_unlink($this->sftp, $realPath))
			throw new Ssh2FilesystemException("SSH2 failed to delete remote file: $realPath");
	}

	//--------------------------------------------------------------------------
	//! Delete directory $path
	//--------------------------------------------------------------------------
	public function rmdir($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "SSH2: Deleting directory: '$realPath'");

		if (!ssh2_sftp_rmdir($this->sftp, $realPath))
			throw new Ssh2FilesystemException("SSH2 failed to delete remote directory: $realPath");
	}

	//--------------------------------------------------------------------------
	//! Returns a DateTime object representing the file's last modification time
	//--------------------------------------------------------------------------
	public function getLastModificationDt($path)
	{
		$realPath = $this->_realPath($path);
		throw new \Exception("Not implemented");
	}

	//--------------------------------------------------------------------------
	//! Tells whether the location is available
	//--------------------------------------------------------------------------
	public function isAvailable()
	{
		try {
			$this->_connect();
			return TRUE;
		}
		catch (Ssh2FilesystemException $e) {
			return FALSE;
		}
	}
};
