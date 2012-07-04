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

		if(array_key_exists('BOUNDARY',$this->typeParameters)){
			$boundary=$this->typeParameters['BOUNDARY'];
		}else{
			$boundary=$this->findBoundaryForParts($childrenToString);
		}

		$headers=$this->toHeaderString($envelope,$boundary);
		$body=$this->toBodyString($childrenToString,$boundary);

		return $headers."\r\n\r\n".$body;
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

		return substr($headers,0,strpos($headers,"\r\n\r\n"));
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

		$body['type.parameters']['BOUNDARY']=$boundary;

		return [
			$body,
			[
				'type'=>TYPETEXT,
				'contents.data'=>'dummy text'
			]
		];
	}
}
