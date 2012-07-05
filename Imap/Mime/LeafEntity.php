<?php

namespace Imap\Mime;

use Imap\Mime\Attachment\AttachmentInterface;

class LeafEntity extends AbstractEntity implements LeafEntityInterface{
	protected $content;

	public function __construct($content=null,$type=TYPETEXT,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setContent($content);
	}

	public function getContent(){
		$this->fetch();

		return $this->content;
	}

	public function setContent($content){
		$this->content=$content;
	}

	public function isFetched(){
		return $this->content!==null;
	}

	public function fetch(){
		if($this->isFetched()){
			return;
		}

		throw new MimeException('Can\'t fetch a simple LeafEntity. Please extend this class to provide some fetch logic.');
	}

	public function getTextEntities($fetchNow=false){
		if($this->type==TYPETEXT&&!$this instanceof AttachmentInterface){
			if($fetchNow){
				$this->fetch();
			}

			return [$this];
		}else{
			return [];
		}
	}

	public function getAttachments($name=null,$fetchNow=false){
		if($this instanceof AttachmentInterface&&($name===null||$name==$this->getFileName())){
			if($fetchNow){
				$this->fetch();
			}

			return [$this];
		}else{
			return [];
		}
	}

	public function toString(array $envelope=[]){
		return imap_mail_compose($envelope,$this->getBodies());
	}

	protected function getBodies(){
		$body=$this->getBodyWithoutContent();
		$content=(string)$this->getContent();

		switch($this->encoding){
			//case ENC7BIT:
				//nothing to do (content is plain valid ascii)
			//case ENC8BIT:
				//nothing to do (content is plain string)
				//note that imap_mail_compose will turn it to quoted printable
			//case ENCBINARY:
				//nothing to do (content is plain binary)
				//note that imap_mail_compose will turn it to base64.
			case ENCBASE64:
				//turn content to base64 (yes, imap_binary does base64 conversion
				//despite the name is not really meaningful)
				$content=imap_binary($content);
				break;
			case ENCQUOTEDPRINTABLE:
				//turn content to quoted printable (yes, imap_8bit does quoted
				//printable conversion despite the name is not really meaningful)
				$content=imap_8bit($content);
				break;
		}

		$body['contents.data']=$content;

		return [$body];
	}
}
