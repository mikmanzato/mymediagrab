<?php
/*==============================================================================

	MM library

	mm\FtpFilesystem class implementation.

	$Id$

=======================i=======================================================*/

namespace mm;


//! Exception for fs-related errors
class FtpFilesystemException extends FilesystemException {}


//------------------------------------------------------------------------------
//! A filesystem located on a FTP server
/*! Supports both FTP and FTPS.

	To configure FTPS on proftpd: https://www.howtoforge.com/tutorial/install-proftpd-with-tls-on-ubuntu-16-04/
*/
//------------------------------------------------------------------------------
class FtpFilesystem
	extends Filesystem
{
	protected $secure;
	protected $username;
	protected $password;
	protected $hostname;
	protected $port;
	protected $path;

	protected $connection = FALSE;

	//--------------------------------------------------------------------------
	//! Factory method
	//--------------------------------------------------------------------------
	public static function build($location, $subdir = NULL)
	{
		$pattern = '|^ftp(s?)://(\w+):(\w+)@([\w-_.]+)(:(\d+))?/(.*)$|';
		if (preg_match($pattern, $location, $regs)) {
			$secure = ($regs[1] == 's');
			$username = $regs[2];
			$password = $regs[3];
			$hostname = $regs[4];
			$port = $regs[6] ? (int) $regs[6] : NULL;
			$path = $regs[7];
			return new FtpFilesystem($secure, $username, $password, $hostname, $port, $path, $subdir);
		}
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($secure, $username, $password, $hostname, $port, $path, $subdir)
	{
		parent::__construct($subdir);

		$this->secure = (bool) $secure;
		$this->username = $username;
		$this->password = $password;
		$this->hostname = $hostname;
		$this->port = $port ? $port : 21;
		$this->path = $path;
	}

	//--------------------------------------------------------------------------
	//! Return the hostname
	//--------------------------------------------------------------------------
	public function getHostname()
	{
		return $this->hostname;
	}

	//--------------------------------------------------------------------------
	//! Connect to remote host
	//--------------------------------------------------------------------------
	protected function _connect()
	{
		if (!$this->connection) {
			$timeout = 5;	// Connection timeout
			if ($this->secure)
				$connection = @ftp_ssl_connect($this->hostname, $this->port, $timeout);
			else
				$connection = @ftp_connect($this->hostname, $this->port, $timeout);

			if (!$connection)
				throw new FtpFilesystemException("FTP connection to {$this->hostname}:{$this->port} failed: ".error_get_last()['message']);

			$result = ftp_login($connection, $this->username, $this->password);
			if (!$result)
				throw new FtpFilesystemException("FTP Authentication failed while connecting to {$this->hostname}:{$this->port} with user '{$this->username}'");

			$this->connection = $connection;
			ftp_set_option($this->connection, FTP_TIMEOUT_SEC, 10);
		}
	}

	//--------------------------------------------------------------------------
	//! Produce the actual path
	//--------------------------------------------------------------------------
	protected function _realPath($path)
	{
		//~ echo __method__."('$path')\n";
		//~ var_dump($this->path);
		//~ var_dump($this->subdir);
		//~ var_dump($path);
		$p = self::joinPath(self::joinPath($this->path, $this->subdir), $path);
		//~ var_dump($p);
		return $p;
	}

	//--------------------------------------------------------------------------
	//! Create directory on the FTP server
	//--------------------------------------------------------------------------
	protected function _mkdir($path, $recursive = FALSE)
	{
		if (!$this->connection)
			throw new FtpFilesystemException("FTP Not connected");

		//~ static $i = 0;
		//~ echo __method__."('$path')\n";
		//~ if ($i++ == 10) die();
		if ($path == '.')
			return;

		$d = dirname($path);
		if ($recursive)
			$this->_mkdir($d, TRUE);

		$fnames = ftp_nlist($this->connection, $d);
		if ($fnames === FALSE)
			throw new FtpFilesystemException("FTP failed to get directory listing: $d");

		$n = basename($path);
		if (!in_array($path, $fnames)) {
			Log::submit(LOG_DEBUG, "FTP: Making directory: $path");
			$result = ftp_mkdir($this->connection, $path);
			if (!$result)
				throw new FtpFilesystemException("FTP failed to create directory: $path");
		}
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
//		echo __method__."(): '$path', '$pattern'\n";
		$realPath = $this->_realPath($path);
//		echo "real path: $realPath\n";

		$fnames = ftp_nlist($this->connection, $realPath);
		if ($fnames === FALSE)
			throw new FtpFilesystemException("FTP: Can't get directory listing for: $path");
		$entries = array();
		foreach ($fnames as $fname) {
			$entry = basename($fname);
			if (!$pattern || preg_match($pattern, $entry))
				$entries[] = $entry;
		}
//		var_dump($entries);
		return $entries;
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	/*! Return extra information for each file: file type, size and date/time */
	//--------------------------------------------------------------------------
	public function listFilesEx($path, $pattern = NULL)
	{
//		echo __method__."(): '$path', '$pattern'\n";

		$this->_connect();
		$realPath = $this->_realPath($path);
//		echo "real path: $realPath\n";

		$systype = ftp_systype($this->connection);

		// Get raw FTP directory listing
		$lines = ftp_rawlist($this->connection, $realPath);
		if ($lines === FALSE)
			throw new FtpFilesystemException("FTP: Can't get raw directory listing for: $path");

		$entries = array();
		foreach ($lines as $line) {
			//~ var_dump($line);

			switch ($systype) {
				case 'UNIX':
					// "-rwxrw-r--   1 aragorn  aragorn   1951529 Dec 31 11:06 20150826_120616.jpg"
					$chunks = preg_split("/\s+/", $line);
					list($rights, $number, $user, $group, $size, $month, $day, $time) = $chunks;
					array_splice($chunks, 0, 8);

					// File name
					$fname = implode(" ", $chunks);

					// File type
					$type = NULL;
					switch ($rights{0}) {
						case 'd':
							$type = 'dir';
							break;
						case '-':
							$type = 'file';
							break;
					}

					// Parse date and build a DateTime object
					if (preg_match('/^[0-9]{4}$/', $time)) {
						$year = $time;
						$time = "23:59:59";
					}
					else
						$year = (int) date('Y');

					$s = "$month $day $year $time";
					$v = strptime($s, "%b %d %Y %H:%M");
					$ts = mktime($v['tm_hour'], $v['tm_min'], $v['tm_sec'], $v['tm_mon']+1, $v['tm_mday'], $v['tm_year']+1900);
					if ($ts > time())
						$ts = mktime($v['tm_hour'], $v['tm_min'], $v['tm_sec'], $v['tm_mon']+1, $v['tm_mday'], $v['tm_year']+1900-1);

					$mtime = new \DateTime;
					$mtime->setTimestamp($ts);
					//~ echo $mtime->format("Y-m-d H:i:s")."\n";	// DEBUG
					break;
				default:
					throw new \Exception("FTP systype '$systype' is not implemented");
			}

			if ($pattern && !preg_match($pattern, $fname))
				continue;

			$entry = array(
					'name'  => $fname,
					'path'  => Fs::joinPath($path, $fname),
					'size'  => $size,
					'type'  => $type,
					'mtime' => $mtime,
				);

			$entries[] = $entry;
		}

		return $entries;
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
		$fnames = ftp_nlist($this->connection, $realPath);
		if ($fnames === FALSE)
			throw new FtpFilesystemException("FTP: Can't get listing for: $path");

		return in_array($n, $fnames);
	}

	//--------------------------------------------------------------------------
	//! Tells whether the given path is a directory
	//--------------------------------------------------------------------------
	public function isDir($path)
	{
		$realPath = $this->_realPath($path);
		$fnames = ftp_nlist($this->connection, $realPath);
		if ($fnames === FALSE)
			throw new FtpFilesystemException("FTP: Can't get listing for: $path");

		if (is_array($fnames)) {
			$n = basename($path);
			if ((sizeof($fnames) == 1) && ($fnames[0] == $n))
				return FALSE;
			return TRUE;
		}
		else
			return FALSE;
	}

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	public function fileSize($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		return ftp_size($this->connection, $realPath);
	}

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	public function putFile($source, $destination, $mode = 0777)
	{
		$this->_connect();
		$realPath = $this->_realPath($destination);
		Log::submit(LOG_DEBUG, "FTP: Putting '$source' to '$realPath'");

		if (!ftp_put($this->connection, $realPath, $source, FTP_BINARY))
			throw new FtpFilesystemException("FTP failed to PUT file to remote $realPath");
	}

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	public function getFile($source, $destination, $mode = 0777)
	{
		$this->_connect();
		$realPath = $this->_realPath($source);
		Log::submit(LOG_DEBUG, "FTP: Getting '$realPath' to '$destination'");

		if (!@ftp_get($this->connection, $destination, $realPath, FTP_BINARY))
			throw new FtpFilesystemException("FTP failed to GET remote file $realPath");
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function supportsLinks()
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function isLink($path)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function readLink($path)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function symlink($path, $target)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//! Remove file $path
	//--------------------------------------------------------------------------
	public function unlink($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "FTP: Deleting: $realPath");

		if (!ftp_delete($this->connection, $realPath))
			throw new FtpFilesystemException("FTP failed to delete remote file: $realPath");
	}

	//--------------------------------------------------------------------------
	//! Delete directory $path
	//--------------------------------------------------------------------------
	public function rmdir($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "FTP: Deleting directory: '$realPath'");

		if (!ftp_rmdir($this->connection, $realPath))
			throw new FtpFilesystemException("FTP failed to delete remote directory: $realPath");
	}

	//--------------------------------------------------------------------------
	//! Returns a DateTime object representing the file's last modification time
	//--------------------------------------------------------------------------
	public function getLastModificationDt($path)
	{
		$realPath = $this->_realPath($path);
		$t = ftp_mdtm($this->connection, $realPath);
		$dt = new \DateTime();
		$dt->setTimestamp($t);
		return $dt;
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
		catch (FtpFilesystemException $e) {
			return FALSE;
		}
	}
};

