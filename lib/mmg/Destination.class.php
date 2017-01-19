<?php
/*==============================================================================

	MyMediaGrab

	mmg\Destination class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Conf;

//------------------------------------------------------------------------------
//! Destination (generic class)
//------------------------------------------------------------------------------
abstract class Destination
{
	//! [string] Coded name of the destination
	protected $name;

	//! [string] Descriptive label of the destination
	protected $label;

	//! [string] Username of the user who will own the files
	protected $owner;

	//! [string] Groupname of the group who will own the files
	protected $group;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		$this->name = $conf->getRequired('destination', 'name');
		$this->label = $conf->getRequired('destination', 'label');
		$this->owner = $conf->getRequired('destination', 'owner');
		$this->group = $conf->getRequired('destination', 'group');
	}

	//--------------------------------------------------------------------------
	//! Return the destination name
	//--------------------------------------------------------------------------
	public function getName()
	{
		return $this->name;
	}

	//--------------------------------------------------------------------------
	//! Store a file in this destination
	/*! \param $sf The sourcefile to store
		\param $subdir [string] Name of subdirectory where the file shall be
			stored on the destination location
		\param $owner [string] Username of the user who shall own the file on
			the destination.
		\retval 'error' If an error was encountered
		\retval 'saved' The file was succesfully stored
		\retval 'parked' The file was saved into the parking area of the
			destination, for manual processing.
		\retval 'already-exists' The file was already existing in the
			destination */
	//--------------------------------------------------------------------------
	abstract public function store(SourceFile $sf, $subdir = '', $owner = NULL);
};
