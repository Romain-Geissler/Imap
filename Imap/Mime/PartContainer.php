<?php

namespace Imap\Mime;

class PartContainer extends AbstractEntity{
	public function __construct(array $content=[],$type=TYPEMULTIPART,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setContent($content);
	}

	public function getContent(){
		return $this->content;
	}

	public function setContent(array $content){
		$this->content=$content;
	}

	public function prependContent(Entity $entity,Entity $beforeEntity=null){
		if($beforeEntity===null){
			array_unshift($this->content,$entity);
		}else{
			array_splice($this->content,$this->getContentOffsetFor($beforeEntity),0,$entity);
		}
	}

	public function appendContent(Entity $entity,Entity $afterEntity=null){
		if($afterEntity===null){
			$this->content[]=$entity;
		}else{
			array_splice($this->content,$this->getContentOffsetFor($beforeEntity)+1,0,$entity);
		}
	}

	public function removeContent(Entity $entity){
		array_splice($this->content,$this->getContentOffsetFor($entity),1);
	}

	public function getBodies($boundary){
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

	public function toString(array $envelope=[]){
		//sadly we can't fully use imap_mail_compose here because support for
		//multipart sections is partial. One can have one multipart section
		//with only simple contained section (ie you cannot not nest multipart
		//sections in a multipart section. If you look at the Imap ext source code,
		//you'll see that the type TYPEMULTIPART is forbidden (the type won't be
		//set and thus use the default (which is TYPETEXT).

		$stringParts=[];

		foreach($this->content as $entity){
			$stringParts[]=$entity->toString();
		}

		if(array_key_exists('BOUNDARY',$this->typeParameters)){
			$boundary=$this->typeParameters['BOUNDARY'];
		}else{
			$boundary=$this->findBoundaryForParts($stringParts);
		}

		$headers=$this->toHeaderString($envelope,$boundary);
		$body=$this->toBodyString($stringParts,$boundary);

		return $headers."\r\n\r\n".$body;
	}

	protected function getContentOffsetFor(Entity $lookForEntity){
		foreach($this->content as $offset=>$entity){
			if($entity===$lookForEntity){
				return $offset;
			}
		}

		throw new MimeException('Entity not found.');
	}

	protected function findBoundaryForParts(array $stringParts){
		while(true){
			$boundary=sprintf('PHP-Imap=%s',uniqid('',true));
			$realBoundary=sprintf('--%s',$boundary);

			foreach($stringParts as $stringPart){
				if(strpos($stringPart,$realBoundary)!==false){
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

	protected function toBodyString(array $stringParts,$boundary){
		$body='';
		$boundary=sprintf('--%s',$boundary);

		foreach($stringParts as $stringPart){
			$body.=$boundary."\r\n".$stringPart;
		}

		$body.=$boundary.'--'."\r\n";

		return $body;
	}
}
