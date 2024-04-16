<?php

namespace AtMentions\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\TitleEvent;
use Title;

class UserMention extends TitleEvent {

	/** @var UserIdentity */
	private $mentionedUser;

	/**
	 * @param UserIdentity $mentionedUser
	 * @param UserIdentity $agent
	 * @param Title $title
	 */
	public function __construct( UserIdentity $mentionedUser, UserIdentity $agent, Title $title ) {
		parent::__construct( $agent, $title );
		$this->mentionedUser = $mentionedUser;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'at-mentions-mention';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'at-mentions-mention-notification-key' );
	}

	/**
	 * @return UserIdentity[]
	 */
	public function getPresetSubscribers(): array {
		return [ $this->mentionedUser ];
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'at-mentions-mention-notification-message' )->params(
			$this->getTitleDisplayText()
		);
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		return [];
	}
}
