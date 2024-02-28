<?php

namespace AtMentions;

use Language;
use MediaWiki\User\UserFactory;
use User;

class MentionParser {
	/** @var UserFactory */
	private $userFactory;
	/** @var Language */
	private $language;

	/**
	 * @param UserFactory $userFactory
	 * @param Language $lang
	 */
	public function __construct( UserFactory $userFactory, Language $lang ) {
		$this->userFactory = $userFactory;
		$this->language = $lang;
	}

	/**
	 * @param string $text
	 *
	 * @return array [ 'text' => '[[User:...]]', 'user' => User object, 'label' => 'whatever is in [[User:...|label]]' ]
	 *
	 */
	public function parse( string $text ): array {
		$mentions = [];
		$matches = [];
		$nsInLang = $this->language->getNsText( NS_USER );
		// Mask off all Template calls
		$text = preg_replace( '/\{\{.*?\}\}/', '', $text );
		preg_match_all( "/\[\[(User|$nsInLang):(.*?)(\|(.*?))?\]\]/", $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$username = $match[2];
			$user = $this->userFactory->newFromName( $username );
			if ( !$user || !$user->isRegistered() ) {
				continue;
			}
			$mentions[] = [
				'text' => $match[0],
				'user' => $user,
				'label' => $match[4] ?? ( $user->getRealName() ?: $user->getName() )
			];
		}
		return $mentions;
	}

	/**
	 * @param string $text
	 * @param bool|null $count If true, this will return [ [ 'user' => {Userobject}, 'count' => {int} ] ],
	 * 					   if false (default), this will return [ {Userobject} ].
	 *
	 * @return User[]
	 */
	public function getMentionedUsers( string $text, $count = false ): array {
		$data = $this->parse( $text );
		$users = [];
		if ( $count ) {
			$count = [];
		}
		foreach ( $data as $dataItem ) {
			$users[$dataItem['user']->getId()] = $dataItem['user'];
			if ( is_array( $count ) ) {
				if ( !isset( $count[$dataItem['user']->getId()] ) ) {
					$count[$dataItem['user']->getId()] = 0;
				}
				$count[$dataItem['user']->getId()]++;
			}
		}

		if ( !is_array( $count ) ) {
			return array_values( $users );
		}

		$countedUsers = [];
		foreach ( $users as $userId => $user ) {
			$countedUsers[] = [
				'user' => $user,
				'count' => $count[$userId]
			];
		}
		return $countedUsers;
	}

	/**
	 * @param string $old
	 * @param string $new
	 *
	 * @return array
	 */
	public function getMentionUserDifference( string $old, string $new ): array {
		$oldUsers = $this->getMentionedUsers( $old, true );
		$newUsers = $this->getMentionedUsers( $new, true );
		// Consider user as "added" if its count increased
		$addedUsers = array_filter( $newUsers, static function ( $item ) use ( $oldUsers ) {
			foreach ( $oldUsers as $oldUser ) {
				if ( $oldUser['user']->getId() === $item['user']->getId() ) {
					return $oldUser['count'] < $item['count'];
				}
			}
			return true;
		} );

		$oldIds = array_map( static function ( array $item ) {
			return $item['user']->getId();
		}, $oldUsers );
		$newIds = array_map( static function ( array $item ) {
			return $item['user']->getId();
		}, $newUsers );
		$removed = array_diff( $oldIds, $newIds );
		$removedUsers = array_values( array_filter( $oldUsers, static function ( array $item ) use ( $removed ) {
			return in_array( $item['user']->getId(), $removed );
		} ) );
		return [
			'added' => array_map( static function ( array $item ) {
				return $item['user'];
			}, $addedUsers ),
			'removed' => array_map( static function ( array $item ) {
				return $item['user'];
			}, $removedUsers )
		];
	}
}
