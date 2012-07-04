<?php

namespace Imap\Mime\Attachment;

use Imap\Mime\LeafEntity;
use Imap\Mime\MimeException;

class Attachment extends LeafEntity implements AttachmentInterface{
	public function __construct($content,$fileName,$type=TYPEAPPLICATION,$subType=null,array $typeParameters=[],$disposition=AttachmentInterface::ATTACHMENT_DISPOSITION,array $dispositionParameters=[],$encoding=ENCBINARY,$charset=null,$id=null,$description=null){
		parent::__construct($content,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setFileName($fileName);
	}

	public function getFileName(){
		if($this->hasDispositionParameter('filename')){
			return $this->getDispositionParameter('filename');
		}else{
			return null;
		}
	}

	public function setFileName($fileName){
		if($fileName===null){
			$this->removeDispositionParameter('filename');
		}else{
			$this->setDispositionParameter('filename',$fileName);
		}
	}

	public function toFile($path,$pathIsParentDirectoryPath=true){
		if($pathIsParentDirectoryPath){
			$path=$path.DIRECTORY_SEPARATOR.basename($this->getFileName());
			//use basename here to avoid hand crafted mails with a name
			//such has '../../../etc/shadow', resulting in a unexpected
			//file overwrite. This can happen if the oponent knows in which
			//directory you will save this attachment.
		}

		if(file_put_contents($path,$this->getContent())===false){
			throw new MimeException(sprintf('Failed to save attachment at path "%s".',$path));
		}
	}
}
