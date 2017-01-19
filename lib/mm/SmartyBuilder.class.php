<?php
/*==============================================================================

	MM library

	mm\SmartyBuilder class implementation.

	$Id$

==============================================================================*/

namespace mm;

require_once 'smarty/Smarty.class.php';


//------------------------------------------------------------------------------
//! Smarty template builder class
//------------------------------------------------------------------------------
abstract class SmartyBuilder
{
	public static $templateDir = NULL;
	public static $compileDir = NULL;
	public static $configDir = NULL;
	public static $cacheDir = NULL;

	//------------------------------------------------------------------------------
	//! Prepare and return a new Smarty template
	//------------------------------------------------------------------------------
	public static function getSmarty()
	{
		if (!is_dir(self::$compileDir))
			Fs::mkdir(self::$compileDir);

		$smarty = new \Smarty();
		$smarty->setTemplateDir(self::$templateDir);
		$smarty->setCompileDir(self::$compileDir);
		//~ $smarty->setConfigDir(self::$configDir);
		//~ $smarty->setCacheDir(self::$cacheDir);

		return $smarty;
	}
};
