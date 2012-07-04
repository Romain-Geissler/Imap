<?php

namespace Imap;

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
}
