<?php

namespace Imap\Mime\Attachment;

use Imap\Mime\MimeException;

class StreamURIAttachment extends Attachment implements StreamURIAttachmentInterface{
	protected $streamURI;
	protected $context;

	public function __construct($streamURI,$fileName=null,$context=null,$fetchNow=false,$type=TYPEAPPLICATION,$subType=null,array $typeParameters=[],$disposition=AttachmentInterface::ATTACHMENT_DISPOSITION,array $dispositionParameters=[],$encoding=ENCBINARY,$charset=null,$id=null,$description=null){
		if($fileName===null){
			$fileName=basename($streamURI);
		}

		parent::__construct(null,$fileName,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->streamURI=$streamURI;
		$this->context=$context;

		if($fetchNow){
			$this->fetch();
		}
	}

	public function getStreamURI(){
		return $this->streamURI;
	}

	public function getContext(){
		return $this->context;
	}

	public function fetch(){
		if($this->isFetched()){
			return;
		}

		$content=file_get_contents($this->streamURI,false,$this->context);

		if($content===false){
			throw new MimeException(sprintf('Failed to get "%s" content.',$this->streamURI));
		}

		$this->setContent($content);
	}
}
