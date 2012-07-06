<?php

namespace Imap\Mime;

use Imap\MessageInterface;

trait ImapEntityTrait{
	protected $message;
	protected $sectionName;

	public function getMessage(){
		return $this->message;
	}

	public function getSectionName(){
		return $this->sectionName;
	}

	protected function setMessage(MessageInterface $message){
		$this->message=$message;
	}

	protected function setSectionName($sectionName){
		$this->sectionName=$sectionName;
	}
}
