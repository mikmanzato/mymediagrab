<?php
/*==============================================================================

	MyMediaGrab

	mmg\Source class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;

//------------------------------------------------------------------------------
//! Source on a local filesystem
//------------------------------------------------------------------------------
class FsSource
	extends Source
{
	protected $path;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct($name, Conf $conf)
	{
		parent::__construct($name, $conf);
	}

	//--------------------------------------------------------------------------
	//! Get or calculate date/time of next grab attempt
	/*! For FS sources retry at each cycle. */
	//--------------------------------------------------------------------------
	protected function getNextGrabAttemptDt()
	{
		return NULL;
	}
};
