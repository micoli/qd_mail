<?php
namespace qd\mail\mimedecoder;

class mimeDecoderHtml{
	public static function decode($body,$charset,&$o,&$attachments,$options=array()){
		if($charset=='unknown'){
			$body = utf8_encode($body);//$charset='ISO-8859-1';
		}else{ //if ( (strtoupper($charset)!='UTF-8')){
			$body= iconv(strtoupper($charset),'UTF-8',$body); // //TRANSLIT
		}
		$o['hasInlineComponents'] = false;

		foreach($attachments as &$f){
			if(array_key_exists('id',$f) && $f['id']!='-'){
				$body = preg_replace('!src=(?:"|\')cid:'.preg_quote($f['id'],'!').'(?:"|\')!','src="'.$f['attachUrlLink'].'"',$body);
				$o['hasInlineComponents'] = true;
			}
		}
		if(!akead('no_safe_image',$options,false)){
			if(preg_match('!src=(?:"|\')([^\'"]*)(?:"|\')!',$body)){
				$body = preg_replace('!src=(?:"|\')([^\'"]*)(?:"|\')!', 'src="" data-imgsafesrc="\\1"',$body );
				$o['hasInlineComponents'] = true;
			}
		}

		return $body;
	}
}
