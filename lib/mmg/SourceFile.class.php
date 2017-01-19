<?php
/*==============================================================================

	MyMediaGrab

	mmg\SourceFile class implementation.

	$Id$

==============================================================================*/

namespace mmg;


//------------------------------------------------------------------------------
//! A file located on the source
//------------------------------------------------------------------------------
class SourceFile
{
	//! [Source] The source this file belongs to
	protected $source;

	//! [string] Relative name of the file on the source's filesystem
	protected $fname;

	//! [mmg\MediaFile] The downloaded mediafile
	protected $mf;

	//! [\DateTime] Last modification time, cached
	protected $lastModificationDt;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Source $source, $fname, $size, \DateTime $mtime = NULL)
	{
		$this->source = $source;
		$this->fname = $fname;
		$this->size = $size;
		$this->lastModificationDt = $mtime;
	}

	//--------------------------------------------------------------------------
	//! String
	//--------------------------------------------------------------------------
	public function __toString()
	{
		return sprintf("%s on %s", $this->fname, $this->source->getName());
	}

	//--------------------------------------------------------------------------
	//! Return the source this SourceFile belongs to
	//--------------------------------------------------------------------------
	public function getSource()
	{
		return $this->source;
	}

	//--------------------------------------------------------------------------
	//! Return the relative file name
	//--------------------------------------------------------------------------
	public function getFName()
	{
		return $this->fname;
	}

	//--------------------------------------------------------------------------
	//! Return the relative file name
	//--------------------------------------------------------------------------
	public function getSize()
	{
		return $this->size;
	}

	//--------------------------------------------------------------------------
	//! Returns the datetime of last modification of the file on the source
	//--------------------------------------------------------------------------
	public function getLastModificationDt()
	{
		if (!$this->lastModificationDt) {
			$location = $this->source->getLocation();
			$this->lastModificationDt = $location->getLastModificationDt($this->fname);
		}

		return $this->lastModificationDt;
	}

	//--------------------------------------------------------------------------
	//! Provide the MediaFile
	/*! Move the file to local and return a MediaFile associated to this
		SourceFile. Caches the MediaFile for subsequent use. */
	//--------------------------------------------------------------------------
	public function getMediaFile()
	{
//		var_dump(__method__);
		if (is_null($this->mf)) {
			$location = $this->source->getLocation();
			$location->getFileToTemp($this->fname, $tmpFName);
//			var_dump($tmpFName);
			$this->mf = MediaFile::build($tmpFName, TRUE);
		}

		return $this->mf;
	}

	//--------------------------------------------------------------------------
	//! Delete the original file
	/*! Once this function has been called the object is no longer usable. */
	//--------------------------------------------------------------------------
	public function delete()
	{
		$location = $this->source->getLocation();
		$location->unlink($this->fname);
		$this->source = NULL;
		$this->fname = NULL;
		$this->lastModificationDt = NULL;
		$this->mf = NULL;
	}
};
