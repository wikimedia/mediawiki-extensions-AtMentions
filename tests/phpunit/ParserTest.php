<?php

namespace AtMentions\Tests;

use AtMentions\MentionParser;
use MediaWiki\Language\Language;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {

	/**
	 * @covers \AtMentions\MentionParser::parse
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testParse() {
		$text = $this->getText( 'rev1' );
		$parser = $this->getParser();

		$mentions = $parser->parse( $text );
		// Plain parse will return all occurrences, even duplicates
		$this->assertCount( 5, $mentions );

		$this->assertSame( '[[User:A]]', $mentions[0]['text'] );
		$this->assertSame( 'A', $mentions[0]['user']->getName() );
		$this->assertSame( 'A real', $mentions[0]['label'] );

		$this->assertSame( '[[User:Z]]', $mentions[1]['text'] );
		$this->assertSame( 'Z', $mentions[1]['user']->getName() );
		$this->assertSame( 'Z real', $mentions[1]['label'] );

		$this->assertSame( '[[user:B|UserB]]', $mentions[2]['text'] );
		$this->assertSame( 'B', $mentions[2]['user']->getName() );
		$this->assertSame( 'UserB', $mentions[2]['label'] );

		$this->assertSame( '[[User:C]]', $mentions[3]['text'] );
		$this->assertSame( 'C', $mentions[3]['user']->getName() );
		$this->assertSame( 'C real', $mentions[3]['label'] );

		$this->assertSame( '[[User:C|UserC]]', $mentions[4]['text'] );
		$this->assertSame( 'C', $mentions[4]['user']->getName() );
		$this->assertSame( 'UserC', $mentions[4]['label'] );
	}

	/**
	 * @covers \AtMentions\MentionParser::getMentionedUsers
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testGetMentionedUsers() {
		$text = $this->getText( 'rev1' );
		$parser = $this->getParser();
		$users = $parser->getMentionedUsers( $text );

		$this->assertCount( 4, $users );
		$this->assertSame( 'A', $users[0]->getName() );
		$this->assertSame( 'Z', $users[1]->getName() );
		$this->assertSame( 'B', $users[2]->getName() );
		$this->assertSame( 'C', $users[3]->getName() );
	}

	/**
	 * @param string $old
	 * @param string $new
	 * @param array $expected
	 *
	 * @covers \AtMentions\MentionParser::getMentionUserDifference()
	 * @dataProvider provideData
	 *
	 * @return void
	 */
	public function testGetMentionUserDifference( $old, $new, $expected ) {
		$parser = $this->getParser();
		$diff = $parser->getMentionUserDifference( $old, $new );
		$this->adaptForTest( $diff );
		$this->assertSame( $expected, $diff );
	}

	/**
	 * @return array[]
	 * @throws \Exception
	 */
	public function provideData() {
		return [
			[
				$this->getText( 'rev1' ),
				$this->getText( 'rev2' ),
				[
					// "B" is now mentioned twice, so re-mentioned
					'added' => [ 'B', 'D' ],
					'removed' => [ 'A', 'Z', 'C' ]
				]
			],
			[
				$this->getText( 'rev2' ),
				$this->getText( 'rev2' ),
				[
					'added' => [],
					'removed' => []
				]
			],
			[
				'',
				$this->getText( 'rev1' ),
				[
					'added' => [ 'A', 'Z', 'B', 'C' ],
					'removed' => []
				]
			],
			[
				$this->getText( 'rev1' ),
				'',
				[
					'added' => [],
					'removed' => [ 'A', 'Z', 'B', 'C' ]
				]
			]
		];
	}

	/**
	 * @param string $filename
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function getText( string $filename ): string {
		$base = __DIR__ . '/data/';
		$filename = $base . $filename . '.txt';
		if ( !file_exists( $filename ) ) {
			throw new \Exception( "File $filename does not exist" );
		}
		return file_get_contents( $filename );
	}

	/**
	 * @return MentionParser
	 */
	private function getParser(): MentionParser {
		$ufMock = $this->getMockBuilder( UserFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$ufMock->method( 'newFromName' )->willReturnCallback( function ( $name ) {
			$user = $this->getMockBuilder( User::class )
				->disableOriginalConstructor()
				->getMock();
			$ids = [ 'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'Z' => 5, 'Y' => 6, 'X' => 0 ];
			$user->method( 'getId' )->willReturn( $ids[$name] );
			$user->method( 'isRegistered' )->willReturn( $name !== 'X' );
			$user->method( 'getName' )->willReturn( $name );
			$user->method( 'getRealName' )->willReturn( $name . ' real' );
			return $user;
		} );
		$langMock = $this->getMockBuilder( Language::class )
			->disableOriginalConstructor()
			->getMock();
		$langMock->method( 'getNsText' )->willReturn( 'User' );
		return new MentionParser( $ufMock, $langMock );
	}

	/**
	 * Simplify the user objects for testing
	 *
	 * @param array &$diff
	 *
	 * @return void
	 */
	private function adaptForTest( array &$diff ) {
		foreach ( $diff as $key => $value ) {
			if ( $key === 'added' || $key === 'removed' ) {
				$diff[$key] = array_map( static function ( $user ) {
					return $user->getName();
				}, $value );
			}
		}
	}
}
