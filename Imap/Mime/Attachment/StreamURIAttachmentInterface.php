<?php

namespace Imap\Mime\Attachment;

interface StreamURIAttachmentInterface extends AttachmentInterface{
	function getStreamURI();

	function getContext();
}
