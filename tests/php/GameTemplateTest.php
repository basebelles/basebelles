<?php
/**
 * Tests for Basebelles_Game_Template::hierarchy() (features/class-game-template.php).
 *
 * Only the pure hierarchy logic is tested; registering the template needs WordPress.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class GameTemplateTest extends TestCase {

	private const POST_ID = 42;

	private const WP_HIERARCHY = array( 'single-post-triumph-over-the-south-side.php', 'single-post.php', 'single.php' );

	protected function setUp(): void {
		Basebelles_Test_State::reset();
	}

	private static function in_category( string ...$slugs ): void {
		Basebelles_Test_State::$categories[ self::POST_ID ] = array_map(
			static function ( $slug ) {
				return (object) array(
					'term_id' => crc32( $slug ),
					'name'    => ucfirst( $slug ),
					'slug'    => $slug,
				);
			},
			$slugs
		);
	}

	public function test_game_post_gets_game_template_after_its_own_and_before_generic(): void {
		self::in_category( 'games' );

		$this->assertSame(
			array( 'single-post-triumph-over-the-south-side.php', 'single-game.php', 'single-post.php', 'single.php' ),
			Basebelles_Game_Template::hierarchy( self::WP_HIERARCHY, self::POST_ID )
		);
	}

	public function test_game_post_with_other_categories_still_counts(): void {
		self::in_category( 'news', 'games' );

		$this->assertContains( 'single-game.php', Basebelles_Game_Template::hierarchy( self::WP_HIERARCHY, self::POST_ID ) );
	}

	public function test_other_posts_are_left_alone(): void {
		self::in_category( 'off-days' );

		$this->assertSame( self::WP_HIERARCHY, Basebelles_Game_Template::hierarchy( self::WP_HIERARCHY, self::POST_ID ) );
	}

	public function test_post_without_categories_is_left_alone(): void {
		$this->assertSame( self::WP_HIERARCHY, Basebelles_Game_Template::hierarchy( self::WP_HIERARCHY, self::POST_ID ) );
	}

	public function test_falls_back_to_before_single_then_to_the_end(): void {
		self::in_category( 'games' );

		$this->assertSame(
			array( 'single-game.php', 'single.php' ),
			Basebelles_Game_Template::hierarchy( array( 'single.php' ), self::POST_ID )
		);
		$this->assertSame(
			array( 'something.php', 'single-game.php' ),
			Basebelles_Game_Template::hierarchy( array( 'something.php' ), self::POST_ID )
		);
	}

	public function test_not_added_twice(): void {
		self::in_category( 'games' );

		$once = Basebelles_Game_Template::hierarchy( self::WP_HIERARCHY, self::POST_ID );

		$this->assertSame( $once, Basebelles_Game_Template::hierarchy( $once, self::POST_ID ) );
	}
}
