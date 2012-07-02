<?php

namespace Imap;

class ImapException extends \RuntimeException{
	public function __construct($message,$useLastImapError=true,\Exception $previousException=null){
		if($useLastImapError){
			$message.=': '.imap_last_error();
		}

		parent::__construct($message,0,$previousException);
	}
}
