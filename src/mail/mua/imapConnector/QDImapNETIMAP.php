<?php
namespace qd\mail\mua\imapConnector;

class QDImapNETIMAP extends QDImap{
	var $imapStream		;

	var $pear_imap_order		= array(
		'date'		=> 'INTERNALDATE',
		'arrival'	=> 'UID',
		'from'		=> SORTFROM,
		'subject'	=> 'SUBJECT',
		'size'		=> 'RFC822.SIZE'
	);

	public function getmailboxes ($filter){
		return $this->imapStream->getMailboxes($filter);
	}

	public function __destruct (){
		if($this->imapStream){
			$this->imapStream->disconnect();
		}
	}

	public function open ($subFolder=''){
		$this->currentFolder	= $subFolder;
		$this->currentFolder64	= base64_encode($subFolder);
		//$this->imapStream = new \Noi\Util\Mail\ImapIdleClient('localhost',143,false,'UTF-8');
		$this->imapStream = new QDNet_IMAP('localhost',143,false,'UTF-8');
		$this->imapStream->login($this->accounts[$this->account]['user'],$this->accounts[$this->account]['pass']);
		$this->imapStream->selectMailbox('INBOX');
	}

	public function getacl ($mailbox){
		return $this->imapStream->getACL($mailbox);
	}

	public function isConnected(){
		return isset($this->imapStream);
	}

	public function search ($query){
		return $this->imapStream->search($query);
	}

	public function renamemailbox($old,$new){
		//return $this->imapStream->;
	}

	public function createmailbox($folder){
	}

	public function deletemailbox($folder){
	}

	public function status ($mailbox,$options=SA_ALL){
		return $this->imapStream->getStatus($mailbox);
	}

	public function sort ($sort,$dir){
		$sort='arrival';
		if($this->imapStream->hasCapability('SORT')){
			//getFieldForSort
			return $this->imapStream->sort(($dir=='ASC'?:'REVERSE ').strtoupper($sort),true);
		}else{
			$aTmp = $this->imapStream->getFieldForSort(null,true,$this->pear_imap_order[$sort]);
			asort($aTmp, SORT_NATURAL);
			return $aTmp;
		}
	}

	public function thread (){
	}

	public function num_msg (){
		return $this->imapStream->getNumberOfMessages();
	}

	public function uid ($message_no){
	}

	public function msgno ($message_no){
	}

	public function fetch_overview ($p,$uid=true){
		return $this->imapStream->getSummary($p,true);
	}

	public function fetchheader ($message_no){
		return $this->imapStream->getRawHeaders($message_no,'',true);
	}

	public function fetchbody ($message_no,$partno){
		$file = $this->getCacheFileName(__FUNCTION__,$message_no,$partno);
		if($this->cacheEnabled && file_exists($file)){
			return json_decode(file_get_contents($file));
		}else{
			$tmp = $this->imapStream->getBodyPart($message_no,$partno,true);

			if($tmp){
				file_put_contents($file,json_encode($tmp));
			}
			return $tmp;
		}
	}

	public function body($message_no){
		$file = $this->getCacheFileName(__FUNCTION__,$message_no);
		if($this->cacheEnabled && file_exists($file)){
			return json_decode(file_get_contents($file));
		}else{
			$tmp = $this->imapStream->getBody($message_no,true);

			if($tmp){
				file_put_contents($file,json_encode($tmp));
			}
			return $tmp;
		}
	}

	public function fetchstructure ($message_no){
		$file = $this->getCacheFileName(__FUNCTION__,$message_no,'struct');
		if($this->cacheEnabled && file_exists($file)){
			return json_decode(file_get_contents($file));
		}else{
			$tmp = $this->imapStream->getStructure($message_no,true);

			if($tmp){
				file_put_contents($file,json_encode($tmp));
			}
			return $tmp;
		}
	}

	public function setflag_full($message_no,$flag){
		return $this->imapStream->setFlags($message_no, $flag);
	}

	public function clearflag_full($message_no,$flag){
		return $this->imapStream->setFlags($message_no, '','remove',true);
	}

	public function expunge(){
		return $this->imapStream->expunge();
	}

	public function mail_copy($sequence,$dest){
		return $this->imapStream->copyMessages($dest,$sequence,null,true);
	}

	public function mail_move($sequence,$dest){
	}

	public function append($folder, $mail_string,$flag){
		return $this->imapStream->appendMessage($mail_string,$folder,$flag);
	}

	public function fetch_overviewWithCache($aID,$o){
		$aMsgs	= $this->fetch_overview(implode(',',$aID));
		$aRet	= array();
		$aMID	= array();
		if ($aMsgs) {
			foreach ($aMsgs as $msg) {
				$oTmp = (object) array();
				if($msg['MESSAGE_ID']){
					$oTmp->message_id	= $msg['MESSAGE_ID'];
					$oTmp->subject		= $msg['SUBJECT'];
					$oTmp->from			= sprintf('"%s" <%s>',$msg['FROM'][0]['PERSONAL_NAME'	],$msg['FROM'	][0]['EMAIL']);
					$oTmp->to			= sprintf('"%s" <%s>',$msg['TO'][0]['PERSONAL_NAME'	],$msg['TO'		][0]['EMAIL']);
					$oTmp->date			= $msg['DATE'];
					$oTmp->size			= $msg['SIZE'];
					$oTmp->uid			= $msg['UID'];
					$oTmp->msgno		= $msg['MSG_NUM'];
					$oTmp->recent		= akead('recent',$msg['FLAGS'],false)?1:0;
					$oTmp->flagged		= akead('flagged',$msg['FLAGS'],false)?1:0;
					$oTmp->answered		= akead('answered',$msg['FLAGS'],false)?1:0;
					$oTmp->deleted		= akead('deleted',$msg['FLAGS'],false)?1:0;
					$oTmp->seen			= akead('\seen',$msg['FLAGS'],false)?1:0;
					$oTmp->draft		= akead('draft',$msg['FLAGS'],false)?1:0;
					$oTmp->udate		= strtotime($msg['INTERNALDATE']);
					$msg				= $oTmp;

					$msg->folder		= $folder;
					$msg->msgid			= $msg->uid;
					$msg->date			= date('Y-m-d H:i:s',strtotime($msg->date));
					$msg->account		= $o['account'];
					$msg->folder		= $o['folder'];
					$aMID[]				= $msg->msgid;
					$aRet[]				= $msg;
				}
			}
			$aMMGCache = $this->getMMGCache($aMID);
			foreach($aRet as &$msg){
				$this->getMsgWithCacheSupport($aMMGCache,$msg);
			}
		}
		return $aRet;
	}
}