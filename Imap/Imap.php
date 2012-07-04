<?php

namespace Imap;

class Imap implements ImapInterface{
	const DEFAULT_PORT=143;
	const DEFAULT_MAX_RETRY_COUNT=3;
	const DEFAULT_TOP_MAILBOX_NAME='INBOX';

	protected $host;
	protected $port;
	protected $useSSL;
	protected $userName;
	protected $password;
	protected $maxRetryCount;
	protected $topMailboxName;

	protected $resource;
	protected $serverSpecification;
	protected $currentFullMailboxServerPath;
	protected $delimiterCharacter;
	protected $topMailbox;

	public function __construct($host,$port=self::DEFAULT_PORT,$useSSL=true,$userName,$password,$maxRetryCount=self::DEFAULT_MAX_RETRY_COUNT,$topMailboxName=self::DEFAULT_TOP_MAILBOX_NAME,ImapFactoryInterface $factory=null){
		$this->host=$host;
		$this->port=$port;
		$this->useSSL=$useSSL;
		$this->userName=$userName;
		$this->password=$password;
		$this->maxRetryCount=$this->maxRetryCount;
		$this->topMailboxName=$topMailboxName;
		$this->factory=$factory===null?new ImapFactory():$factory;

		$this->resource=null;
		$this->serverSpecification=sprintf('{%s:%s%s}',$this->host,$this->port,$this->useSSL?'/ssl/novalidate-cert':'');
		$this->currentFullMailboxServerPath=null;
		$this->delimiterCharacter=null;
		$this->topMailbox=$this->factory->createMailBox($this);
	}

	public function __destruct(){
		$this->disconnect();
	}

	public function connect(MailboxPathInterface $mailboxPath=null){
		if($this->connectionIsAlive()){
			$this->reconnect($mailboxPath);

			return;
		}else if($this->resource!==null){
			$this->topMailbox->clear();
		}

		if($mailboxPath===null){
			$mailboxPath=$this->getPath();
		}

		$this->currentFullMailboxServerPath=null;
		$fullMailboxServerPath=$this->computeFullMailboxServerPath($mailboxPath);

		if(($resource=imap_open($fullMailboxServerPath,$this->userName,$this->password,CL_EXPUNGE,$this->maxRetryCount))===false){
			throw new ImapException(sprintf('Failed to connect to "%s", SSL: "%s", User Name: "%s", Max Retry Count: "%s"',$fullMailboxServerPath,$this->userName,$this->maxRetryCount));
		}

		$this->resource=$resource;
		$this->currentFullMailboxServerPath=$fullMailboxServerPath;
	}

	public function disconnect(){
		$this->topMailbox->clear();

		$this->currentFullMailboxServerPath=null;

		if($this->connectionIsAlive()){
			imap_close($this->resource);
		}

		$this->resource=null;
	}

	public function flushDelete(){
		if(!$this->connectionIsAlive()){
			return;
		}

		imap_expunge($this->resource);
	}

	public function getPath($namePath=null){
		return $this->factory->createPath($this,$namePath);
	}

	public function computeFullMailboxServerPath(MailboxPathInterface $mailboxPath){
		return $this->serverSpecification.$mailboxPath->escape();
	}

	public function computeFullMailboxPath($fullMailboxServerPath){
		$path=$this->getPath();

		$path->unescape(substr($fullMailboxServerPath,strlen($this->serverSpecification)));

		return $path;
	}

	public function getHost(){
		return $this->host;
	}

	public function getPort(){
		return $this->port;
	}

	public function getUseSSL(){
		return $this->useSSL;
	}

	public function getUserName(){
		return $this->userName;
	}

	public function getPassword(){
		return $this->password;
	}

	public function getMaxRetryCount(){
		return $this->maxRetryCount;
	}

	public function getTopMailboxName(){
		return $this->topMailboxName;
	}

	public function getFactory(){
		return $this->factory;
	}

	public function setFactory(ImapFactoryInterface $factory){
		$this->factory=$factory;
	}

	public function getResource(MailboxPathInterface $mailboxPath=null){
		$this->connect($mailboxPath);

		return $this->resource;
	}

	public function getServerSpecification(){
		return $this->serverSpecification;
	}

	public function getCurrentFullMailboxServerPath(){
		return $this->currentFullMailboxServerPath;
	}

	public function getDelimiterCharacter(){
		if($this->delimiterCharacter===null){
			$mailboxPath=$this->getPath();

			if(($list=imap_getmailboxes($this->getResource(),$this->serverSpecification,$mailboxPath->escape()))===false){
				throw new ImapException(sprintf('Failed to retrieve delimiter character for base mailbox "%s"',$mailboxPath));
			}

			$this->delimiterCharacter=$list[0]->delimiter;
		}

		return $this->delimiterCharacter;
	}

	public function getTopMailbox(){
		return $this->topMailbox;
	}

	public function clearMessageCache(){
		if($this->connectionIsAlive()){
			imap_gc($this->resource,IMAP_GC_ELT|IMAP_GC_ENV|IMAP_GC_TEXTS);
		}
	}

	protected function connectionIsAlive(){
		return $this->resource!==null&&imap_ping($this->resource);
	}

	//call this only if the previous connection is still alive
	protected function reconnect(MailboxPathInterface $mailboxPath=null){
		if($mailboxPath===null){
			return;
		}

		$fullMailboxServerPath=$this->computeFullMailboxServerPath($mailboxPath);

		if($fullMailboxServerPath==$this->currentFullMailboxServerPath){
			return;
		}

		if(!imap_reopen($this->resource,$fullMailboxServerPath,OP_EXPUNGE|CL_EXPUNGE,$this->maxRetryCount)){
			throw new ImapException(sprintf('Failed to reconnect to "%s", SSL: "%s", User Name: "%s", Max Retry Count: "%s"',$fullMailboxServerPath,$this->userName,$this->maxRetryCount));
		}

		$this->currentFullMailboxServerPath=$fullMailboxServerPath;
	}
}
