<?php

namespace Imap\Mime;

interface Entity{
	function toString(array $envelope=[]);
}
