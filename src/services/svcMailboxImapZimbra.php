<?php
namespace qd\services;
use CEP\Interceptor;
use qd\mail\mua\QDImap;

class svcMailboxImapZimbra extends svcMailboxImap{

	public function init(){
		$accounts = $GLOBALS['conf']['imapMailBox']['accounts'];
		$this->imapProxy = new Interceptor(QDImap::getInstance($accounts,$this->proxyClass),isset($GLOBALS['conf']['app']['plugins'])?$GLOBALS['conf']['app']['plugins']:null);
	}

	public function pub_getAccountFolders($o){
		$this->imapProxy->setAccount($o['account']);
		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array();
		}
		return $this->imapProxy->getmailboxes("*");
	}

	public function pub_getMailListInFolders($o){
		$o['folder']=base64_decode($o['folder']);
		$this->imapProxy->setAccount($o['account']);
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}

		$query = array_key_exists_assign_default('query',$o,false);

		if($query){
			$res = $this->imapProxy->search(array(
				'query'=>$query
			));
		}else{
			$o['query']='in:"'.$o['folder'].'"';
			$res = $this->imapProxy->search($o);
		}
		return array (
			'data'		=> $res,
			'totalCount'=> count ( $res ) * 200,
			'm'			=> count ( $res ) * 200,
			's'			=> 0
		);
	}

	public function pub_searchContact($o){
		$this->imapProxy->setAccount($o['account']);
		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array();
		}
		return $this->imapProxy->searchContact($o);
	}

}