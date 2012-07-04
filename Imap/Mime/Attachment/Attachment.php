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
		$dispositionParameters=$this->getDispositionParameters();

		if(array_key_exists('filename',$dispositionParameters)){
			return basename($dispositionParameters['filename']);
			//use basename here to avoid hand crafted mails with a name
			//such has '../../../etc/shadow', resulting in a unexpected
			//file overwrite. This can happen if the oponent knows in which
			//directory you will save this attachment.
		}else{
			return null;
		}
	}

	public function setFileName($fileName){
		$dispositionParameters=$this->getDispositionParameters();

		$dispositionParameters=array_merge($dispositionParameters,['filename'=>$fileName]);

		$this->setDispositionParameters($dispositionParameters);
	}

	public function toFile($path,$pathIsParentDirectoryPath=true){
		if($pathIsParentDirectoryPath){
			$path=$path.DIRECTORY_SEPARATOR.basename($this->getFileName());
		}

		if(file_put_contents($path,$this->getContent())===false){
			throw new MimeException(sprintf('Failed to save attachment at path "%s".',$path));
		}
	}
}
