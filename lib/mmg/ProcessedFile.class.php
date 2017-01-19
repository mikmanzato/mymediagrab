<?php
/*==============================================================================

	MyMediaGrab

	mmg\ProcessedFile class implementation.

	$Id$

==============================================================================*/

namespace mmg;


//------------------------------------------------------------------------------
//! A reference to a processed source file
//------------------------------------------------------------------------------
class ProcessedFile
{
	//! [int] Size of the file
	public $size = NULL;

	//! [\DateTime] Date and time of the file
	public $dt = NULL;

	//! [\DateTime] Date and time of processing
	public $timestamp = NULL;

	//--------------------------------------------------------------------------
	//! Constructor
	/*! \param $sf The related source file */
	//--------------------------------------------------------------------------
	public function __construct(SourceFile $sf)
	{
		$this->size = $sf->getSize();
		$this->dt = $sf->getLastModificationDt();
		$this->timestamp = new \DateTime("now");
	}
};


