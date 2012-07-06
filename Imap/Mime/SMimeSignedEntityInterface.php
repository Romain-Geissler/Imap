<?php

namespace Imap\Mime;

interface SMimeSignedEntityInterface extends SMimeEntityInterface{
	const SUBTYPE='signed';

	function isSigned();

	//$returnSignerPEMCertificate => return false (invalid signature) or a string containing
	//the signer PEM certificate (valid signature).
	//
	//!$returnSignerPEMCertificate => return false (invalid signature) or true (valid signature)
	//
	//Note that in both cases, MimeException may still occur (failed to verify signature caused by
	//another unexpected error).
	function verifySignature(array $certificationAuthorities,$returnSignerPEMCertificate=true);

	//throws MimeException if signature fails for any reason.
	function sign($certificate,$privateKey=null);
}
