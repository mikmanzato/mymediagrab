<?php
/*==============================================================================

	MyMediaGrab

	mmg\Destinations class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;
use mm\Fs;


//------------------------------------------------------------------------------
//! Wrapper around all configured destinations
//------------------------------------------------------------------------------
abstract class Destinations
{
	private static $destinations = array();
	private static $destinationsByName = array();

	//--------------------------------------------------------------------------
	// Add a new destination to the queue
	//--------------------------------------------------------------------------
	private static function add(Destination $destination)
	{
		self::$destinations[] = $destination;
		self::$destinationsByName[$destination->getName()] = $destination;
	}

	//--------------------------------------------------------------------------
	// Return a destination by its name
	/*! \param $name [string] Name of destination to return
		\returns [Destination] The found destination, or NULL if no such destination exists. */
	//--------------------------------------------------------------------------
	public static function getDestinationByName($name)
	{
		if (!isset(self::$destinationsByName[$name]))
			throw new \Exception("Unknown destination: $name");

		return self::$destinationsByName[$name];
	}

	//--------------------------------------------------------------------------
	// Load all destinations from configuration directory
	//--------------------------------------------------------------------------
	public static function load($dir)
	{
		$confFnames = glob(Fs::joinPath($dir, "*.conf"));

		foreach ($confFnames as $confFname) {
			$conf = new Conf($confFname);
			$class = $conf->get('destination', 'type', 'mmg\SimpleDestination');
			$destination = new $class($conf);
			self::add($destination);
		}
	}
};
