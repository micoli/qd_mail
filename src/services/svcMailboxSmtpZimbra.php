<?php
namespace qd\services;
use CEP\Interceptor;
use qd\mail\mua\QDImap;

/**
 * @author o.michaud
 * @CEP_Interceptable()
 */
class svcMailboxSmtpZimbra extends svcMailboxSmtp{
	/**
	 * @var \qd\mail\mua\imapConnector\QDImapZIMBRA
	 */
	var $imapProxy;
	public function init(){
		$accounts = $GLOBALS['conf']['imapMailBox']['accounts'];
		$this->imapProxy = new \CEP\Interceptor(QDImap::getInstance($accounts,$this->proxyClass),isset($GLOBALS['conf']['app']['plugins'])?$GLOBALS['conf']['app']['plugins']:null);
	}

	public function pub_uploadAttachment($o){
		$this->setAccount($o['account']);
		$success = true;
		$aUpload = parent::pub_uploadAttachment($o);
		if($aUpload['success']){
			$atmp = $aUpload['files'];
			$aUpload['files']=array();
			foreach($atmp as $k=>$file){
				$aUpload['files'][$file['origin']] = $this->imapProxy->uploadAttachment(123, $file['path'], $file['prefix'].'-'.$file['origin']);
				if(!$aUpload['files'][$file['origin']]['success']){
					$success = false;
				}
			}
		}
		return array (
			'success'	=> $success,
			'files'		=> $aUpload['files']
		);
	}

	public function pub_sendMail($o){
		header('Content-type:text/html');
		$this->setAccount($o['account']);
		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array();
		}
		if(akead('is_draft',$o,false)){
			return $this->imapProxy->saveDraft($o);
		}else{
			return $this->imapProxy->sendEmail($o);
		}
	}

	public function pub_getMessageStructure($o){

	}
}
