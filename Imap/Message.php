<?php

namespace Imap;

class Message implements MessageInterface{
	protected $mailbox;
	protected $id;

	protected static $flagVariableNames=[
		MessageInterface::RECENT_FLAG=>'Recent',
		MessageInterface::SEEN_FLAG=>'Seen',
		MessageInterface::FLAGGED_FLAG=>'Flagged',
		MessageInterface::ANSWERED_FLAG=>'Answered',
		MessageInterface::DELETED_FLAG=>'Deleted',
		MessageInterface::DRAFT_FLAG=>'Draft'
	];

	public function __construct(MailboxInterface $mailbox,$id){
		$this->mailbox=$mailbox;
		$this->id=$id;
	}

	public function getMailbox(){
		return $this->mailbox;
	}

	public function getId(){
		return $this->id;
	}

	public function getMessageNumber(){
		$messageNumber=imap_msgno($this->mailbox->getResource(),$this->id);

		if($messageNumber===false){
			throw new ImapException(sprintf('Failed to retrieve message number for message "%s" in mailbox "%s"',$this,$this->mailbox));
		}

		return $messageNumber;
	}

	public function getHeaders(){
		$headers=imap_headerinfo($this->mailbox->getResource(),$this->getMessageNumber());
		//fix for the seen flag (unseen flag is given instead)
		$headers->Seen=$headers->Unseen==' '?'S':' ';

		if($headers===false){
			throw new ImapException(sprintf('Failed to retrieve headers for message "%s" in mailbox "%s"',$this,$this->mailbox));
		}

		return $headers;
	}

	public function getEnvelope($removeMessageId=true){
		$headers=$this->getHeaders();

		$envelope=['custom_headers'=>[]];

		$copiedProperties=[
			'remail',
			'date',
			'subject'
		];

		if(!$removeMessageId){
			$copiedProperties[]='message_id';
		}

		foreach($copiedProperties as $propertyName){
			if(property_exists($headers,$propertyName)){
				$envelope[$propertyName]=$headers->$propertyName;
			}
		}

		$copiedAddresses=[
			'return_path',
			'from',
			'reply_to',
			'in_reply_to',
			'to',
			'cc',
			'bcc',
		];

		foreach($copiedAddresses as $propertyName){
			if(!property_exists($headers,$propertyName)){
				continue;
			}

			if(is_string($headers->$propertyName)){
				$envelope[$propertyName]=$headers->$propertyName;

				continue;
			}

			$envelope[$propertyName]=[];

			foreach($headers->$propertyName as $mailAddress){
				$personal=property_exists($mailAddress,'personal')?$mailAddress->personal:'';
				$envelope[$propertyName][]=imap_rfc822_write_address($mailAddress->mailbox,$mailAddress->host,$personal);
			}

			$envelope[$propertyName]=implode(', ',$envelope[$propertyName]);
		}

		return $envelope;
	}

	public function getRawHeaders(){
		$rawHeaders=imap_fetchheader($this->mailbox->getResource(),$this->id,FT_UID);

		if($rawHeaders===false){
			throw new ImapException(sprintf('Failed to retrieve raw headers for message "%s" in mailbox "%s"',$this,$this->mailbox));
		}

		return $rawHeaders;
	}

	public function getRawBody(){
		$rawBody=imap_body($this->mailbox->getResource(),$this->id,FT_UID);

		if($rawBody===false){
			throw new ImapException(sprintf('Failed to retrieve raw body for message "%s" in mailbox "%s"',$this,$this->mailbox));
		}

		return $rawBody;
	}

	public function getFlags(){
		$headers=$this->getHeaders();
		$flags=0;

		foreach(static::$flagVariableNames as $flag=>$flagVariableName){
			if($headers->$flagVariableName!=' '){
				$flags|=$flag;
			}
		}

		return $flags;
	}

	public function setFlags($flags,$eraseOthers=false){
		if($eraseOthers){
			$oldFlags=$this->getFlags()&(~MessageInterface::RECENT_FLAG);

			$differentFlags=$oldFlags^$flags;
			$clearedFlags=$differentFlags&$oldFlags;
			$flags&=$differentFlags;

			if($clearedFlags!=0){
				if(!imap_clearflag_full($this->mailbox->getResource(),$this->id,$this->flagsToString($clearedFlags),ST_UID)){
					throw new ImapException(sprintf('Failed to clear flags for message "%s" in mailbox "%s"',$this,$this->mailbox));
				}
			}
		}

		if($flags!=0){
			if(!imap_setflag_full($this->mailbox->getResource(),$this->id,$this->flagsToString($flags),ST_UID)){
				throw new ImapException(sprintf('Failed to set flags for message "%s" in mailbox "%s"',$this,$this->mailbox));
			}
		}
	}

	public function delete(){
		$resource=$this->mailbox->getResource();

		if(!imap_delete($resource,$this->id,FT_UID)||!imap_expunge($resource)){
			throw new ImapException(sprintf('Failed to delete message "%s" in mailbox "%s"',$this,$this->mailbox));
		}

		$this->mailbox->notifyDeletedMessage($this->id);
	}

	public function move(MailboxPathInterface $fullMailboxPath){
		if($fullMailboxPath->equals($this->mailbox->getPath())){
			return;
		}

		$topMailbox=$this->mailbox->getImap()->getTopMailbox();
		$newMailbox=$topMailbox->getMailbox($fullMailboxPath);
		$newId=$newMailbox->getNextMessageId();

		$resource=$this->mailbox->getResource();

		if(!imap_mail_move($resource,$this->id,$fullMailboxPath->escape(),CP_UID)||!imap_expunge($resource)){
			throw new ImapException(sprintf('Failed to move message "%s" from "%s" to "%s"',$this,$this->mailbox,$newMailbox));
		}

		$this->mailbox->notifyMovedOutMessage($this->id);

		$this->id=$newId;
		$this->mailbox=$newMailbox;

		$this->mailbox->notifyMovedInMessage($this);
	}

	public function copy(MailboxPathInterface $fullMailboxPath){
		if($fullMailboxPath->equals($this->mailbox->getPath())){
			return;
		}

		$topMailbox=$this->mailbox->getImap()->getTopMailbox();
		$copyMailbox=$topMailbox->getMailbox($fullMailboxPath);
		//do not remove this line, this ensure that the copy mailbox exists.
		$copyId=$copyMailbox->getNextMessageId();

		if(!imap_mail_copy($this->mailbox->getResource(),$this->id,$fullMailboxPath->escape(),CP_UID)){
			throw new ImapException(sprintf('Failed to copy message "%s" from "%s" to "%s"',$this,$this->mailbox,$copyMailbox));
		}

		$copiedMessage=$this->mailbox->getImap()->getFactory()->createMessage($copyMailbox,$copyId);

		return $copyMailbox->notifyCopiedMessage($copiedMessage);
	}

	public function flagsToString($flags){
		if($flags&MessageInterface::RECENT_FLAG){
			throw new ImapException('The imap protocol won\'t allow you to set the recent flag.',false);
		}

		$stringFlagArray=[];

		foreach(static::$flagVariableNames as $flag=>$flagVariableName){
			if($flags&$flag){
				$stringFlagArray[]='\\'.$flagVariableName;
			}
		}

		return implode(' ',$stringFlagArray);
	}

	public function __toString(){
		return (string)$this->id;
	}
}
