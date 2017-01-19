<?php
/*==============================================================================

	MyMediaGrab

	mmg\Sources class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Fs;

//------------------------------------------------------------------------------
//! Wrapper around all configured sources
//------------------------------------------------------------------------------
abstract class Sources
{
	private static $sources = array();
	private static $sourcesByName = array();

	//--------------------------------------------------------------------------
	// Add a new source to the queue
	//--------------------------------------------------------------------------
	private static function add(Source $source)
	{
		self::$sources[] = $source;
		self::$sourcesByName[$source->getName()] = $source;
	}

	//--------------------------------------------------------------------------
	// Load all sources from configuration directory
	//--------------------------------------------------------------------------
	public static function get()
	{
		return self::$sources;
	}

	//--------------------------------------------------------------------------
	// Return a source by its name
	/*! \param $name [string] Name of source to return
		\returns [Source] The found source, or NULL if no such source exists. */
	//--------------------------------------------------------------------------
	public static function getSourceByName($name)
	{
		if (isset(self::$sourcesByName[$name]))
			return self::$sourcesByName[$name];
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	// Load all sources from configuration directory
	//--------------------------------------------------------------------------
	public static function load($dir)
	{
		$confFnames = glob(Fs::joinPath($dir, "*.conf"));

		foreach ($confFnames as $confFname) {
			$source = Source::load($confFname);
			self::add($source);
		}
	}
};
