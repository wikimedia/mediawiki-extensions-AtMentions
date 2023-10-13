<?php

namespace AtMentions\Notifications;

class MentionInEntityNotification extends MentionNotification {
	/**
	 * @return string
	 */
	public function getKey() {
		return 'at-mentions-mention-in-entity-echo';
	}
}
