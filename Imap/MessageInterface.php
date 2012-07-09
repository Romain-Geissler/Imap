<?php

namespace Imap;

interface MessageInterface{
	const RECENT_FLAG=1;
	const SEEN_FLAG=2;
	const FLAGGED_FLAG=4;
	const ANSWERED_FLAG=8;
	const DELETED_FLAG=16;
	const DRAFT_FLAG=32;

	function getMailbox();

	function getId();

	function getMessageNumber();

	function getTopMimeEntity($fetchNow=false);

	function getHeaders();

	function getEnvelope($removeMessageId=true);

	function getRawHeaders();

	function getRawBody();

	function getFlags();

	function setFlags($flags,$eraseOthers=false);

	function delete();

	function move(MailboxPathInterface $fullMailboxPath);

	function copy(MailboxPathInterface $fullMailboxPath);

	function flagsToString($flags);

	function clear();

	function fetchRawHeaders($sectionName=null);

	function fetchRawSectionBody($sectionName=null);

	function fetchSectionBody($sectionName);

	function fetchRawSection($sectionName=null);

	function getTextEntities($fetchNow=false);

	function getText();
	//return an UTF8 encoded string, no matter the original charset was.

	function hasAttachments($name=null);

	function getAttachments($name=null,$fetchNow=false);

	function getSingleAttachmentNamed($name,$fetchNow=false);

	//This will work no matter the encrypted message is nested in a signature entity or not.
	function decrypt(MailboxInterface $temporaryMailbox,$certificate,$privateKey=null,$requireEncryptedEntity=true);

	//This will work no matter the signed message is nested in an encrypted entity or not.
	//If you need to both decrypt and verify signature, better call "decrypt" first, as the signature
	//might be enclose in the encrypted message, thus being ciphered at first.
	function verifySignature(array $certificationAuthorities,$returnSignerPEMCertificate=true,$requireValidSignature=true);

	function __toString();
}
