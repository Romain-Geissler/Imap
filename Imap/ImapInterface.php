<?php

namespace Imap;

interface ImapInterface{
	function connect(MailboxPathInterface $mailboxPath=null);

	function disconnect();

	function flushDelete();

	function getPath($namePath=null);

	function computeFullMailboxServerPath(MailboxPathInterface $mailboxPath);

	function computeFullMailboxPath($fullMailboxServerPath);

	function getHost();

	function getPort();

	function getUseSSL();

	function getUserName();

	function getPassword();

	function getMaxRetryCount();

	function getTopMailboxName();

	function getFactory();

	function setFactory(ImapFactoryInterface $factory);

	function getResource(MailboxPathInterface $mailboxPath=null);

	function getServerSpecification();

	function getCurrentFullMailboxServerPath();

	function getDelimiterCharacter();

	function getTopMailbox();

	function clearMessageCache();
}
