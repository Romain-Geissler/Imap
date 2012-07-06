<?php

namespace Imap\Mime;

use Imap\MessageInterface;

class ImapSMimeEncryptedEntity extends SMimeEncryptedEntity implements ImapSMimeEntityInterface{
	use ImapSMimeEntityTrait;

	public function __construct(MessageInterface $message,$sectionName,EntityInterface $contentEntity=null,$fetchNow=false,$type=TYPEMULTIPART,$subType=SMimeEncryptedEntityInterface::SUBTYPE,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($contentEntity,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setMessage($message);
		$this->setSectionName($sectionName);

		if($fetchNow){
			$this->fetch();
		}
	}
}
