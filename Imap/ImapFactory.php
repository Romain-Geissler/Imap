<?php

namespace Imap;

use Imap\Mime\EntityContainer;
use Imap\Mime\ImapLeafEntity;
use Imap\Mime\Attachment\ImapAttachment;
use Imap\Mime\Attachment\AttachmentInterface;

class ImapFactory implements ImapFactoryInterface{
	public function createPath(ImapInterface $imap,$namePath=null){
		return new MailboxPath($imap,$namePath);
	}

	public function createMailBox(ImapInterface $imap,MailboxInterface $parent=null,$name=null){
		return new Mailbox($imap,$parent,$name);
	}

	public function createMessage(MailboxInterface $mailbox,$id){
		return new Message($mailbox,$id);
	}

	public function createEntityContainer(array $children=[],$fetchNow=false,$type=TYPEMULTIPART,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		return new EntityContainer($children,$fetchNow,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);
	}

	public function createImapLeafEntity(MessageInterface $message,$sectionName,$fetchNow=false,$type=TYPETEXT,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=ENCBINARY,$charset=null,$id=null,$description=null){
		return new ImapLeafEntity($message,$sectionName,$fetchNow,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);
	}

	public function createImapAttachment(MessageInterface $message,$sectionName,$fileName,$fetchNow=false,$type=TYPEAPPLICATION,$subType=null,array $typeParameters=[],$disposition=AttachmentInterface::ATTACHMENT_DISPOSITION,array $dispositionParameters=[],$encoding=ENCBINARY,$charset=null,$id=null,$description=null){
		return new ImapAttachment($message,$sectionName,$fileName,$fetchNow,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);
	}
}
