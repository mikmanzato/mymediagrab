<?php
/*==============================================================================

	MyMediaGrab

	mmg\SourceDirIterator class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Log;

//------------------------------------------------------------------------------
//! Visit a tree of files in the Source
//------------------------------------------------------------------------------
class SourceDirIterator
{
	//! [array(string)] Extensions of files to ignore
	public static $ignoredExtensions = array();

	//! [array(string)] Extensions of files to allow
	public static $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'avi', 'mp4', 'mpeg4');

	//! [Source] The related source
	protected $source;

	//! [mm\Filesystem] The location on the Source
	protected $location;

	//! [string] Subdirectory on the source
	protected $dir;

	//! [array] Entries in the current directory, as returned by listFilesEx()
	protected $entries;

	//! [SourceDirIterator] The iterator which is used to visit subdirectories
	protected $subdirIterator;

	//! [int] Depth of search
	protected $depth = 0;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Source $source, $dir = '.', $depth = 0)
	{
		//~ echo __method__."(): Iterating on $dir\n";
		$this->source = $source;
		$this->dir = $dir;
		$this->depth = $depth;
		$this->location = $this->source->getLocation();
		$this->entries = $this->location->listFilesEx($dir);
		// var_dump($this->entries); die();
	}

	//--------------------------------------------------------------------------
	//! Return the next SourceFile in the array. Visit subdirectories "inorder"
	//--------------------------------------------------------------------------
	public function next()
	{
		while (true) {
			if ($this->subdirIterator) {
				$sf = $this->subdirIterator->next();
				if ($sf)
					return $sf;

				$this->subdirIterator = NULL;
			}

			if (empty($this->entries)) {
				if ($this->source->pruneEmptyDirs() && ($this->depth > 0)) {
					// Delete empty subdirectory
//					echo "Check if empty: $this->dir \n";
					$entries = $this->location->listFilesEx($this->dir);
					$hasHidden = FALSE;
					foreach ($entries as $entry) {
						if ($entry['name']{0} == '.')
							$hasHidden = TRUE;
					}
//					var_dump($entries);
					if (($hasHidden && count($entries) == 2) || (!$hasHidden && count($entries) == 0))
						$this->location->rmdir($this->dir);
				}

				return NULL;
			}

			$entry = array_shift($this->entries);
			if (substr($entry['name'], 0, 1) == '.')
				continue;

			$fpath = $this->location->joinPath($this->dir, $entry['name']);
//			Log::submit(LOG_DEBUG, __method__."(): fpath= '$fpath'");

			// Iterate subdirectories
			if ($entry['type'] == 'dir') {
				$this->subdirIterator = new self($this->source, $fpath, $this->depth + 1);
				continue;
			}

			// Get & check file extension
			$ext = strtolower(pathinfo($fpath, PATHINFO_EXTENSION));
//			Log::submit(LOG_DEBUG, __method__."(): ext= '$ext'");

			if (in_array($ext, self::$ignoredExtensions)) {
				// Ignore this file
				Log::submit(LOG_DEBUG, "Ignoring file: $fpath");
				continue;
			}

			if (!in_array($ext, self::$allowedExtensions)) {
				// Unknown extension, register in log
				Log::submit(LOG_WARNING, "Unknown file extension: $fpath");
				continue;
			}

			//~ echo __method__."(): Returning: '$fpath'\n";
			$sf = new SourceFile($this->source, $fpath, $entry['size'], $entry['mtime']);
			return $sf;
		}
	}
};
