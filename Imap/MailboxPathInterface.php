<?php

namespace Imap;

interface MailboxPathInterface{
	function move($namePath);

	function rename($name);

	function getRenamed($name);

	function getNamePath();

	function getLastName();

	function getLast();

	function getFirstName();

	function getFirst();

	function hasName();

	function hasSubName();

	function getCommonAncestor(MailboxPathInterface $comparedPath);

	function getDescentDifferentFrom(MailboxPathInterface $comparedPath);

	function isAncestorOf(MailboxPathInterface $path,$strict=true);

	function isDirectAncestorOf(MailboxPathInterface $path);

	function isDescendantOf(MailboxPathInterface $path,$strict=true);

	function isDirectDescendantOf(MailboxPathInterface $path);

	function append(MailboxPathInterface $path);

	function getAppended(MailboxPathInterface $path);

	function prepend(MailboxPathInterface $path);

	function getPrepended(MailboxPathInterface $path);

	function appendName($name);

	function getNameAppended($name);

	function prependName($name);

	function getNamePrepended($name);

	function removeLastName();

	function getLastNameRemoved();

	function removeFirstName();

	function getFirstNameRemoved();

	function escape();

	function unescape($escapedPath);

	function escapeForChildrenLookup($deepLookup=false);

	function getImap();

	function __toString();

	function equals(MailboxPathInterface $path);
}
