<?php

namespace Imap\Mime;

interface EntityContainerInterface extends EntityInterface{
	function getChildren();

	function setChildren(array $children,$fetchNow=false);

	function prependChild(EntityInterface $entity,EntityInterface $beforeEntity=null);

	function appendChild(EntityInterface $entity,EntityInterface $afterEntity=null);

	function removeChild(EntityInterface $entity);
}
