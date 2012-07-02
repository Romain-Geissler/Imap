<?php

namespace Imap;

class Message{
	const RECENT_FLAG=1;
	const SEEN_FLAG=2;
	const FLAGGED_FLAG=4;
	const ANSWERED_FLAG=8;
	const DELETED_FLAG=16;
	const DRAFT_FLAG=32;

	protected $mailbox;
	protected $id;

	protected static $flagVariableNames=[
		self::RECENT_FLAG=>'Recent',
		self::SEEN_FLAG=>'Seen',
		self::FLAGGED_FLAG=>'Flagged',
		self::ANSWERED_FLAG=>'Answered',
		self::DELETED_FLAG=>'Deleted',
		self::DRAFT_FLAG=>'Draft'
	];

	public function __construct(Mailbox $mailbox,$id){
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
			$oldFlags=$this->getFlags()&(~static::RECENT_FLAG);

			$differentFlags=$oldFlags^$flags;
			$clearedFlags=$differentFlags&$oldFlags;
			$flags&=$differentFlags;

			if($clearedFlags!=0){
				if(!imap_clearflag_full($this->mailbox->getResource(),$this->id,static::flagsToString($clearedFlags),ST_UID)){
					throw new ImapException(sprintf('Failed to clear flags for message "%s" in mailbox "%s"',$this,$this->mailbox));
				}
			}
		}

		if($flags!=0){
			if(!imap_setflag_full($this->mailbox->getResource(),$this->id,static::flagsToString($flags),ST_UID)){
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

	public function move(MailboxPath $fullMailboxPath){
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

		$this->mailbox->notifyMovedMessage($newMailbox,$this->id,$newId);

		$this->id=$newId;
		$this->mailbox=$newMailbox;
	}

	public function copy(MailboxPath $fullMailboxPath){
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

		return $copyMailbox->notifyCopiedMessage($copyId);
	}

	public static function flagsToString($flags){
		if($flags&static::RECENT_FLAG){
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
