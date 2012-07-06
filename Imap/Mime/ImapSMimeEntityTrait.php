<?php

namespace Imap\Mime;

use Imap\ImapException;

trait ImapSMimeEntityTrait{
	use ImapEntityTrait;

	public function isFetched(){
		if(!parent::isFetched()){
			return false;
		}

		return $this->hasRawContent();
	}

	public function fetch(){
		parent::fetch();

		$this->fetchRawContent();
	}

	public function getRawContent(){
		$this->fetchRawContent();

		return parent::getRawContent();
	}

	protected function fetchRawContent(){
		if($this->hasRawContent()){
			return;
		}

		try{
			$this->setRawContent($this->getMessage()->fetchRawSection($this->getSectionName()));
		}catch(ImapException $e){
			throw new MimeException('Failed to fetch imap raw content.',$e);
		}
	}
}
