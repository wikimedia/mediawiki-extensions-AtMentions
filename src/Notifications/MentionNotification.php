<?php

namespace AtMentions\Notifications;

use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Notifications\BaseNotification;
use Title;

class MentionNotification extends BaseNotification {

	/**
	 * @param UserIdentity $agent
	 * @param UserIdentity $targetUser
	 * @param Title $title
	 */
	public function __construct( UserIdentity $agent, UserIdentity $targetUser, Title $title ) {
		parent::__construct( 'at-mentions-mention-echo', $agent, $title );
		$this->addAffectedUsers( [ $targetUser ] );
	}

	/**
	 * @return array|string[]
	 */
	public function getParams() {
		return array_merge( parent::getParams(), [
			'realname' => $this->getUserRealName()
		] );
	}
}
