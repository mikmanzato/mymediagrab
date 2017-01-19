<?php
/*==============================================================================

	MM library

	mm\Email class implementation

	$Id$

==============================================================================*/

namespace mm;


class EmailException extends \Exception {}


//------------------------------------------------------------------------------
//! Mailer class
//------------------------------------------------------------------------------
class Email
{
	protected $htmlBody;
	protected $subject;
	protected $from;
	protected $recipients;

	//--------------------------------------------------------------------------
	//! Build an HTML email from full text
	//--------------------------------------------------------------------------
	public static function buildHtml($htmlBody)
	{
		$email = new self;

		$lines = explode("\n", $htmlBody);
		while (!empty($lines)) {
			$line = $lines[0];
			if (preg_match('/^([a-z0-9-]+) *:(.*)$/i', $line, $regs)) {
				if (strtolower($regs[1]) == 'subject')
					$email->setSubject(trim($regs[2]));
				array_shift($lines);
			}
			else
				break;
		}

		$htmlBody = implode("\n", $lines);
		$email->setHtmlBody($htmlBody);
		return $email;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function setHtmlBody($htmlBody)
	{
		$this->htmlBody = $htmlBody;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function getHtmlBody()
	{
		return $this->htmlBody;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function setSubject($subject)
	{
		$this->subject = $subject;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function getSubject()
	{
		return $this->subject;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function setFrom($from)
	{
		$this->from = $from;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function getFrom()
	{
		return $this->from;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function setRecipients($recipients)
	{
		if (is_string($recipients))
			$recipients = explode(",", $recipients);
		elseif (!is_array($recipients))
			throw new EmailException("Invalid parameter \$recipients");

		$this->recipients = $recipients;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function getRecipients()
	{
		return $this->recipients;
	}
}



