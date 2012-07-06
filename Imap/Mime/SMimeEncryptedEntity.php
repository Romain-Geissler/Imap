<?php

namespace Imap\Mime;

use Imap\MessageInterface;
use Imap\MailboxInterface;

class SMimeEncryptedEntity extends AbstractSMimeEntity implements SMimeEncryptedEntityInterface{
	public function __construct(EntityInterface $contentEntity=null,$type=TYPEMULTIPART,$subType=SMimeEncryptedEntityInterface::SUBTYPE,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($contentEntity,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);
	}

	public function isEncrypted(){
		return $this->hasRawContent();
	}

	public function decrypt(MailboxInterface $temporaryMailbox,$certificate,$privateKey=null){
		if($privateKey===null){
			$privateKey=$certificate;
		}

		$temporaryFilePaths=[];

		try{
			$temporaryFilePaths[]=$inputFilePath=$this->createTemporaryFile($this->getRawContent());
			$temporaryFilePaths[]=$outputFilePath=$this->createTemporaryFile();

			if(!openssl_pkcs7_decrypt($inputFilePath,$outputFilePath,$certificate,$privateKey)){
				throw new MimeException('Failed to decrypt S/Mime message.');
			}

			$decryptedRawContent=$this->sanitizeOpenSSLOutput($outputFilePath);
			$message=$temporaryMailbox->addMessage($decryptedRawContent,MessageInterface::SEEN_FLAG|MessageInterface::DRAFT_FLAG);
			$this->contentEntity=$message->getTopMimeEntity(true);

			$message->delete();
		}catch(\Exception $e){
			$this->removeTemporaryFilePaths($temporaryFilePaths);

			throw $e;
		}

		$this->removeTemporaryFilePaths($temporaryFilePaths);
	}

	public function encrypt(array $recepientCertificates){
		$temporaryFilePaths=[];

		try{
			$temporaryFilePaths[]=$inputFilePath=$this->createTemporaryFile($this->getContentEntity()->toString());
			$temporaryFilePaths[]=$outputFilePath=$this->createTemporaryFile();

			if(!openssl_pkcs7_encrypt($inputFilePath,$outputFilePath,$recepientCertificates,[])){
				throw new MimeException('Failed to encrypt entity.');
			}

			$this->setRawContent($this->sanitizeOpenSSLOutput($outputFilePath));
		}catch(\Exception $e){
			$this->removeTemporaryFilePaths($temporaryFilePaths);

			throw $e;
		}

		$this->removeTemporaryFilePaths($temporaryFilePaths);
	}
}
