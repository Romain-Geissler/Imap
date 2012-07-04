<?php

namespace Imap\Mime;

interface ImapLeafEntityInterface extends LeafEntityInterface{
	function getMessage();

	function getSectionName();
}
