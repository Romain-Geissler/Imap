<?php

namespace Imap\Mime;

use Imap\MessageInterface;

class ImapSMimeSignedEntity extends SMimeSignedEntity implements ImapSMimeEntityInterface{
	use ImapSMimeEntityTrait;

	public function __construct(MessageInterface $message,$sectionName,EntityInterface $contentEntity,$fetchNow=false,$type=TYPEMULTIPART,$subType=SMimeSignedEntityInterface::SUBTYPE,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($contentEntity,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setMessage($message);
		$this->setSectionName($sectionName);

		if($fetchNow){
			$this->fetch();
		}
	}
}
