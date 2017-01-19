<?php
/*==============================================================================

	MyMediaGrab

	mmg\PhoneSourceStatus class implementation.

	$Id$

==============================================================================*/

namespace mmg;


//------------------------------------------------------------------------------
//! Source status, specific to PhoneSource's
//------------------------------------------------------------------------------
class PhoneSourceStatus
	extends SourceStatus
{
	//! [\DateTime] Date and time when the source was last asked to connect
	/*! Used to avoid to send connection request emails too often. */
	public $lastConnectionRequestDt = NULL;
};
