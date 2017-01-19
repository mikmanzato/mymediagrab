<?php
/*==============================================================================

	MyMediaGrab

	mmg\VideoMediaFile class implementation.

	$Id$

==============================================================================*/

namespace mmg;


//------------------------------------------------------------------------------
//! A MediaFile which refers to a video
//------------------------------------------------------------------------------
class VideoMediaFile
	extends MediaFile
{
	//--------------------------------------------------------------------------
	//! Builder
	//--------------------------------------------------------------------------
	public static function build($fname, $temp = FALSE)
	{
		//~ if (!is_file($fname))
			//~ return NULL;

		$mimeType = @mime_content_type($fname);

		if (preg_match('|^video/|', $mimeType))
			return new self($fname, $temp);
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Returns datetime of the video
	//--------------------------------------------------------------------------
	public function getDateTime()
	{
		return parent::getDateTime();
	}
};
