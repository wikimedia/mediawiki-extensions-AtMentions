<?php

namespace AtMentions\Hook;

use AtMentions\Event\UserMention;
use AtMentions\Mention;
use AtMentions\MentionParser;
use AtMentions\MentionStore;
use ManualLogEntry;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Notifier;
use Title;
use TitleFactory;
use User;

class ProcessMentions implements
	ParserBeforeInternalParseHook,
	PageSaveCompleteHook,
	PageMoveCompleteHook,
	PageDeleteCompleteHook,
	ArticleUndeleteHook
{
	/** @var MentionParser */
	protected $parser;

	/** @var MentionStore */
	protected $store;

	/** @var RevisionStore */
	protected $revisionStore;

	/** @var Notifier */
	protected $notifier;

	/** @var TitleFactory */
	protected $titleFactory;

	/**
	 * @param MentionParser $parser
	 * @param MentionStore $mentionStore
	 * @param RevisionStore $revisionStore
	 * @param Notifier $notifier
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		MentionParser $parser, MentionStore $mentionStore,
		RevisionStore $revisionStore, Notifier $notifier, TitleFactory $titleFactory
	) {
		$this->parser = $parser;
		$this->store = $mentionStore;
		$this->revisionStore = $revisionStore;
		$this->notifier = $notifier;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $mwParser, &$text, $stripState ) {
		$title = $mwParser->getTitle();
		if ( !$title || !$title->exists() || !$title->isContentPage() ) {
			return;
		}
		if ( $title->getNamespace() === NS_MEDIAWIKI ) {
			return;
		}

		$text = $text ?? '';
		$mentions = $this->parser->parse( $text );
		if ( !$mentions ) {
			return;
		}
		$replaced = [];
		foreach ( $mentions as $mention ) {
			if ( in_array( $mention['text'], $replaced ) ) {
				// Only replace every unique text once
				continue;
			}
			$user = $mention['user'];
			$text = str_replace( $mention['text'], $this->getMentionHtml( $user, $mention['label'] ), $text );
			$replaced[] = $mention['text'];
		}
	}

	/**
	 * @param User $user
	 * @param string $label
	 *
	 * @return string
	 */
	private function getMentionHtml( User $user, string $label ) {
		return \Html::rawElement( 'span', [
			'href' => $user->getUserPage()->getLinkURL(),
			'class' => 'at-mention',
			'style' => 'background-color: #e5e4ff; color: white; border: 1px solid #acaeff;' .
				'padding: 0 2px; border-radius: 2px;'
		], "[[User:{$user->getName()}|$label]]" );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( $wikiPage->getTitle()->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			// For now only support wikitext
			return;
		}
		$this->parseOnSave( $revisionRecord, $user );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity|null $user
	 * @param bool|null $compareToPrevious
	 *
	 * @return void
	 */
	protected function parseOnSave(
		RevisionRecord $revisionRecord, ?UserIdentity $user = null, ?bool $compareToPrevious = true
	) {
		if ( $user === null ) {
			$user = $revisionRecord->getUser();
		}
		$currentText = $this->getRevisionText( $revisionRecord );
		$oldText = '';
		if ( $compareToPrevious ) {
			$oldRev = $this->revisionStore->getPreviousRevision( $revisionRecord );
			if ( $oldRev ) {
				$oldText = $this->getRevisionText( $oldRev );
			}
		}
		$existing = $this->store->forPage(
			$this->getTitleFromLinkTarget( $revisionRecord->getPageAsLinkTarget() )
		)->query();
		$difference = $this->parser->getMentionUserDifference( $oldText, $currentText );
		foreach ( $difference['removed'] as $mentionedUser ) {
			if ( !$this->isMentioned( $mentionedUser, $existing ) ) {
				continue;
			}
			$this->store->removeMention(
				$mentionedUser, $this->getTitleFromLinkTarget( $revisionRecord->getPageAsLinkTarget() )
			);
		}
		foreach ( $difference['added'] as $mentionedUser ) {
			if ( $this->isMentioned( $mentionedUser, $existing ) ) {
				// User is re-mentioned
				$this->store->updateMention( $mentionedUser, $revisionRecord, $user );
			} else {
				$this->store->addMention( $mentionedUser, $revisionRecord, $user );
			}
			$this->emitEvent( $mentionedUser, $revisionRecord, $user );
		}
	}

	/**
	 * @param RevisionRecord $revision
	 *
	 * @return string
	 */
	private function getRevisionText( RevisionRecord $revision ): string {
		$content = $revision->getContent( SlotRecord::MAIN );
		return $content->getText();
	}

	/**
	 * @param UserIdentity $user
	 * @param array $existing
	 *
	 * @return bool
	 */
	private function isMentioned( UserIdentity $user, array $existing ) {
		/** @var Mention $mention */
		foreach ( $existing as $mention ) {
			if ( $mention->getUser()->getName() === $user->getName() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param UserIdentity $mentionedUser
	 * @param RevisionRecord $revisionRecord
	 * @param UserIdentity $actor
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function emitEvent( UserIdentity $mentionedUser, RevisionRecord $revisionRecord, UserIdentity $actor ) {
		$this->notifier->emit(
			new UserMention(
				$mentionedUser, $actor, $this->getTitleFromLinkTarget( $revisionRecord->getPageAsLinkTarget() )
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$this->store->removeMentionsForPageId( $pageID );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$latestRev = $this->revisionStore->getRevisionByTitle( $new );
		$this->store->moveMentions( $latestRev );
	}

	/**
	 * @inheritDoc
	 */
	public function onArticleUndelete( $title, $create, $comment, $oldPageId, $restoredPages ) {
		if ( $title->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			// For now only support wikitext
			return;
		}
		$revision = $this->revisionStore->getRevisionByTitle( $title );
		if ( !$revision ) {
			return;
		}
		$this->parseOnSave( $revision, null, false );
	}

	/**
	 * @param LinkTarget $linkTarget
	 *
	 * @return Title
	 */
	private function getTitleFromLinkTarget( LinkTarget $linkTarget ): Title {
		return $this->titleFactory->newFromLinkTarget( $linkTarget );
	}
}
