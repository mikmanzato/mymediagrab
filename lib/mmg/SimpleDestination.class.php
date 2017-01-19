<?php
/*==============================================================================

	MyMediaGrab

	mmg\SimpleDestination class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Fs;
use mm\Filesystem;
use mm\Log;

//------------------------------------------------------------------------------
//! Simply managed destination
//------------------------------------------------------------------------------
class SimpleDestination
	extends Destination
{
	//! [string] The destination location (full path to a local directory)
	protected $location = NULL;

	//! [string] Destination subdirectory in the destination
	protected $subdir;

	//! [array] Alternate destination subdirectory on the destination
	/*! These subdirectories will be checked for existence before creating the
		subdirectory $subdir. However, subdirs won't be created. Useful to
		locate directories which have been moved with respect to their
		"canonical" position (e.g. "Y-m-d" day subdirectories which have been
		moved to "Y-m" subdirectories. */
	protected $altSubdirs = array();

	//! [string] The parking directory
	/*! Media files which cannot be safely attributed to a destination directory
		are attributed to the parking directory. */
	protected $parkingDir = NULL;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf);

		$this->location = $conf->getRequired('destination', 'location');
		if (!is_dir($this->location))
			throw new \Exception("Not found: $this->location");

		$subdir = $conf->get('destination', 'subdir', "Y/Y-m-d");
		if ($subdir)
			$this->subdir = $subdir;

		$defaultAltSubdirs = "Y/Y[-._]m[-._]d/;Y/Y[-._]m/Y[-._]m[-._]d/";
		$s = $conf->get('destination', 'alt_subdirs', $defaultAltSubdirs);
		if ($s)
			$this->altSubdirs = explode(',', $s);

		$this->parkingDir = $conf->getRequired('destination', 'parking');
		if (!is_dir($this->parkingDir))
			throw new \Exception("Not found: $this->parkingDir");
	}

	//--------------------------------------------------------------------------
	//! Returns the date of assignment of the media
	/*! \returns [\DateTime] The date of the media, with the difference that
			images up to 4am are attributed to the previous solar day. */
	//--------------------------------------------------------------------------
	protected static function getAssignmentDate(MediaFile $mf)
	{
		$dt = $mf->getDateTime();
		if (!$dt)
			return NULL;

		$hour = (int) $dt->format('H');
		$dt->setTime(12, 0, 0);
		if ($hour <= 3)
			$dt = $dt->sub(new \DateInterval("P1D"));
		return $dt;
	}

	//--------------------------------------------------------------------------
	//! Create a directory into the destination location and assign proper permissions
	/*! \param $root [string] Absolute path to root directory
		\param $path [string] Relative path of directory to create into the root path
		\param $owner [string] Username of directory owner */
	//--------------------------------------------------------------------------
	protected function mkdir($root, $path, $owner)
	{
		$fullPath = Fs::joinPath($root, $path);

		if (!file_exists($fullPath)) {
			Log::submit(LOG_DEBUG, "Creating directory: $path");
			Fs::mkdir($fullPath, 0775, TRUE);
		}
		elseif (!is_dir($fullPath))
			throw new \Exception("File is in the way while creating directory with same name: $fullPath");

		chmod($fullPath, 0775);
		if ($owner)
			chown($fullPath, $owner);
		if ($this->group)
			chgrp($fullPath, $this->group);

		return $fullPath;
	}

	//--------------------------------------------------------------------------
	//!
	/*! \param [string] RELATIVE path into destination location
		\param [string] Pattern to apply
	*/
	//--------------------------------------------------------------------------
	protected function lookupDestinationPath($dirPattern, \DateTime $dt, $create, $owner, $path = '.')
	{
		if (!$dirPattern)
			return $path;

		$patterns = explode('/', $dirPattern);
		$pattern = array_shift($patterns);
		$dirPattern = implode('/', $patterns);
		$pattern = $dt->format($pattern);

		//~ echo __method__."(): path='$path'; pattern='$pattern'\n";

		$d = dir(Fs::joinPath($this->location, $path));
		$found = FALSE;
		while (false !== ($entry = $d->read())) {
			if (($entry == '.') || ($entry == '..'))
				continue;

			$regex = "|^$pattern|";
			if (preg_match($regex, $entry)) {
				$found = TRUE;
				$path = Fs::joinPath($path, $entry);
				break;
			}
		}

		$d->close();

		if ($found) {
			//~ echo __method__."(): Found: $path\n";
			return $this->lookupDestinationPath($dirPattern, $dt, $create, $owner, $path);
		}
		elseif ($create) {
			//~ echo __method__."(): Not found: $path\n";
			$path = Fs::joinPath($path, $pattern);
			$this->mkdir($this->location, $path, $owner);
			return $this->lookupDestinationPath($dirPattern, $dt, $create, $owner, $path);
		}
		else {
			//~ echo __method__."(): Not found, skipping\n";
			return FALSE;
		}
	}

	//--------------------------------------------------------------------------
	//! Save media file $mf to destination path $path into $root
	//--------------------------------------------------------------------------
	protected function saveMediaFileToPath(MediaFile $mf, $root, $path, $owner)
	{
		Log::submit(LOG_DEBUG, sprintf("Saving '%s' to '%s' in '%s'", $mf->getBasename(), $path, $root));

		$destRelative = Fs::joinPath($path, $mf->getBasename());
		$dest = Fs::joinPath($root, $destRelative);

		$this->mkdir($root, dirname($destRelative), $owner);

		if (file_exists($dest)) {
			// Don't re-copy existing file
			if ($mf->isSameAs($dest)) {
				Log::submit(LOG_DEBUG, "File already esists: $destRelative");
				return 'already-exists';
			}

			// A different file with the same name already exists on the destination
			// Try building a nonexisting filename in the form: /path/to/file/basename-NNNN.ext
			Log::submit(LOG_INFO, "A different file with the same name already esists: $destRelative");

			$basename = basename($destRelative);
			if (!preg_match('/^(.*)(\\.[a-z0-9]+)$/i', $basename, $regs)) {
				Log::submit(LOG_ERR, "Can't match filename: $dest");
				return 'error';
			}

			$name = $regs[1];
			$ext = $regs[2];
			$n = 0;
			do {
				$n++;
				$basename = sprintf("%s-%04d%s", $name, $n, $ext);
				$destRelative = Fs::joinPath($path, $basename);
				$dest = Fs::joinPath($root, $destRelative);
				if ($mf->isSameAs($dest)) {
					Log::submit(LOG_DEBUG, "File already esists: $destRelative");
					return 'already-exists';
				}
			} while (file_exists($dest));

			Log::submit(LOG_INFO, "Renamed to: $destRelative");
		}

		$mf->saveTo($dest, $owner, $this->group);
		return 'saved';
	}

	//--------------------------------------------------------------------------
	//! Store a file in the "canonical path" of the destination
	//--------------------------------------------------------------------------
	protected function storeMediaFile(MediaFile $mf, $subdir, $owner)
	{
		// File has an attribution date: locate the destination path
		$dt = self::getAssignmentDate($mf);
		if (!$dt) {
			Log::submit(LOG_WARNING, "A reliable assignment date cannot be found.");
			return 'error';
		}

		// Search for alternate paths, if existing
		foreach ($this->altSubdirs as $dir) {
			$path = $this->lookupDestinationPath($dir, $dt, FALSE, $owner);
			if ($path)
				break;
		}

		if ($path === FALSE) {
			// Path not found, create the "canonical" one
			$path = $this->lookupDestinationPath($this->subdir, $dt, TRUE, $owner);
		}

		if ($subdir) {
			// Create subdirectory into the destination path
			$path = Fs::joinPath($path, $subdir);
			$this->mkdir($this->location, $path, $owner);
		}

		// Save file to destination
		return $this->saveMediaFileToPath($mf, $this->location, $path, $owner);
	}

	//--------------------------------------------------------------------------
	//! Store a file in this destination
	/*! \param $sf The sourcefile to store
		\param $subdir [string] Name of subdirectory where the file shall be
			stored on the destination location
		\param $owner [string] Username of the user who shall own the file on
			the destination.
		\retval 'error' If an error was encountered
		\retval 'saved' The file was succesfully stored
		\retval 'parked' The file was saved into the parking area of the
			destination, for manual processing.
		\retval 'already-exists' The file was already existing in the
			destination */
	//--------------------------------------------------------------------------
	public function store(SourceFile $sf, $subdir = '', $owner = NULL)
	{
		Log::submit(LOG_INFO, sprintf("Processing file %s", (string) $sf));

		if (!$owner)
			$owner = $this->owner;

		try {
			$mf = $sf->getMediaFile();		// copies file locally
		}
		catch (\UnknownMediaFileException $e) {
			Log::submit(LOG_WARNING, sprintf("Can't create a media file from %s", (string) $sf));
			return 'error';
		}

		$result = $this->storeMediaFile($mf, $subdir, $owner);
		if ($result != 'error')
			return $result;

		// Couldn't save media file to canonical location, save to parking area
		$path = $sf->getSource()->getName();
		$result = $this->saveMediaFileToPath($mf, $this->parkingDir, $path, $owner);
		if ($result == 'saved')
			return 'parked';
		else
			return $result;
	}
};
