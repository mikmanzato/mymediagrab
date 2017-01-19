<?php
/*==============================================================================

	Main file

	$Id$

==============================================================================*/

// Set include path
ini_set('include_path', dirname(__FILE__).PATH_SEPARATOR.
                        ini_get('include_path'));

require_once 'mm/autoload.php';
require_once 'mmg/autoload.php';

mmg\Main::run();
