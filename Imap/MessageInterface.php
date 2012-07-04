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

	function __toString();
}
