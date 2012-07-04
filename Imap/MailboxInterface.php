<?php

namespace Imap;

interface MailboxInterface{
	const ASCENDING_SORT=0;
	const DESCENDING_SORT=1;
	const ALL_MESSAGES_CRITERIA='ALL';

	function getImap();

	function getParent();

	function getPath();

	function getName();

	function hasMailbox(MailboxPathInterface $localMailboxPath);

	function createMailbox(MailboxPathInterface $localMailboxPath);

	function getMailbox(MailboxPathInterface $localMailboxPath,$checkExistence=true);

	function getMailboxes($deepLookup=false);

	function delete($recursive=false);

	function notifyDeletedMailbox($mailboxName);

	function move(MailboxPathInterface $fullMailboxPath);

	function notifyMovedPath(MailboxPathInterface $newFullMailboxPath);

	function notifyMovedOutMailbox($mailboxName);

	function notifyMovedInMailbox(MailboxInterface $mailbox);

	function getFetchedMailboxes($deepLookup=false);

	function clearFetchedMailboxes();

	function getMessages($criterias=self::ALL_MESSAGES_CRITERIA);

	function getSortedMessages($sortCriteria,$sortOrder=self::ASCENDING_SORT,$criterias=self::ALL_MESSAGES_CRITERIA);

	function getResource($moveToThisMailbox=true);

	function getFetchedMessages();

	function clearFetchedMessages();

	function clear();

	function notifyDeletedMessage($messageId);

	function notifyMovedOutMessage($oldMessageId);

	function notifyMovedInMessage(MessageInterface $message);

	function notifyCopiedMessage(MessageInterface $message);

	function notifyAddedMessage(MessageInterface $message);

	function getNextMessageId();

	function getMessageCount();

	function getRecentMessageCount();

	function addMessage($stringMessage,$flags=0);

	function __toString();
}
