<?php

namespace Imap\Mime;

class EntityContainer extends AbstractEntity implements EntityContainerInterface{
	protected $children;

	public function __construct(array $children=[],$fetchNow=false,$type=TYPEMULTIPART,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setChildren($children,$fetchNow);
	}

	public function getChildren(){
		return $this->children;
	}

	public function setChildren(array $children,$fetchNow=false){
		$this->children=$children;

		if($fetchNow){
			$this->fetch();
		}
	}

	public function prependChild(EntityInterface $entity,EntityInterface $beforeEntity=null){
		if($beforeEntity===null){
			array_unshift($this->children,$entity);
		}else{
			array_splice($this->children,$this->getChildOffsetFor($beforeEntity),0,$entity);
		}
	}

	public function appendChild(EntityInterface $entity,EntityInterface $afterEntity=null){
		if($afterEntity===null){
			$this->children[]=$entity;
		}else{
			array_splice($this->children,$this->getChildOffsetFor($beforeEntity)+1,0,$entity);
		}
	}

	public function removeChild(EntityInterface $entity){
		array_splice($this->children,$this->getChildOffsetFor($entity),1);
	}

	public function isFetched(){
		foreach($this->children as $child){
			if(!$child->isFetched()){
				return false;
			}
		}

		return true;
	}

	public function fetch(){
		foreach($this->children as $child){
			$child->fetch();
		}
	}

	public function getTextEntities($fetchNow=false){
		$textEntities=[];

		foreach($this->children as $child){
			foreach($child->getTextEntities($fetchNow) as $childTextEntity){
				$textEntities[]=$childTextEntity;
			}
		}

		return $textEntities;
	}

	public function getAttachments($name=null,$fetchNow=false){
		$attachments=[];

		foreach($this->children as $child){
			foreach($child->getAttachments($name,$fetchNow) as $childAttachments){
				$attachments[]=$childAttachments;
			}
		}

		return $attachments;
	}

	public function toString(array $envelope=[]){
		//sadly we can't fully use imap_mail_compose here because support for
		//multipart sections is partial. One can have one multipart section
		//with only simple contained section (ie you cannot not nest multipart
		//sections in a multipart section. If you look at the Imap ext source code,
		//you'll see that the type TYPEMULTIPART is forbidden (the type won't be
		//set and thus use the default (which is TYPETEXT).

		$childrenToString=[];

		foreach($this->children as $child){
			$childrenToString[]=$child->toString();
		}

		if($this->hasTypeParameter('boundary')){
			$boundary=$this->getTypeParameter('boundary');
		}else{
			$boundary=$this->findBoundaryForParts($childrenToString);
		}

		$headers=$this->toHeaderString($envelope,$boundary);
		$body=$this->toBodyString($childrenToString,$boundary);

		return $headers."\r\n".$body;
	}

	protected function getChildOffsetFor(EntityInterface $lookForEntity){
		foreach($this->children as $offset=>$child){
			if($child===$lookForEntity){
				return $offset;
			}
		}

		throw new MimeException('Entity not found.');
	}

	protected function findBoundaryForParts(array $childrenToString){
		while(true){
			$boundary=sprintf('PHP-Imap=%s',uniqid('',true));
			$realBoundary=sprintf('--%s',$boundary);

			foreach($childrenToString as $childToString){
				if(strpos($childToString,$realBoundary)!==false){
					continue 2;
				}
			}

			return $boundary;
		}
	}

	protected function toHeaderString(array $envelope,$boundary){
		$headers=imap_mail_compose($envelope,$this->getBodies($boundary));
		$matches=[];

		if(!preg_match('/^\r\n/m',$headers,$matches,PREG_OFFSET_CAPTURE)){
			throw new MimeException('Malformed header.');
		}

		return substr($headers,0,$matches[0][1]);
	}

	protected function toBodyString(array $childrenToString,$boundary){
		$body='';
		$boundary=sprintf('--%s',$boundary);

		foreach($childrenToString as $childToString){
			$body.=$boundary."\r\n".$childToString;
		}

		$body.=$boundary.'--'."\r\n";

		return $body;
	}

	protected function getBodies($boundary){
		$body=$this->getBodyWithoutContent();

		unset($body['type.parameters']['boundary']);
		$body['type.parameters']['BOUNDARY']=$boundary;
		//make sure that BOUNDARY is written in upper case here.

		return [
			$body,
			[
				'type'=>TYPETEXT,
				'contents.data'=>'dummy text'
			]
		];
	}
}
