<?php

namespace Imap;

class MailboxPath{
	protected $imap;
	protected $namePath;
	protected $cachedEscapedNamePath;

	public function __construct(Imap $imap,$namePath=null){
		$this->imap=$imap;

		$this->move($namePath);
	}

	public function move($namePath){
		if($namePath instanceof MailboxPath){
			$this->namePath=$namePath->namePath;
			$this->cachedEscapedNamePath=$namePath->cachedEscapedNamePath;

			return;
		}

		if($namePath===null){
			$namePath=[];
		}

		if(!is_array($namePath)){
			$namePath=[$namePath];
		}

		foreach($namePath as &$name){
			$name=$this->checkAndSanitizeName($name);
		}

		$this->namePath=$namePath;

		$this->clearCache();
	}

	public function rename($name){
		if(!$this->hasName()){
			throw new \LogicException(sprintf('Failed to rename Mailbox path "%s": it has has no name.',$this));
		}

		$this->namePath[count($this->namePath)-1]=$this->checkAndSanitizeName($name);

		$this->clearCache();
	}

	public function getRenamed($name){
		$renamed=clone $this;

		$renamed->rename($name);

		return $renamed;
	}

	public function getNamePath(){
		return $this->namePath;
	}

	public function getLastName(){
		if(!$this->hasName()){
			throw new \LogicException(sprintf('Failed to get last name for Mailbox path "%s": it has has no name.',$this));
		}

		return $this->namePath[count($this->namePath)-1];
	}

	public function getLast(){
		$last=clone $this;
		$last->namePath=[$this->getLastName()];

		$last->clearCache();

		return $last;
	}

	public function getFirstName(){
		if(!$this->hasName()){
			throw new \LogicException(sprintf('Failed to get first name for Mailbox path "%s": it has has no name.',$this));
		}

		return $this->namePath[0];
	}

	public function getFirst(){
		$first=clone $this;
		$first->namePath=[$this->getFirstName()];

		$first->clearCache();

		return $first;
	}

	public function hasName(){
		return count($this->namePath)!=0;
	}

	public function hasSubName(){
		return count($this->namePath)>1;
	}

	public function getCommonAncestor(MailboxPath $path){
		$ancestor=new static($this->imap);
		$max=min(count($this->namePath),count($path->namePath));

		for($i=0;$i<$max;++$i){
			if($this->namePath[$i]!=$path->namePath[$i]){
				break;
			}

			$ancestor->namePath[]=$this->namePath[$i];
		}

		return $ancestor;
	}

	public function getDescentDifferentFrom(MailboxPath $path){
		$descent=new static($this->imap);
		$count=count($this->namePath);
		$max=min($count,count($path->namePath));

		for($i=0;$i<$max;++$i){
			if($this->namePath[$i]!=$path->namePath[$i]){
				break;
			}
		}

		$descent->namePath=array_slice($this->namePath,$i);

		return $descent;
	}

	public function isAncestorOf(MailboxPath $path,$strict=true){
		return $this->equals($this->getCommonAncestor($path))&&(!$strict||!$this->equals($path));
	}

	public function isDescendantOf(MailboxPath $path,$strict=true){
		return $path->isAncestorOf($this,$strict);
	}

	public function append(MailboxPath $path){
		foreach($path->namePath as $name){
			$this->namePath[]=$name;
		}

		$this->clearCache();
	}

	public function getAppended(MailboxPath $path){
		$appended=clone $this;

		$appended->append($path);

		return $appended;
	}

	public function prepend(MailboxPath $path){
		$oldNamePath=$this->namePath;
		$this->namePath=$path->namePath;

		foreach($oldNamePath as $name){
			$this->namePath[]=$name;
		}

		$this->clearCache();
	}

	public function getPrepended(MailboxPath $path){
		$prepended=clone $this;

		$prepended->prepend($path);

		return $prepended;
	}

	public function appendName($name){
		$name=$this->checkAndSanitizeName($name);

		array_push($this->namePath,$name);
		$this->clearCache();
	}

	public function getNameAppended($name){
		$nameAppended=clone $this;

		$nameAppended->appendName($name);

		return $nameAppended;
	}

	public function prependName($name){
		$name=$this->checkAndSanitizeName($name);

		array_unshift($this->namePath,$name);
		$this->clearCache();
	}

	public function getNamePrepended($name){
		$namePrepended=clone $this;

		$namePrepended->prependName($name);

		return $namePrepended;
	}

	public function removeLastName(){
		if(!$this->hasName()){
			throw new \LogicException(sprintf('Failed to remove last name for Mailbox path "%s": it has has no name.',$this));
		}

		array_pop($this->namePath);

		$this->clearCache();
	}

	public function getLastNameRemoved(){
		$lastNameRemoved=clone $this;

		$lastNameRemoved->removeLastName();

		return $lastNameRemoved;
	}

	public function removeFirstName(){
		if(!$this->hasName()){
			throw new \LogicException(sprintf('Failed to remove first name for Mailbox path "%s": it has has no name.',$this));
		}

		array_shift($this->namePath);

		$this->clearCache();
	}

	public function getFirstNameRemoved(){
		$firstNameRemoved=clone $this;

		$firstNameRemoved->removeFirstName();

		return $firstNameRemoved;
	}

	public function escape(){
		if($this->cachedEscapedNamePath===null){
			$topMailboxName=$this->imap->getTopMailboxName();

			if(!$this->hasName()){
				//special case when the delimiter has not been computed yet
				$this->cachedEscapedNamePath=static::escapeName($this->imap->getTopMailboxName());
			}else{
				$namePath=$this->namePath;

				if($topMailboxName!=''){
					array_unshift($namePath,$this->imap->getTopMailboxName());
				}

				foreach($namePath as &$name){
					$name=static::escapeName($name);
				}

				$this->cachedEscapedNamePath=implode($this->imap->getDelimiterCharacter(),$namePath);
			}
		}

		return $this->cachedEscapedNamePath;
	}

	//names are of course UTF-8 encoded
	public static function escapeName($name){
		//don't use imap_utf7_encode(utf8_decode($name)) as ISO-8859-1 can't
		//be used as an intermediate charset (cannot handle all characters)
		return mb_convert_encoding($name,'UTF7-IMAP','UTF-8');
	}

	public static function unescape(Imap $imap,$escapedPath){
		$unescaped=new static($imap);

		$unescaped->namePath=explode($imap->getDelimiterCharacter(),$escapedPath);

		if($imap->getTopMailboxName()!=''){
			array_shift($unescaped->namePath);
		}

		foreach($unescaped->namePath as &$name){
			$name=static::unescapeName($name);
		}

		return $unescaped;
	}

	public static function unescapeName($name){
		//don't use imap_utf8_encode(imap_utf7_decode($name)) as ISO-8859-1 can't
		//be used as an intermediate charset (cannot handle all characters)
		return mb_convert_encoding($name,'UTF-8','UTF7-IMAP');
	}

	public function escapeForChildrenLookup($deepLookup=false){
		return sprintf('%s%s%s',$this->escape(),$this->imap->getDelimiterCharacter(),$deepLookup?'*':'%');
	}

	public function getImap(){
		return $this->imap;
	}

	public function __toString(){
		if(!$this->hasName()){
			//special case: the top mailbox has no name (from this abstraction point of view)

			return '['.$this->imap->getTopMailboxName().']';
		}else{
			return implode($this->imap->getDelimiterCharacter(),$this->namePath);
		}
	}

	public function equals(MailboxPath $path){
		return $this->escape()==$path->escape();
	}

	protected function checkAndSanitizeName($name){
		$name=(string)$name;

		if($name==''){
			throw new \InvalidArgumentException('A mailbox name element must not be empty.');
		}

		$delimiterCharacter=$this->imap->getDelimiterCharacter();

		if(strpbrk($name,$delimiterCharacter)!==false){
			throw new \InvalidArgumentException(sprintf('A mailbox name element must not contain the delimiter character "%s"',$delimiterCharacter));
		}

		return $name;
	}

	protected function clearCache(){
		$this->cachedEscapedNamePath=null;
	}
}
