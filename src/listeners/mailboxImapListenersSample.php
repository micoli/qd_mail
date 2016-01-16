<?php
namespace qd\listeners;
use CEP\Event;
use CEP\EventContainer;
use qd\mail\mua\QDImap;

/**
 */
class mailboxImapListenersSample extends EventContainer{
	/**
	 * @__CEP_EventHandler(event="qd.services.mua.mailbox_imap.__construct",priority=10)
	 * @__CEP_EventHandler(event="qd.services.mua.mailbox_imap.getTemplates.pre")
	 * @__CEP_EventHandler(event="qd.services.mua.mailbox_imap.getAccounts.pre",priority=10)
	 * @__CEP_EventHandler(event="qd.services.mua.mailbox_imap.setAccount.pre")
	 * @__CEP_EventHandler(event="qd.services.mua.mailbox_imap.getAccounts.transform")
	 **/
}