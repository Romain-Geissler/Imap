<?php

namespace Imap\Mime;

class SMimeSignedEntity extends AbstractSMimeEntity implements SMimeSignedEntityInterface{
	public function __construct(EntityInterface $contentEntity,$type=TYPEMULTIPART,$subType=SMimeSignedEntityInterface::SUBTYPE,array $typeParameters=[],$disposition=null,array $dispositionParameters=[],$encoding=null,$charset=null,$id=null,$description=null){
		parent::__construct($contentEntity,$type,$subType,$typeParameters,$disposition,$dispositionParameters,$encoding,$charset,$id,$description);
	}

	public function isSigned(){
		return $this->hasRawContent();
	}

	public function verifySignature(array $certificationAuthorities,$returnSignerPEMCertificate=true){
		$temporaryFilePaths=[];

		try{
			$temporaryFilePaths[]=$inputFilePath=$this->createTemporaryFile($this->getRawContent());
			$temporaryFilePaths[]=$signedCertificateFilePath=$this->createTemporaryFile();

			if(($result=openssl_pkcs7_verify($inputFilePath,PKCS7_DETACHED,$signedCertificateFilePath,$certificationAuthorities))===-1){
				throw new MimeException('Failed to verify S/Mime signature.');
			}

			if($result&&$returnSignerPEMCertificate){
				if(($result=file_get_contents($signedCertificateFilePath))===false){
					throw new MimeException('Failed to read signer certificate from temporary file (signature is valid through).');
				}
			}
		}catch(\Exception $e){
			$this->removeTemporaryFilePaths($temporaryFilePaths);

			throw $e;
		}

		$this->removeTemporaryFilePaths($temporaryFilePaths);

		return $result;
	}

	public function sign($certificate,$privateKey=null){
		if($privateKey===null){
			$privateKey=$certificate;
		}

		$temporaryFilePaths=[];

		try{
			$temporaryFilePaths[]=$inputFilePath=$this->createTemporaryFile($this->getContentEntity()->toString());
			$temporaryFilePaths[]=$outputFilePath=$this->createTemporaryFile();

			if(!openssl_pkcs7_sign($inputFilePath,$outputFilePath,$certificate,$privateKey,[])){
				throw new MimeException('Failed to sign entity.');
			}

			$this->setRawContent($this->sanitizeOpenSSLOutput($outputFilePath));
		}catch(\Exception $e){
			$this->removeTemporaryFilePaths($temporaryFilePaths);

			throw $e;
		}

		$this->removeTemporaryFilePaths($temporaryFilePaths);
	}
}
