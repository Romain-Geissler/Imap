<?php

namespace Imap\Mime\Attachment;

interface StreamAttachmentInterface extends AttachmentInterface{
	function getStream();
}
