<?php
/*==============================================================================

	MM library

	mm\Mailer class implementation

	$Id$

==============================================================================*/

namespace mm;

use mm\Email;

class MailerException extends \Exception {}


//------------------------------------------------------------------------------
//! Mailer class
//------------------------------------------------------------------------------
abstract class Mailer
{
	public static $smtpHostname = 'localhost';

	//--------------------------------------------------------------------------
	//! Send an email
	//--------------------------------------------------------------------------
	public static function send(Email $email,
								array $options = array())
	{
		require_once 'phpmailer/class.phpmailer.php';

		$smtpHostname = self::$smtpHostname;
		if (isset($options['smtp_hostname']))
			$smtpHostname = $options['smtp_hostname'];

		$mail = new \PHPMailer;
		$mail->IsSMTP();
		$mail->Host = $smtpHostname;
		$mail->SetFrom($email->getFrom());

		foreach ($email->getRecipients() as $recipient)
			$mail->addAddress($recipient);

		$mail->Subject = $email->getSubject();
		$mail->Body = $email->getHtmlBody();
		$mail->IsHTML(TRUE);

		if (!$mail->Send())
			throw new MailerException("Can't send email message: ".$mail->ErrorInfo);
	}
}



