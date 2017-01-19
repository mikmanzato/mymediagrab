<?php
/*==============================================================================

	MM library

	mmg\Mailer class implementation

	$Id$

==============================================================================*/

namespace mmg;

use mm\MainConf;
use mm\Email;
use mm\System;


//------------------------------------------------------------------------------
//! Mail notification class
//------------------------------------------------------------------------------
class Mailer
{
	//--------------------------------------------------------------------------
	//! Send a email to the configured recipients
	/*! \param $subject [string] The email subject
		\param $subject [string] The email body (HTML)
		\param $recipients [string] Email recipients */
	//--------------------------------------------------------------------------
	public static function send(Email $email)
	{
		$mainConf = MainConf::getGlobal();

		$from = $mainConf->get('notifications', 'from', 'mymediagrab@'.System::getFqdn());
		$email->setFrom($from);

		$options = array();
		$smtpHostname = $mainConf->get('notifications', 'smtp_hostname');
		if ($smtpHostname)
			$options['smtp_hostname'] = $smtpHostname;

		\mm\Mailer::send($email, $options);
	}
};



