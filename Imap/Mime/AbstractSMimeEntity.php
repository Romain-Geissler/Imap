<?php

namespace Imap\Mime;

abstract class AbstractSMimeEntity extends AbstractEntity implements SMimeEntityInterface{
	protected $contentEntity;
	protected $rawContent;

	public function __construct(EntityInterface $contentEntity=null,$type=TYPEMULTIPART,$subType=null,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);

		$this->setContentEntity($contentEntity);
		$this->setRawContent(null);
	}

	public function getContentEntity(){
		if(!$this->hasContentEntity()){
			throw new MimeException('Failed to get content entity as the content entity was never provided/generated.');
		}

		return $this->contentEntity;
	}

	public function hasContentEntity(){
		return $this->contentEntity!==null;
	}

	public function getRawContent(){
		if(!$this->hasRawContent()){
			throw new MimeException('Failed to get raw content as it was never provided/generated');
		}

		return $this->rawContent;
	}

	public function hasRawContent(){
		return $this->rawContent!==null;
	}

	public function isFetched(){
		if(!$this->hasContentEntity()){
			return false;
		}else{
			$this->getContentEntity()->isFetched();
		}
	}

	public function fetch(){
		$this->getContentEntity()->fetch();
	}

	public function getTextEntities($fetchNow=false){
		return $this->getContentEntity()->getTextEntities($fetchNow);
	}

	public function getAttachments($name=null,$fetchNow=false){
		return $this->getContentEntity()->getAttachments($name,$fetchNow);
	}

	public function toString(array $envelope=[]){
		return $this->toHeaderString($envelope).$this->getRawContent();
	}

	protected function setContentEntity(EntityInterface $contentEntity=null){
		$this->contentEntity=$contentEntity;
	}

	protected function setRawContent($rawContent){
		$this->rawContent=$rawContent;
	}

	protected function createTemporaryFile($content=null){
		if(($temporaryFilePath=tempnam(sys_get_temp_dir(),'SMimeSignature'))===false){
			throw new MimeException('Failed to retrieve temporary file path.');
		}

		if($content!==null){
			if(file_put_contents($temporaryFilePath,$content)===false){
				throw new MimeException('Failed to write raw S/Mime entity content in a temporary file.');
			}
		}

		return $temporaryFilePath;
	}

	protected function removeTemporaryFilePaths(array $temporaryFilePaths){
		foreach($temporaryFilePaths as $temporaryFilePath){
			if(file_exists($temporaryFilePath)){
				unlink($temporaryFilePath);
			}
		}
	}

	protected function sanitizeOpenSSLOutput($outputFilePath){
		if(($rawContent=file_get_contents($outputFilePath))===false){
			throw new MimeException('Failed to read temporary file.');
		}

		$rawContent=ltrim($rawContent);
		$rawContent=str_replace("\r\n","\n",$rawContent);
		$rawContent=str_replace("\n","\r\n",$rawContent);

		return $rawContent;
	}

	protected function toHeaderString(array $envelope){
		$headers=imap_mail_compose($envelope,$this->getBodies());
		$headers=substr($headers,0,strpos($headers,"\r\n\r\n")+4);
		$headers=Utils::filterMimeHeaders($headers,false);

		return substr($headers,0,-4);
	}

	protected function getBodies(){
		$body=$this->getBodyWithoutContent();

		return [
			$body,
			[
				'type'=>TYPETEXT,
				'contents.data'=>'dummy text'
			]
		];
	}
}
