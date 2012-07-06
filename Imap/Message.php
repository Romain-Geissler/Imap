<?php

namespace Imap;

use Imap\Mime\Attachment\AttachmentInterface;
use Imap\Mime\Utils;
use Imap\Mime\SMimeSignedEntityInterface;
use Imap\Mime\SMimeEncryptedEntityInterface;

class Message implements MessageInterface{
	protected $mailbox;
	protected $id;
	protected $topMimeEntity;

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
		$this->topMimeEntity=null;
	}

	public function getMailbox(){
		return $this->mailbox;
	}

	public function getId(){
		return $this->id;
	}

	public function getTopMimeEntity($fetchNow=false){
		if($this->topMimeEntity===null){
			if(($structure=imap_fetchstructure($this->mailbox->getResource(),$this->id,FT_UID))===false){
				throw new ImapException(sprintf('Failed to fetch message structure for message "%s" in mailbox "%s"',$this,$this->mailbox));
			}

			$this->topMimeEntity=$this->structureToMimeEntity($structure,$fetchNow);
		}

		return $this->topMimeEntity;
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

	public function clear(){
		$this->topMimeEntity=null;
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

	public function fetchRawHeaders($sectionName=null){
		if($sectionName===null){
			return Utils::filterMimeHeaders($this->getRawHeaders(),true);
		}else{
			if(($rawHeaders=imap_fetchmime($this->mailbox->getResource(),$this->id,$sectionName,FT_UID))===false){
				throw new ImapException(sprintf('Failed to fetch headers for section "%s" in message "%s" in mailbox "%s"',$sectionName,$this,$this->mailbox));
			}

			return Utils::filterMimeHeaders($rawHeaders,true);
		}
	}

	public function fetchRawSectionBody($sectionName=null){
		if($sectionName===null){
			return $this->getRawBody();
		}else{
			if(($rawBody=imap_fetchbody($this->mailbox->getResource(),$this->id,$sectionName,FT_UID))===false){
				throw new ImapException(sprintf('Failed to fetch body for section "%s" in message "%s" in mailbox "%s"',$sectionName,$this,$this->mailbox));
			}

			return $rawBody;
		}
	}

	public function fetchSectionBody($sectionName){
		$resource=$this->mailbox->getResource();

		if(($structure=imap_bodystruct($resource,$this->getMessageNumber(),$sectionName))===false){
			throw new ImapException(sprintf('Failed to fetch structure for section "%s" in message "%s" in mailbox "%s"',$sectionName,$this,$this->mailbox));
		}

		$rawContent=$this->fetchRawSectionBody($sectionName);

		switch($structure->encoding){
			case ENC7BIT:
			case ENC8BIT:
			case ENCBINARY:
				$content=$rawContent;
				break;
			case ENCBASE64:
				$content=imap_base64($rawContent);
				break;
			case ENCQUOTEDPRINTABLE:
				$content=imap_qprint($rawContent);
				break;
			case OTHER:
			default:
				throw new ImapException(sprintf('Unknown mime encoding "%s"',$structure->encoding),false);
		}

		if($content===false){
			throw new ImapException('Failed to decode mail content');
		}

		return $content;
	}

	public function fetchRawSection($sectionName=null){
		return $this->fetchRawHeaders($sectionName).$this->fetchRawSectionBody($sectionName);
	}

	public function getTextEntities($fetchNow=false){
		return $this->getTopMimeEntity(false)->getTextEntities($fetchNow);
	}

	public function getText(){
		return $this->getTopMimeEntity(false)->getText();
	}

	public function hasAttachments($name=null){
		return $this->getTopMimeEntity(false)->hasAttachments($name);
	}

	public function getAttachments($name=null,$fetchNow=false){
		return $this->getTopMimeEntity(false)->getAttachments($name,$fetchNow);
	}

	public function getSingleAttachmentNamed($name,$fetchNow=false){
		return $this->getTopMimeEntity(false)->getSingleAttachmentNamed($name,$fetchNow);
	}

	public function __toString(){
		return (string)$this->id;
	}

	protected function structureToMimeEntity($structure,$fetchNow,$sectionName=null){
		if($structure->type==TYPEMULTIPART){
			return $this->structureToMimeEntityContainer($structure,$fetchNow,$sectionName);
		}else{
			return $this->structureToLeafMimeEntity($structure,$fetchNow,$sectionName);
		}
	}

	protected function structureToMimeEntityContainer($structure,$fetchNow,$sectionName=null){
		$arguments=$this->getEntityHeaderArguments($structure);
		$children=[];
		$counter=1;

		if($sectionName===null){
			$sectionNamePattern='%2$s';
		}else{
			$sectionNamePattern='%s.%s';
		}

		foreach($structure->parts as $partStructure){
			$children[]=$this->structureToMimeEntity($partStructure,$fetchNow,sprintf($sectionNamePattern,$sectionName,$counter++));
		}

		if($structure->ifsubtype){
			$subtype=strtolower($structure->subtype);

			if($subtype==SMimeSignedEntityInterface::SUBTYPE){
				array_unshift($arguments,$this,$sectionName,$children[0],$fetchNow);

				return call_user_func_array([$this->mailbox->getImap()->getFactory(),'createSMimeSignedEntity'],$arguments);
			}
		}

		array_unshift($arguments,$children,$fetchNow);

		return call_user_func_array([$this->mailbox->getImap()->getFactory(),'createEntityContainer'],$arguments);
	}

	protected function structureToLeafMimeEntity($structure,$fetchNow,$sectionName=null){
		$arguments=$this->getEntityHeaderArguments($structure);

		if($sectionName===null){
			$sectionName='1';
		}

		if($structure->ifsubtype){
			$subtype=strtolower($structure->subtype);
			$smimeType=$this->getStructureParameter($structure,'parameters',strtolower(SMimeEncryptedEntityInterface::SMIME_TYPE_ATTRIBUTE_NAME),null);

			if($subtype==SMimeEncryptedEntityInterface::SUBTYPE&&$smimeType===SMimeEncryptedEntityInterface::SMIME_TYPE){
				array_unshift($arguments,$this,$sectionName,null,$fetchNow);

				return call_user_func_array([$this->mailbox->getImap()->getFactory(),'createSMimeEncryptedEntity'],$arguments);
			}
		}

		if($structure->ifdisposition&&($structure->disposition==AttachmentInterface::ATTACHMENT_DISPOSITION||AttachmentInterface::INLINE_DISPOSITION)){
			$fileName=$this->getStructureParameter($structure,'dparameters','filename',null);
			array_unshift($arguments,$this,$sectionName,$fileName,$fetchNow);

			return call_user_func_array([$this->mailbox->getImap()->getFactory(),'createImapAttachment'],$arguments);
		}else{
			array_unshift($arguments,$this,$sectionName,$fetchNow);

			return call_user_func_array([$this->mailbox->getImap()->getFactory(),'createImapLeafEntity'],$arguments);
		}
	}

	protected function getEntityHeaderArguments($structure){
		return [
			$structure->type,
			$structure->ifsubtype?strtolower($structure->subtype):null,
			$structure->ifparameters?$this->objectParametersToArrayParameters($structure->parameters):[],
			$structure->ifdisposition?$structure->disposition:null,
			$structure->ifdparameters?$this->objectParametersToArrayParameters($structure->dparameters):[],
			$structure->encoding,
			$this->getStructureParameter($structure,'parameters','charset',null),
			$structure->ifid?$structure->id:null,
			$structure->ifdescription?$structure->description:null
		];
	}

	protected function objectParametersToArrayParameters(array $objectParameters){
		$arrayParameters=[];

		foreach($objectParameters as $objectParameter){
			$arrayParameters[strtolower($objectParameter->attribute)]=$objectParameter->value;
		}

		return $arrayParameters;
	}

	protected function getStructureParameter($structure,$parameterArrayName,$attributeName,$defaultValue){
		$attributeName=strtolower($attributeName);

		if(!$structure->{'if'.$parameterArrayName}){
			return $defaultValue;
		}

		foreach($structure->$parameterArrayName as $parameter){
			if(strtolower($parameter->attribute)==$attributeName){
				return $parameter->value;
			}
		}

		return $defaultValue;
	}
}
