<?php
/*==============================================================================

	MyMediaGrab

	mmg\ImageMediaFile class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Log;


//------------------------------------------------------------------------------
//! A MediaFile which refers to an image
//------------------------------------------------------------------------------
class ImageMediaFile
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

		if (preg_match('|^image/|', $mimeType))
			return new self($fname, $temp);
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Returns datetime of the image
	//--------------------------------------------------------------------------
	public function getDateTime()
	{
		$mimeType = @mime_content_type($this->fname);
		if ($mimeType == 'image/jpeg') {
			// JPEG image: get date/time from EXIF information
			$exifData = @exif_read_data($this->fname);
			if (!empty($exifData['DateTimeOriginal'])) {
				Log::submit(LOG_DEBUG, "Getting date and time from EXIF");
				$exifDate = $exifData['DateTimeOriginal'];
				$dt = \DateTime::createFromFormat("Y:m:d H:i:s", $exifDate);
				return $dt;
			}
		}

		return parent::getDateTime();
	}
};
