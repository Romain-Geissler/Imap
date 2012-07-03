<?php

namespace Imap\Mime;

class Part extends AbstractEntity{
	protected $content;

	public function __construct($content,$type=TYPETEXT,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setContent($content);
	}

	public function getContent(){
		return $this->content;
	}

	public function setContent($content){
		$this->content=(string)$content;
	}

	public function getBodies(){
		$body=$this->getBodyWithoutContent();
		$body['contents.data']=$this->content;

		return [$body];
	}

	public function toString(array $envelope=[]){
		return imap_mail_compose($envelope,$this->getBodies());
	}
}
