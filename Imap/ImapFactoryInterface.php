<?php

namespace Imap;

interface ImapFactoryInterface{
	function createPath(ImapInterface $imap,$namePath=null);

	function createMailBox(ImapInterface $imap,MailboxInterface $parent=null,$name=null);

	function createMessage(MailboxInterface $mailbox,$id);
}
