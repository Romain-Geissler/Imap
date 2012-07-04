<?php

namespace Imap\Mime;

interface LeafEntityInterface extends EntityInterface{
	function getContent();

	function setContent($content);
}
