<?php
/*==============================================================================

	MM library

	Autoloader for classes in the 'mm' namespace.

	$Id$

==============================================================================*/

// Register class autoloader
spl_autoload_register(function ($class) {
		if (!preg_match('/^mm\\\\/', $class))
			return;

		$file = dirname(dirname(__FILE__))."/".str_replace("\\", "/", $class).".class.php";
		if (!file_exists($file))
			throw new \Exception("Class not found: $class");

		require_once $file;
	});
