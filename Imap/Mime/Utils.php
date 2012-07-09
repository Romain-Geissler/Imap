<?php

namespace Imap\Mime;

class Utils{
	//$include => only mime headers are kept
	//!$include => all but mime headers are kept
	public static function filterMimeHeaders($rawHeaders,$include){
		$rawHeaders=substr($rawHeaders,0,-2);
		$matches=[];

		if(($count=preg_match_all('/^([\x21-\x39\x3B-\x7E]+):/m',$rawHeaders,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE))===false){
			throw new MimeException('Unexpected regex error.');
		}

		$filteredHeaders=[];

		foreach($matches as $i=>$match){
			if(!$include^(bool)preg_match('/^(content-|mime-version)/i',$match[1][0])){
				if($i!=$count-1){
					$filteredHeader=substr($rawHeaders,$match[0][1],$matches[$i+1][0][1]-$match[0][1]);
				}else{
					$filteredHeader=substr($rawHeaders,$match[0][1]);
				}

				$filteredHeaders[]=$filteredHeader;
			}
		}

		return implode('',$filteredHeaders);
	}
}
