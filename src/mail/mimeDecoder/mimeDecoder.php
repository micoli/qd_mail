<?php
namespace qd\mail\mimedecoder;
require_once(dirname(__FILE__).'/mimeDecoderHtml.php');
require_once(dirname(__FILE__).'/mimeDecoderPlain.php');
class mimeDecoder{
	public static function decode($type,$data,$charset,&$o,&$attachments,&$head,&$outStruct,$options=array()){
		$className='qd\mail\mimedecoder\mimeDecoder'.ucfirst(strtolower($type));
		if(class_exists($className)){
			return $className::decode($data,$charset,$o,$attachments,$options);
		}else{
			return	'<b>type</b>	:<br><pre>'.print_r($type,true)."</pre>".
					'<b>head</b>	:<br><pre>'.print_r($head,true)."</pre>".
					'<b>struct</b>	:<br><pre>'.print_r($outStruct,true)."</pre>";
		}
	}
}
?>