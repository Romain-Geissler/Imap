<?php

namespace Imap\Mime\Attachment;

use Imap\Mime\LeafEntityInterface;

interface AttachmentInterface extends LeafEntityInterface{
	const ATTACHMENT_DISPOSITION='attachment';
	const INLINE_DISPOSITION='inline';

	function getFileName();

	function setFileName($fileName);

	function toFile($path,$pathIsParentDirectoryPath=true);
}
