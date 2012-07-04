<?php

namespace Imap\Mime;

use Imap\MessageInterface;

class ImapLeafEntity extends LeafEntity implements ImapLeafEntityInterface{
	use ImapLeafEntityTrait;

	public function __construct(MessageInterface $message,$sectionName,$fetchNow=false,$type=TYPETEXT,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct(null,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setMessage($message);
		$this->setSectionName($sectionName);

		if($fetchNow){
			$this->fetch();
		}
	}
}
