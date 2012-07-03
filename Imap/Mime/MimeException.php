<?php

namespace Imap\Mime;

use Imap\ImapException;

class MimeException extends ImapException{
	public function __construct($message,\Exception $previousException=null){
		parent::__construct($message,false,$previousException);
	}
}
