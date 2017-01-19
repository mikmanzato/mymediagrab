<?php
/*==============================================================================

	MyMediaGrab

	mmg\UnknownMediaFile class implementation.

	$Id$

==============================================================================*/

namespace mmg;


//------------------------------------------------------------------------------
//! MediaFile for a generic, unknown media
//------------------------------------------------------------------------------
class UnknownMediaFile
	extends MediaFile
{
	//--------------------------------------------------------------------------
	//! Builder
	//--------------------------------------------------------------------------
	public static function build($fname, $temp = FALSE)
	{
		return new self($fname, $temp);
	}

	//--------------------------------------------------------------------------
	//! Returns datetime of the media file
	//--------------------------------------------------------------------------
	public function getDateTime()
	{
		return NULL;
	}
};
