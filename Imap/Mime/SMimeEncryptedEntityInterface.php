<?php

namespace Imap\Mime;

use Imap\MailboxInterface;

interface SMimeEncryptedEntityInterface extends SMimeEntityInterface{
	const TYPE=TYPEAPPLICATION;
	const SUBTYPE='pkcs7-mime';
	const SMIME_TYPE_ATTRIBUTE_NAME='smime-type';
	const SMIME_TYPE='enveloped-data';

	function isEncrypted();

	//throws MimeException if decryption fails for any reason.
	function decrypt(MailboxInterface $temporaryMailbox,$certificate,$privateKey=null);

	//throws MimeException if encryption fails for any reason.
	function encrypt(array $recepientCertificates);
}
