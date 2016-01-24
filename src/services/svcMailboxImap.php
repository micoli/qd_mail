<?php
namespace qd\services;
use \qd\mail\mua\QDImap;
use \qd\mail\orm\MMG_MAIL_MESSAGE;
use \CEP\Interceptor;
use \Symfony\Component\EventDispatcher\EventDispatcherInterface;

class svcMailboxImap {
	var $imapProxy;
	var $proxyClass		= 'ZIMBRA';
	var $dispatchKey	= 'qd.services.mua.mailbox_imap';

	/**
	 * @var \CEP\EventDispatcher
	 **/
	protected $dispatcher;

	public function attachDispatcher(EventDispatcherInterface $dispatcher){
		$this->dispatcher = $dispatcher;
	}

	public function __construct(){
		//header('content-type: text/html; charset=utf-8');
		QDImap::$svcImapClass=preg_replace('!^svc!','',array_pop(explode('\\',get_class($this))));
	}

	public function init(){
		\QDOrm::addConnection('extmailbox', new \QDPDO($GLOBALS['conf']['qddb']['connection'], $GLOBALS['conf']['qddb']['username'], $GLOBALS['conf']['qddb']['password']));

		$this->imapProxy = new Interceptor(QDImap::getInstance($GLOBALS['conf']['imapMailBox']['accounts'],$this->proxyClass),isset($GLOBALS['conf']['app']['plugins'])?$GLOBALS['conf']['app']['plugins']:null);
		$this->imapProxy->setCache($GLOBALS['conf']['imapMailBox']['tmp']);
		$this->imapProxy->setDBCacheObject(new \MMG_MAIL_MESSAGE());
	}

	protected function setAccount($account,$withCheck=false){
		$this->init();
		$this->dispatcher->dispatch($this->dispatchKey.'.setAccount.inpre',new \CEP\Event($this,$account));
		$this->imapProxy->setAccount($account,$withCheck);
		$this->dispatcher->dispatch($this->dispatchKey.'.setAccount.inpost',new \CEP\Event($this,$account));
	}

	public function pub_todo_getMailThreadsInFolders($o){
		$this->setAccount($o['account']);

		$res = array();
		$folder=base64_decode($o['folder']);
		$this->imapProxy->open($folder);
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}

		//db(imap_thread($this->imapProxy->imapStream));
		//$aID	= $this->imapProxy->sort(akead('sort', $o, 'date'),akead('dir', $o, 'DESC'));
		//$num	= $this->imapProxy->num_msg();

		$threads = $rootValues = array();
		$thread = $this->imapProxy->thread();
		$root = 0;
		//first we find the root (or parent) value for each email in the thread
		//we ignore emails that have no root value except those that are infact
		//the root of a thread

		//we want to gather the message IDs in a way where we can get the details of
		//all emails on one call rather than individual calls ( for performance )

		//foreach thread
		foreach ($thread as $i => $messageId) {
			//get sequence and type
			list($sequence, $type) = explode('.', $i);

			//if type is not num or messageId is 0 or (start of a new thread and no next) or is already set
			if($type != 'num' || $messageId == 0
					|| ($root == 0 && $thread[$sequence.'.next'] == 0)
					|| isset($rootValues[$messageId])) {
				//ignore it
				continue;
			}

			//if this is the start of a new thread
			if($root == 0) {
				//set root
				$root = $messageId;
			}

			//at this point this will be part of a thread
			//let's remember the root for this email
			$rootValues[$messageId] = $root;

			//if there is no next
			if($thread[$sequence.'.next'] == 0) {
			//reset root
				$root = 0;
			}
		}

		//now get all the emails details in rootValues in one call
		//because one call for 1000 rows to a server is better
		//than calling the server 1000 times
		$emails = imap_fetch_overview($imap, implode(',', array_keys($rootValues)));

		if($num==0 || !$aID){
			return array('data'=>array(),'totalCount'=>0);
		}

		//there is no need to sort, the threads will automagically in chronological order
		echo '<pre>'.print_r($threads, true).'</pre>';

		foreach ($emails as $msg) {
			if($msg->message_id){
				$aMID = array();
				$msg->msgid		= $folder.'/'.$msg->uid;
				$msg->date		= date('Y-m-d H:i:s',strtotime($msg->date));
				$msg->account	= $o['account'];
				$msg->folder	= $o['folder'];
				$aMID[]			= $msg->msgid;
				$aMMGCache		= $this->getMMGCache($aMID);
				$this->getMsgWithCacheSupport($aMMGCache,$msg);
			}
			$root = $rootValues[$msg->msgno];
			$threads[$root][] = $msg;
		}
		db($threads);
		//$aMsgs	= $this->imapProxy->fetch_overview(implode(',',array_keys($rootValues)));
		$a= array('data'=>array_values($threads),'totalCount'=>$num,'s'=>$nStart,'m'=>($nStart+$nCnt-1));
		return $a;
	}

	public function pub_getAccounts($o){
		$tmp = array();
		$o['account']=akead('account',$o,array_shift(array_keys($this->imapProxy->accounts)));
		$this->setAccount($o['account'],true);

		$aIdentities=$this->imapProxy->getIdentities($o);

		foreach($this->imapProxy->accounts as $k=>$v){
			$tmp[]=array(
				'account'	=> $k,
				'email'		=> $v['email'],
				'identities'=> $aIdentities
			);
		}
		/*usort($tmp, function($a, $b){
			if ($a['detail']['default'] == $b['detail']['default']) {
				return 0;
			}
			return ($a['detail']['default']===true)?-1:1;
		});*/

		$result = array(
			'data'=>$tmp
		);
		return $result;
	}

	public function pub_getTemplates($o){
		$res=array('data'=>array());
		$templatePath = akead('templatePath',$o,dirname(__FILE__).'/mailTemplates');
		foreach(glob($templatePath.'/*.html') as $template){
			$res['data'][]=array(
					'name'	=> str_replace('.html','',basename($template)),
					'body'	=> file_get_contents($template)
			);
		}
		return $res;
	}

	public function pub_setMessageFlag($o){
		$this->setAccount($o['account']);

		$o['folder'] = base64_decode($o['folder']);
		$this->imapProxy->open($o['folder']);
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}
		if($o['value']==1){
			return $this->imapProxy->setflag_full	($o['message_no'],"\\".$o['flag']);
		}else{
			return $this->imapProxy->clearflag_full($o['message_no'],"\\".$o['flag']);
		}
	}

	public function pub_searchContact($o){
		$QDDb = new \QDDB();
		if(array_key_exists('query',$o)){
			$arr = $QDDb->query2Array(sprintf('select p.*,personal as name from imail.PRS_PERSONAL p where email like "%%%s%%" or personal like "%%%s%%"  ',$o['query'],$o['query']));
		}else{
			$sql = 'select * from imail.PRS_PERSONAL where true ';
			if(array_key_exists('name',$o)){
				$sql.=sprintf(' and personal like "%%%s%%"',$o['name']);
			}
			if(array_key_exists('email',$o)){
				$sql.=sprintf(' and email like "%%%s%%"',$o['email']);
			}
			$arr = $QDDb->query2Array($sql);
		}
		return array(
			'data'			=> $arr,
			'totalCount'	=> count($arr)
		);
	}

	public function pub_folderRename($o){
		$this->setAccount($o['account']);

		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}
		$this->imapProxy->renamemailbox(
			base64_decode($o['parentFolder']).'.'.base64_decode($o['oldName']),
			base64_decode($o['parentFolder']).'.'.base64_decode($o['newName'])
		);
		return array('ok'=>true);
	}

	public function pub_createSubFolder($o){
		$this->setAccount($o['account']);

		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}
		$this->imapProxy->createmailbox(base64_decode($o['parentFolder']).'.'.base64_decode($o['subFolder']));
		return array('ok'=>true);
	}

	public function pub_deleteFolder($o){
		$this->setAccount($o['account']);

		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}
		$this->imapProxy->deletemailbox(base64_decode($o['folder']));
		return array('ok'=>true);
		//name
	}

	public function pub_getAccountFolders($o){
		$this->setAccount($o['account']);

		$res = array(
			'text'			=> $o['account'],
			'uiProvider'	=> 'col',
			'expanded'		=> true,
			'allowDrop'		=> true,
			'allowChildren'	=> true,
			'folderType'	=> 'account'
		);
		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return $res;
		}
		$list	= $this->imapProxy->getmailboxes("*");
		if (is_array($list)){
			foreach ($list as $val) {
				$name	= utf8_encode(imap_utf7_decode(str_replace($this->imapProxy->getAccountVar('cnx'),'',$val->name)));
				if(trim($name)!=''){
					$tmpArr	= explode($val->delimiter,$name);
					$tmp	= &$res;
					foreach($tmpArr as $k=>$v){
						$id=implode($val->delimiter,array_slice($tmpArr,0,$k+1));
						if(!array_key_exists('children',$tmp)){
							$tmp['children']=array();
						}
						$subId=$id;
						if(!(array_key_exists($subId,$tmp['children']))){
							//db($subId);
							//db($this->imapProxy->getacl($subId));
							$tmp['children'][$subId]=array(
								'text'			=> $v,
								'id'			=> base64_encode($id),
								'fid'			=> $id,
								'uiProvider'	=> 'col',
								'cls'			=> ' x-tree-node-collapsed x-tree-node-icon ',
								'nb'			=> 0,//imap_num_msg ($currMbox)
								'allowDrop'		=> true,
								'stat'			=> $this->imapProxy->status($subId),
								'folderType'	=> 'folder'
							);
							QDImap::personalizeFolderIcon($tmp['children'][$subId],strtolower($v));
						}
						$tmp=&$tmp['children'][$subId];
					}
				}
			}
			uasort($res['children'],array('QDImap','sortNaturalMailFolders'));
		} else {
			echo "imap_getmailboxes failed : " . imap_last_error() . "\n";
		}
		$this->flatAssocChildren($res);
		return array($res);
	}

	public function pub_getMailListInFolders($o){
		$this->setAccount($o['account']);

		$this->imapProxy->open();
		if(!$this->imapProxy->isConnected()){
			return array();
		}

		$folder=base64_decode($o['folder']);

		$query = akead('query',$o,false);

		if($query){
			$aID	= $this->imapProxy->search($query);
			$num	= count($aID);
		}else{
			$aID	= $this->imapProxy->sort(akead('sort', $o, 'date'),akead('dir', $o, 'DESC'));
			$num	= $this->imapProxy->num_msg();
			fb($aID);
		}
		if($num==0 || !$aID){
			return array('data'=>array(),'totalCount'=>0);
		}
		$nStart	= akead('start'	,$o, 0);
		$nCnt	= akead('limit'	,$o,25);
		if (($nStart+$nCnt) > $num) {
			$nCnt = $num-$nStart;
		}
		$aID	= array_slice($aID,$nStart,$nCnt);
		$aRet	= $this->imapProxy->fetch_overviewWithCache($aID,$o);
		if(akead('dir', $o, 'DESC')=='DESC'){
			$aRet = array_reverse($aRet);
		}
		$a = array('data'=>array_values($aRet),'totalCount'=>$num,'s'=>$nStart,'m'=>($nStart+$nCnt-1));
		return $a;

	}

	public function pub_getMessageSource($o){
		$this->setAccount($o['account']);

		$folder		= base64_decode($o['folder']);;
		$message_no	= $o['message_no'];

		$this->imapProxy->open($folder);

		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}
		$data = $this->imapProxy->body($message_no);
		return array('source'=>$data);
	}

	public function pub_getMessageContent($o){
		$this->setAccount($o['account']);

		$folder		= base64_decode($o['folder']);;
		$message_no	= $o['message_no'];

		$this->imapProxy->open($folder);

		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}

		$res = $this->imapProxy->getMessageContent($message_no,array(
			'no_safe_image'	=> akead('no_safe_image',$o,false)
		));
		$aAllAttachmentsPartNo = array(-1);
		if(is_array($res['attachments'])){
			foreach($res['attachments'] as &$f){
				$aAllAttachmentsPartNo[] = $f['partno'];
				$f['attachUrlLink']=QDImap::getAttachementURLLink($o,$f['partno']);
				if($f['filename']){
					$f['type']=strtolower(pathinfo($f['filename'],PATHINFO_EXTENSION));
				}
			}
			if(count($res['attachments'])>=2){
				$res['attachments'][]=array(
					'filename'		=> 'all',
					'hfilename'		=> 'all',
					'type'			=> 'zip',
					'size'			=> -1,
					'partno'		=> implode(',',$aAllAttachmentsPartNo),
					'attachUrlLink'	=> QDImap::getAttachementURLLink($o,implode(',',$aAllAttachmentsPartNo))
				);
			}
		}
		return $res;
	}

	public function pub_getMessageAttachment($o){
		$this->setAccount($o['account']);

		$folder		= base64_decode($o['folder']);;
		$message_no	= $o['message_no'];

		$this->imapProxy->open($folder);
		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}

		if(preg_match('!^-1,!',$o['partno'])){
			$outStruct	= $this->imapProxy->getMimeFlatStruct($message_no);
			$outStruct	= $outStruct['flat'];
			$tmpName	= tempnam(sys_get_temp_dir(),'zip')."_folder.zip";
			$archive	= new \PclZip($tmpName);
			$archDatas	= array();
			foreach($outStruct as $partno=>$part){
				if($filename=$this->getPartFilename($part)){
					$data = $this->imapProxy->fetchbody($message_no,$partno);
					$archDatas[]=array(
						PCLZIP_ATT_FILE_NAME	=> $filename,
						PCLZIP_ATT_FILE_CONTENT	=> $data
					);
				}
			}
			$list = $archive->create($archDatas);
			if ($list == 0) {
				die("ERROR : '".$archive->errorInfo(true)."'");
			}
			header('Content-type: application/zip');
			$this->headerForDownload("folder.zip",filesize($tmpName));
			print file_get_contents($tmpName);
			unlink($tmpName);
			die();
		} else {
			$part = $outStruct[$o['partno']];
			if(array_key_exists('filename',$o)){
				$filename=$o['filename'];
			}else{
				$outStruct	= $this->imapProxy->getMimeFlatStruct($message_no);
				$outStruct	= $outStruct['flat'];
				$filename	= $this->getPartFilename($part);
			}

			$data = $this->imapProxy->fetchbody($message_no,$o['partno']);

			if(false){
				header('content-type: text/html; charset=utf-8');
				db($filename);
				db($part);
				db($outStruct);
				db(urlencode($this->imapProxy->decodeMimeStr($filename)));
				db($data);
				die();
			}
			$o['onlyView']=1;
			if(akead('onlyView',$o,false)){
				$this->headerForView($filename,mb_strlen($data));
			}else{
				$this->headerForDownload($filename,mb_strlen($data));
			}
			print $data;
			die();
		}
	}

	public function pub_expunge($o){
		$this->setAccount($o['account']);

		$folder	= base64_decode($o['folder']);

		$this->imapProxy->open($fromFolder);
		$this->imapProxy->expunge();
		return array('ok'=> true);
	}

	public function pub_mailCopyMove($o){
		$this->setAccount($o['account']);

		$fromFolder	= base64_decode($o['fromFolder']);
		$toFolder	= base64_decode($o['toFolder'  ]);
		$toFolderId	= $o['toFolderId'];

		$this->imapProxy->open($fromFolder);

		if(!$this->imapProxy->isConnected()){
			return array('error'=>true);
		}
		$ids = explode(',',$o['messages_no']);
		asort($ids);
		switch (strtolower($o['mode'])){
			case 'copy':
				$ok = $this->imapProxy->mail_copy(implode(',',$ids),$toFolder,$toFolderId);
			break;
			case 'move':
				$ok = $this->imapProxy->mail_move(implode(',',$ids),$toFolder,$toFolderId);
			break;
		}

		if(!$ok){
			return array('ok'=>0,'errors'=>join("\n",imap_errors()));
		}else{
			$this->imapProxy->expunge();
			return array('ok'=>count($ids));
		}
	}

	public function getPartFilename($part){
		if(	is_array($part) && ( array_key_exists('name',$part) || array_key_exists('filename',$part) ) ){
			return ($part['filename'])? $part['filename'] : $part['name'];
		}else{
			return false;
		}
	}

	private function headerForDownload($filename,$size){
		header("Content-Disposition: attachment; filename=" . urlencode($this->imapProxy->decodeMimeStr($filename)));
		header("Cache-Control: no-cache, must-revalidate");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Description: File Transfer");
		header("Content-Length: " . $size);
	}

	private function headerForView($filename,$size){
		////header("Content-Disposition: attachment; filename=" . urlencode($this->imapProxy->decodeMimeStr($filename)));
		header("Content-Type: "		.QDImap::getMimeContentType($filename));
		header("Content-Length: " 	. $size);
	}

	private function flatAssocChildren(&$v){
		if(is_array($v) && array_key_exists('children',$v)){
			$v['leaf']=false;
			$v['children']=array_values($v['children']);
			foreach($v['children'] as &$vv){
				$this->flatAssocChildren($vv);
			}
		}else{
			$v['leaf']=true;
		}
	}
}