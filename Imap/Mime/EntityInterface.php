<?php

namespace Imap\Mime;

interface EntityInterface{
	function getType();

	function setType($type);

	function getSubType();

	function setSubType($subType);

	function getTypeParameters();

	function setTypeParameters(array $typeParameters);

	function getDisposition();

	function setDisposition($disposition);

	function getDispositionParameters();

	function setDispositionParameters(array $dispositionParameters);

	function getEncoding();

	function setEncoding($encoding);

	function getCharset();

	function setCharset($charset);

	function getId();

	function setId($id);

	function getDescription();

	function setDescription($description);

	function isFetched();

	function fetch();

	function toString(array $envelope=[]);

	function __toString();
}
