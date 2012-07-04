<?php

namespace Imap\Mime;

use Imap\MessageInterface;

trait ImapLeafEntityTrait{
	protected $message;
	protected $sectionName;

	public function getMessage(){
		return $this->message;
	}

	public function getSectionName(){
		return $this->sectionName;
	}

	public function fetch(){
		if($this->isFetched()){
			return;
		}

		$this->setContent($this->message->fetchSectionBody($this->sectionName));
	}

	protected function setMessage(MessageInterface $message){
		$this->message=$message;
	}

	protected function setSectionName($sectionName){
		$this->sectionName=$sectionName;
	}
}
