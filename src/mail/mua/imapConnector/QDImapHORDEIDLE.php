<?php
namespace qd\mail\mua\imapConnector;

class QDImapHORDEIDLE extends QDImapHORDE{
	var $internalClass='QDHorde_Imap_Client_SocketIdle';

	public function idle($time){
		return $this->imap_imp->idle($time);
	}
}