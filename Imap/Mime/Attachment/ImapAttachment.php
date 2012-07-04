<?php

namespace Imap\Mime\Attachment;

use Imap\Mime\ImapLeafEntityInterface;
use Imap\Mime\ImapLeafEntityTrait;
use Imap\MessageInterface;

class ImapAttachment extends Attachment implements ImapLeafEntityInterface{
	use ImapLeafEntityTrait;

	public function __construct(MessageInterface $message,$sectionName,$fileName,$fetchNow=false,$type=TYPEAPPLICATION,$subType=null,array $typeParameters=[],$disposition=AttachmentInterface::ATTACHMENT_DISPOSITION,array $dispositionParameters=[],$encoding=ENCBINARY,$charset=null,$id=null,$description=null){
		parent::__construct(null,$fileName,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setMessage($message);
		$this->setSectionName($sectionName);

		if($fetchNow){
			$this->fetch();
		}
	}
}
