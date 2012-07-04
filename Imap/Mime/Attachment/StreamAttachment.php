<?php

namespace Imap\Mime\Attachment;

use Imap\Mime\MimeException;

class StreamAttachment extends Attachment implements StreamAttachmentInterface{
	protected $stream;

	public function __construct($stream,$fileName=null,$fetchNow=false,$type=TYPEAPPLICATION,$subType=null,array $typeParameters=[],$disposition=AttachmentInterface::ATTACHMENT_DISPOSITION,array $dispositionParameters=[],$encoding=ENCBINARY,$charset=null,$id=null,$description=null){
		if($fileName===null){
			if(($metadata=stream_get_meta_data())===false){
				throw new MimeException('Failed to get stream metadata.');
			}

			$fileName=basename($metadata['uri']);
		}

		parent::__construct(null,$fileName,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->stream=$stream;

		if($fetchNow){
			$this->fetch();
		}
	}

	public function getStream(){
		return $this->stream;
	}

	public function fetch(){
		if($this->isFetched()){
			return;
		}

		$content=stream_get_contents($this->stream);

		if($content===false){
			throw new MimeException(sprintf('Failed to get stream content.',$this->stream));
		}

		$this->setContent($content);
	}
}
