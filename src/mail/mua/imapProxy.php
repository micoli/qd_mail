<?php
namespace qd\mail\mua\tools;

class imapProxy{
	var $cacheFolder	;
	var $currentFolder	;
	var $imapStream		;
	var $accounts		= array();
	var $cacheEnabled	= true;

	var $imap_order		= array(
		'date'		=> SORTDATE,
		'arrival'	=> SORTARRIVAL,
		'from'		=> SORTFROM,
		'subject'	=> SORTSUBJECT,
		'size'		=> SORTSIZE
	);

	public function __construct ($accounts){
		$this->accounts = $accounts;
	}

	public function __destruct (){
		if ($this->imapStream){
			imap_close($this->imapStream);
		}
	}

	public function setAccount ($account){
		$this->account = $account;
	}

	public function getAccountVar ($var = false){
		if($var){
			return $this->accounts[$this->account][$var];
		}else{
			return $this->accounts[$this->account];
		}
	}

	public function setCache($folder){
		$this->cacheFolder = $folder;
	}

	public function getCacheFileName($type,$message_no,$partno='body'){
		$foldA = md5($this->getAccountVar('cnx')).'_'.$this->getAccountVar('user');
		$foldB = str_replace('/','.',$this->currentFolder);
		$foldC = $type."-".$message_no%1000;
		$folderName = sprintf('%s/%s/%s/%s',$this->cacheFolder,$foldA,$foldB,$foldC);
		if(!file_exists($folderName)){
			mkdir($folderName,0755,true);
		}
		return sprintf("%s/%s_%s",$folderName,$message_no,$partno);
	}

	public function open ($subFolder=''){
		$this->currentFolder = $subFolder;
		$this->imapStream = imap_open($this->accounts[$this->account]['cnx'].$subFolder, $this->accounts[$this->account]['user'],$this->accounts[$this->account]['pass']);
	}

	public function getmailboxes ($filter){
		return imap_getmailboxes($this->imapStream, $this->accounts[$this->account]['cnx'], $filter);
	}

	public function getacl ($mailbox){
		return imap_getacl($this->imapStream, $mailbox);
	}

	public function isConnected(){
		return ($this->imapStream)?true:false;;
	}

	public function search ($query){
		return imap_search($this->imapStream,$query,SE_UID,"UTF-8");
	}

	public function renamemailbox($old,$new){
		return imap_renamemailbox($this->imapStream, $this->getAccountVar('cnx').$old, $this->getAccountVar('cnx').$new);
	}

	public function createmailbox($folder){
		return imap_createmailbox($this->imapStream, $this->getAccountVar('cnx').$folder);
	}

	public function deletemailbox($folder){
		return imap_deletemailbox($this->imapStream, $this->getAccountVar('cnx').$folder);
	}

	public function status ($mailbox,$options=SA_ALL){
		return imap_status($this->imapStream,$mailbox,$options);
	}

	public function sort ($sort,$dir){
		return imap_sort($this->imapStream,$this->imap_order[$sort],$dir=='ASC'?0:1,SE_UID);
	}

	public function thread (){
		return imap_thread($this->imapStream,SE_UID);
	}

	public function num_msg (){
		return imap_num_msg($this->imapStream);
	}

	public function uid ($message_no){
		return imap_uid($this->imapStream,$message_no);
	}
	public function msgno ($message_no){
		return imap_msgno($this->imapStream,$message_no);
	}

	public function fetch_overview ($p){
		return imap_fetch_overview($this->imapStream, $p,FT_UID);
	}

	public function fetchheader ($message_no){
		return imap_fetchheader($this->imapStream,$message_no,FT_UID);
	}

	public function fetchbody ($message_no,$partno){
		$file = $this->getCacheFileName(__FUNCTION__,$message_no,$partno);
		if($this->cacheEnabled && file_exists($file)){
			return json_decode(file_get_contents($file));
		}else{
			$tmp = imap_fetchbody($this->imapStream,$message_no,$partno,FT_UID);
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
			$tmp = imap_body($this->imapStream,$message_no,FT_UID);
			if($tmp){
				file_put_contents($file,json_encode($tmp));
			}
			return $tmp;
		}
		//return imap_body($this->imapStream,$message_no);
	}

	public function fetchstructure ($message_no){
		$file = $this->getCacheFileName(__FUNCTION__,$message_no,'struct');
		if($this->cacheEnabled && file_exists($file)){
			return json_decode(file_get_contents($file));
		}else{
			$tmp = imap_fetchstructure($this->imapStream,$message_no,FT_UID);
			if($tmp){
				file_put_contents($file,json_encode($tmp));
			}
			return $tmp;
		}
		//return imap_fetchstructure($this->imapStream,$message_no);
	}

	public function setflag_full($message_no,$flag){
		return imap_setflag_full($this->imapStream,$message_no,$flag,ST_UID);
	}

	public function clearflag_full($message_no,$flag){
		return imap_clearflag_full($this->imapStream,$message_no,$flag,ST_UID);
	}

	public function expunge(){
		return imap_expunge($this->imapStream);
	}

	public function mail_copy($sequence,$dest){
		return imap_mail_copy($this->imapStream,$sequence,$dest,CP_UID);
	}

	public function mail_move($sequence,$dest){
		return imap_mail_move($this->imapStream,$sequence,$dest,CP_UID);
	}

	public function append($folder, $mail_string,$flag){
		return imap_append($this->imapStream, $this->accounts[$this->account]['cnx'].$folder, $mail_string, $flag);
	}
}