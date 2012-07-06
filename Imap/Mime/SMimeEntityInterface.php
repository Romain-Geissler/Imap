<?php

namespace Imap\Mime;

interface SMimeEntityInterface extends EntityInterface{
	function getContentEntity();

	function hasContentEntity();

	function getRawContent();

	function hasRawContent();
}
