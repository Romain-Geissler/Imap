<?php

namespace Imap\Mime;

interface ImapEntityInterface extends EntityInterface{
	function getMessage();

	function getSectionName();
}
