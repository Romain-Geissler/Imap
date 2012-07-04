<?php

namespace Imap\Mime;

abstract class AbstractEntity implements EntityInterface{
	protected $type;
	protected $encoding;
	protected $charset;
	protected $typeParameters;
	protected $subType;
	protected $id;
	protected $description;
	protected $disposition;
	protected $dispositionParameters;

	public function __construct($type,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		$this->setType($type);
		$this->setSubType($subType);
		$this->setTypeParameters($typeParameters);
		$this->setDisposition($disposition);
		$this->setDispositionParameters($dispositionParameters);
		$this->setEncoding($encoding);
		$this->setCharset($charset);
		$this->setId($id);
		$this->setDescription($description);
	}

	public function getType(){
		return $this->type;
	}

	public function setType($type){
		$this->type=$type;
	}

	public function getSubType(){
		return $this->subType;
	}

	public function setSubType($subType){
		$this->subType=$subType;
	}

	public function getTypeParameters(){
		return $this->typeParameters;
	}

	public function setTypeParameters(array $typeParameters){
		$this->typeParameters=$typeParameters;
	}

	public function getDisposition(){
		return $this->disposition;
	}

	public function setDisposition($disposition){
		$this->disposition=$disposition;
	}

	public function getDispositionParameters(){
		return $this->dispositionParameters;
	}

	public function setDispositionParameters(array $dispositionParameters){
		$this->dispositionParameters=$dispositionParameters;
	}

	public function getEncoding(){
		return $this->encoding;
	}

	public function setEncoding($encoding){
		$this->encoding=$encoding;
	}

	public function getCharset(){
		return $this->charset;
	}

	public function setCharset($charset){
		$this->charset=$charset;
	}

	public function getId(){
		return $this->id;
	}

	public function setId($id){
		$this->id=$id;
	}

	public function getDescription(){
		return $this->description;
	}

	public function setDescription($description){
		$this->description=$description;
	}

	abstract public function isFetched();

	abstract public function fetch();

	abstract public function toString(array $envelope=[]);

	public function __toString(){
		return $this->toString();
	}

	protected function getBodyWithoutContent(){
		$body=[
			'type'=>$this->type,
			'type.parameters'=>$this->typeParameters,
			'disposition'=>$this->dispositionParameters
		];

		if($this->subType!==null){
			$body['subtype']=$this->subType;
		}

		if($this->encoding!==null){
			$body['encoding']=$this->encoding;
		}

		if($this->charset!==null){
			$body['charset']=$this->charset;
		}

		if($this->id!==null){
			$body['id']=$this->id;
		}

		if($this->description!==null){
			$body['description']=$this->description;
		}

		if($this->disposition!==null){
			$body['disposition.type']=$this->disposition;
		}

		return $body;
	}
}
