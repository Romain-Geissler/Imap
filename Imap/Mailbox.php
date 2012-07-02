<?php

namespace Imap;

class Mailbox{
	const ASCENDING_SORT=0;
	const DESCENDING_SORT=1;
	const ALL_MESSAGES_CRITERIA='ALL';

	protected $imap;
	protected $path;
	protected $fetchedMailboxes;
	protected $fetchedMessages;

	public function __construct(Imap $imap,Mailbox $parent=null,$name=null){
		$this->imap=$imap;
		$this->parent=$parent;

		if($this->parent===null){
			$this->path=$this->imap->getPath();
		}else{
			$this->path=$this->parent->path->getNameAppended($name);
		}

		$this->fetchedMailboxes=[];
		$this->fetchedMessages=[];
	}

	public function getImap(){
		return $this->imap;
	}

	public function getParent(){
		return $this->parent;
	}

	public function getPath(){
		return clone $this->path;
	}

	public function getName(){
		return $this->path->getLastName();
	}

	public function hasMailbox(MailboxPath $localMailboxPath){
		$fullMailboxPath=$this->path->getAppended($localMailboxPath);
		$escapedMailboxPath=$fullMailboxPath->escape();

		$fullMailboxServerPaths=imap_list($this->getResource(false),$this->imap->getServerSpecification(),$escapedMailboxPath);

		if($fullMailboxServerPaths===false){
			return false;
		}

		if(strpbrk($escapedMailboxPath,'*%')!==false){
			//check for unescapable wildcards: there may be more than one
			//matching result, and these may not be the ones we are looking for.

			foreach($fullMailboxServerPaths as $fullMailboxServerPath){
				if($this->imap->computeFullMailboxPath($fullMailboxServerPath)->equals($fullMailboxPath)){
					return true;
				}
			}

			return false;
		}else{
			return true;
		}
	}

	public function createMailbox(MailboxPath $localMailboxPath){
		$fullMailboxPath=$this->path->getAppended($localMailboxPath);
		$fullMailboxServerPath=$this->imap->computeFullMailboxServerPath($fullMailboxPath);

		if(!imap_createmailbox($this->getResource(false),$fullMailboxServerPath)){
			throw new ImapException(sprintf('Failed to create mailbox "%s"',$fullMailboxServerPath));
		}
	}

	public function getMailbox(MailboxPath $localMailboxPath,$checkExistence=true){
		if(!$localMailboxPath->hasName()){
			return $this;
		}

		$firstName=$localMailboxPath->getFirstName();

		if(!array_key_exists($firstName,$this->fetchedMailboxes)){
			$childrenLocalMailboxPath=$localMailboxPath->getFirst();

			if($checkExistence&&!$this->hasMailbox($childrenLocalMailboxPath)){
				$this->createMailbox($childrenLocalMailboxPath);
			}

			$this->fetchedMailboxes[$firstName]=new static($this->imap,$this,$firstName);
		}

		if($localMailboxPath->hasSubName()){
			return $this->fetchedMailboxes[$firstName]->getMailbox($localMailboxPath->getFirstNameRemoved(),$checkExistence);
		}else{
			return $this->fetchedMailboxes[$firstName];
		}
	}

	public function getMailboxes($deepLookup=false){
		$serverSpecification=$this->imap->getServerSpecification();

		$fullMailboxServerPaths=imap_list($this->getResource(false),$serverSpecification,$this->path->escapeForChildrenLookup($deepLookup));

		if($fullMailboxServerPaths===false){
			return [];
		}

		$mailboxes=[];

		$containsWildcard=strpbrk($this->path->escape(),'*%')!==false;

		foreach($fullMailboxServerPaths as $fullMailboxServerPath){
			$fullMailboxPath=$this->imap->computeFullMailboxPath($fullMailboxServerPath);

			if($containsWildcard){
				//check for unescapable wildcards: there may be more than one
				//matching result, and these may not be the ones we are looking for.

				if(!$this->path->isAncestorOf($fullMailboxPath)){
					continue;
				}
			}

			$mailboxes[]=$this->getMailbox($fullMailboxPath->getDescentDifferentFrom($this->path),false);
		}

		return $mailboxes;
	}

	public function delete($recursive=false){
		$childMailboxes=$this->getMailboxes(false);

		if($recursive){
			foreach($childMailboxes as $childMailbox){
				$childMailbox->delete(true);
			}
		}else{
			if(count($childMailboxes)!=0){
				throw new ImapException(sprintf('Can\'t delete mailbox "%s" as it contains other mailboxes (use recursive delete instead).',$this),false);
			}
		}

		$fullMailboxServerPath=$this->imap->computeFullMailboxServerPath($this->path);

		if(!imap_deletemailbox($this->getResource(false),$fullMailboxServerPath)){
			throw new ImapException(sprintf('Failed to delete mailbox "%s"',$fullMailboxServerPath));
		}

		$this->clear();

		unset($this->parent->fetchedMailboxes[$this->getName()]);
	}

	public function move(MailboxPath $fullMailboxPath){
		if($fullMailboxPath->equals($this->path)){
			return;
		}

		$fullOldMailboxServerPath=$this->imap->computeFullMailboxServerPath($this->path);
		$fullNewMailboxServerPath=$this->imap->computeFullMailboxServerPath($fullMailboxPath);

		if(!imap_renamemailbox($this->getResource(false),$fullOldMailboxServerPath,$fullNewMailboxServerPath)){
			throw new ImapException(sprintf('Failed to rename mailbox from "%s" to "%s"',$fullOldMailboxServerPath,$fullNewMailboxServerPath));
		}

		foreach($this->getFetchedMailboxes(true) as $fetchedMailbox){
			$fetchedMailbox->path=$fullMailboxPath->getAppended($fetchedMailbox->path->getDescentDifferentFrom($this->path));
		}

		unset($this->parent->fetchedMailboxes[$this->getName()]);
		$this->path=$fullMailboxPath;

		$this->parent=$this->imap->getTopMailbox()->getMailbox($fullMailboxPath,false);
		$this->parent->fetchedMailboxes[$this->getName()]=$this;
	}

	public function getFetchedMailboxes($deepLookup=false){
		if(!$deepLookup){
			return $this->fetchedMailboxes;
		}else{
			$fetchedMailboxes=[];

			foreach($this->fetchedMailboxes as $fetchedMailbox){
				$fetchedMailboxes[]=$fetchedMailbox;

				foreach($fetchedMailbox->getFetchedMailboxes(true) as $nestedFetchedMailbox){
					$fetchedMailboxes[]=$nestedFetchedMailbox;
				}
			}

			return $fetchedMailboxes;
		}
	}

	public function clearFetchedMailboxes(){
		foreach($this->fetchedMailboxes as $fetchedMailbox){
			$fetchedMailbox->clear();
		}

		$this->fetchedMailboxes=[];
	}

	public function getMessages($criterias=self::ALL_MESSAGES_CRITERIA){
		imap_errors();
		$messagesIds=imap_search($this->getResource(),$criterias,SE_UID);

		if($messagesIds===false){
			if(imap_last_error()===false){
				return [];
			}else{
				throw new ImapException(sprintf('Failed to get message matching criterias "%s" in mailbox "%s"',$criterias,$this));
			}
		}

		return $this->getMessagesFromIds($messagesIds);
	}

	public function getSortedMessages($sortCriteria,$sortOrder=self::ASCENDING_SORT,$criterias=self::ALL_MESSAGES_CRITERIA){
		imap_errors();
		$messagesIds=imap_sort($this->getResource(),$sortCriteria,$sortOrder,SE_UID|SE_NOPREFETCH,$criterias);

		if($messagesIds===false){
			if(imap_last_error()===false){
				return [];
			}else{
				throw new ImapException(sprintf('Failed to sort message by "%s" (%s) matching criterias "%s" in mailbox "%s"',$sortCriteria,$sortOrder==static::ASCENDING_SORT?'ASC':'DESC',$criterias,$this));
			}
		}

		return $this->getMessagesFromIds($messagesIds);
	}

	public function getResource($moveToThisMailbox=true){
		return $this->imap->getResource($moveToThisMailbox?$this->path:null);
	}

	public function getFetchedMessages(){
		return $this->fetchedMessages;
	}

	public function clearFetchedMessages(){
		$this->fetchedMessages=[];
	}

	public function clear(){
		$this->clearFetchedMessages();
		$this->clearFetchedMailboxes();
	}

	public function notifyDeletedMessage($messageId){
		unset($this->fetchedMessages[$messageId]);
	}

	public function notifyMovedMessage(Mailbox $newMailbox,$oldMessageId,$newMessageId){
		$newMailbox->fetchedMessages[$newMessageId]=$this->fetchedMessages[$oldMessageId];

		unset($this->fetchedMessages[$oldMessageId]);
	}

	public function notifyCopiedMessage($newMessageId){
		return $this->fetchedMessages[$newMessageId]=new Message($this,$newMessageId);
	}

	public function notifyAddedMessage($newMessageId){
		return $this->fetchedMessages[$newMessageId]=new Message($this,$newMessageId);
	}

	public function getNextMessageId(){
		if(($status=imap_status($this->getResource(false),$this->imap->computeFullMailboxServerPath($this->path),SA_UIDNEXT))===false){
			throw new ImapException(sprintf('Failed to get next message id for mailbox "%s"',this));
		}

		return $status->uidnext;
	}

	public function getMessageCount(){
		return imap_num_msg($this->getResource());
	}

	public function getRecentMessageCount(){
		return imap_num_recent($this->getResource());
	}

	public function addMessage($stringMessage,$flags=0){
		$newMessageId=$this->getNextMessageId();

		if(!imap_append($this->getResource(false),$this->imap->computeFullMailboxServerPath($this->path),$stringMessage,Message::flagsToString($flags))){
			throw new ImapException(sprintf('Failed to add new message in mailbox "%s"',$this));
		}

		return $this->notifyAddedMessage($newMessageId);
	}

	public function __toString(){
		return (string)$this->path;
	}

	protected function getMessagesFromIds(array $messagesIds){
		$messages=[];

		foreach($messagesIds as $messageId){
			if(!array_key_exists($messageId,$this->fetchedMessages)){
				$this->fetchedMessages[$messageId]=new Message($this,$messageId);
			}

			$messages[]=$this->fetchedMessages[$messageId];
		}

		return $messages;
	}
}
