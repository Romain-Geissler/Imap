<?php

namespace Imap;

class Mailbox{
	protected $imap;
	protected $path;
	protected $fetchedMailboxes;

	public function __construct(Imap $imap,Mailbox $parent=null,$name=null){
		$this->imap=$imap;
		$this->parent=$parent;

		if($this->parent===null){
			$this->path=$this->imap->getPath();
		}else{
			$this->path=$this->parent->path->getNameAppended($name);
		}

		$this->fetchedMailboxes=[];
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

		$this->clearFetchedMailboxes();

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
			$fetchedMailbox->clearFetchedMailboxes();
		}

		$this->fetchedMailboxes=[];
	}

	public function __toString(){
		return (string)$this->path;
	}
}
