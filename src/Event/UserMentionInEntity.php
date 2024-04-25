<?php

namespace AtMentions\Event;

use BlueSpice\Entity;
use BlueSpice\Social\Entity\Text;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\EventLink;
use Title;

class UserMentionInEntity extends UserMention {
	/** @var Entity */
	private $entity;

	/** @var Title|null */
	private $relatedTitle;

	/**
	 * @param UserIdentity $mentionedUser
	 * @param UserIdentity $agent
	 * @param Entity $entity
	 * @param Title $entityTitle
	 * @param Title|null $relatedTitle
	 */
	public function __construct(
		UserIdentity $mentionedUser, UserIdentity $agent, Entity $entity,
		Title $entityTitle, ?Title $relatedTitle = null
	) {
		parent::__construct( $mentionedUser, $agent, $entityTitle );
		$this->entity = $entity;
		$this->relatedTitle = $relatedTitle;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'at-mentions-mention-in-entity';
	}

	/**
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->relatedTitle ?? parent::getTitle();
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'at-mentions-mention-in-entity-notification-key' );
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'at-mentions-mention-in-entity-notification-message' )->params(
			$this->getTitleDisplayText(),
			$this->getSnippet()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		// Return the link to the page where the comment was made
		// and to the comment itself (entity) < maybe unnecessary
		return parent::getLinks( $forChannel ) + [
			new EventLink(
				$this->title->getFullURL(),
				Message::newFromKey( 'at-mentions-mention-in-entity-notification-post-link' )
			)
		];
	}

	/**
	 * @return string
	 */
	private function getSnippet(): string {
		if ( !$this->entity instanceof Text ) {
			return '';
		}
		$text = $this->entity->get( Text::ATTR_TEXT );
		if ( $text === null ) {
			return '';
		}
		// TODO: Get snippet around the user link, just some random stuff for now
		$suffix = '...';
		$len = min( strlen( $text ), 100 );
		if ( strlen( $text ) < 100 ) {
			$suffix = '';
		}
		return substr( $text, 0, $len ) . $suffix;
	}
}
