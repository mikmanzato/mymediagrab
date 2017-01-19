<?php
/*==============================================================================

	MM library

	mm\MainConf class

	$Id$

==============================================================================*/

namespace mm;


//------------------------------------------------------------------------------
//! Main configuration file
//------------------------------------------------------------------------------
class MainConf
	extends Conf
{
	private static $mainConf;

	//--------------------------------------------------------------------------
	//! Return the global MainConf
	//--------------------------------------------------------------------------
	public static function load($confFname)
	{
		if (!self::$mainConf)
			self::$mainConf = new self($confFname);

		return self::$mainConf;
	}

	//--------------------------------------------------------------------------
	//! Return the global MainConf
	//--------------------------------------------------------------------------
	public static function getGlobal()
	{
		if (!self::$mainConf)
			throw new \Exception("Configuration not loaded yet");

		return self::$mainConf;
	}
};
