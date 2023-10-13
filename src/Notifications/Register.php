<?php

namespace AtMentions\Notifications;

use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\Notifications\INotifier;

class Register {
	public static function registerNotifications() {
		/** @var INotifier $notifier */
		$notifier = MediaWikiServices::getInstance()->getService( 'MWStakeNotificationsNotifier' );
		$notifier->registerNotification(
			'at-mentions-mention-echo',
			[
				'category' => 'echo-category-title-mention',
				'summary-params' => [
					'title', 'agent', 'realname'
				],
				'email-subject-params' => [
					'title', 'agent', 'realname'
				],
				'email-body-params' => [
					'title', 'agent', 'realname'
				],
				'web-body-params' => [
					'title', 'agent', 'realname'
				],
				'summary-message' => 'at-mentions-echo-mention-notification-summary',
				'email-subject-message' => 'at-mentions-echo-mention-notification-subject',
				'email-body-message' => 'at-mentions-echo-mention-notification-email-body',
				'web-body-message' => 'at-mentions-echo-mention-notification-web-body',
			]
		);
		$notifier->registerNotification(
			'at-mentions-mention-in-entity-echo',
			[
				'category' => 'echo-category-title-mention',
				'summary-params' => [
					'title', 'agent', 'realname'
				],
				'email-subject-params' => [
					'title', 'agent', 'realname'
				],
				'email-body-params' => [
					'title', 'agent', 'realname'
				],
				'web-body-params' => [
					'title', 'agent', 'realname'
				],
				'summary-message' => 'at-mentions-echo-mention-entity-notification-summary',
				'email-subject-message' => 'at-mentions-echo-mention-entity-notification-subject',
				'email-body-message' => 'at-mentions-echo-mention-entity-notification-email-body',
				'web-body-message' => 'at-mentions-echo-mention-notification-web-body',
			]
		);
	}
}
