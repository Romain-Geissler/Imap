<?php

namespace Imap\Mime;

use Imap\ImapException;

trait ImapLeafEntityTrait{
	use ImapEntityTrait;

	public function fetch(){
		if($this->isFetched()){
			return;
		}

		try{
			$this->setContent($this->message->fetchSectionBody($this->sectionName));
		}catch(ImapException $e){
			throw new MimeException('Failed to fetch imap body content.',$e);
		}
	}
}
