<?php
/**
 * Tests for Belle avatars: the monogram fallback, Gravatar URLs, and the cached "has an avatar"
 * lookup that decides between them.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BellesAvatarTest extends TestCase {

	private const HASH = 'b1b3773a05c0ed0176787a4f1574ff0075f7521e2b4ce6e2a5b1e5a9b0e5a0f1';

	protected function setUp(): void {
		Basebelles_Test_State::reset();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Monogram
	 * -----------------------------------------------------------------------
	 */

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function initials_cases(): array {
		return array(
			'first and last'        => array( 'Mika Epstein', 'ME' ),
			'one name only'         => array( 'Cher', 'C' ),
			'middle name is skipped' => array( 'Mary Jane Watson', 'MW' ),
			'extra whitespace'      => array( '   ramona    flowers  ', 'RF' ),
			'accented characters'   => array( 'José Ramírez', 'JR' ),
			'markup is stripped'    => array( '<b>Bea</b> Arthur', 'BA' ),
			'empty name'            => array( '', '?' ),
			'whitespace only'       => array( '   ', '?' ),
		);
	}

	#[DataProvider( 'initials_cases' )]
	public function test_initials_are_derived_from_the_name( string $name, string $expected ): void {
		$this->assertSame( $expected, Basebelles_Belles::get_initials( $name ) );
	}

	public function test_the_monogram_colour_is_stable_for_a_given_name(): void {
		$first = Basebelles_Belles::get_monogram_color( 'Mika Epstein' );

		$this->assertSame( $first, Basebelles_Belles::get_monogram_color( 'Mika Epstein' ) );
	}

	public function test_the_monogram_colour_always_comes_from_the_team_palette(): void {
		$palette = array(
			'var(--bb-deep-navy)',
			'var(--bb-wine-red)',
			'var(--bb-slate-blue)',
			'var(--bb-accent-slate)',
		);

		foreach ( array( 'Mika', 'Bea Arthur', 'José Ramírez', '', 'zzz', '42' ) as $seed ) {
			$this->assertContains( Basebelles_Belles::get_monogram_color( $seed ), $palette );
		}
	}

	/** A single colour for everyone would make the grid look broken rather than intentional. */
	public function test_different_names_do_not_all_land_on_one_colour(): void {
		$names = array( 'Alice A', 'Bob B', 'Carol C', 'Dave D', 'Erin E', 'Frank F', 'Grace G', 'Heidi H' );
		$seen  = array();

		foreach ( $names as $name ) {
			$seen[ Basebelles_Belles::get_monogram_color( $name ) ] = true;
		}

		$this->assertGreaterThan( 1, count( $seen ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Gravatar URLs
	 * -----------------------------------------------------------------------
	 */

	public function test_a_gravatar_url_is_built_from_the_hash(): void {
		$this->assertSame(
			'https://gravatar.com/avatar/' . self::HASH . '?s=160&d=blank',
			Basebelles_Belles::gravatar_url( self::HASH )
		);
	}

	public function test_size_and_default_are_configurable(): void {
		$this->assertSame(
			'https://gravatar.com/avatar/' . self::HASH . '?s=48&d=mp',
			Basebelles_Belles::gravatar_url( self::HASH, 48, 'mp' )
		);
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function bad_hash_cases(): array {
		return array(
			'empty'             => array( '' ),
			'md5 length'        => array( 'd41d8cd98f00b204e9800998ecf8427e' ),
			'uppercase hex'     => array( 'B1B3773A05C0ED0176787A4F1574FF0075F7521E2B4CE6E2A5B1E5A9B0E5A0F1' ),
			'not hex'           => array( str_repeat( 'z', 64 ) ),
			'a raw email'       => array( 'mika@example.com' ),
			'path traversal'    => array( '../../etc/passwd' ),
		);
	}

	/**
	 * The hash is validated before it reaches a URL, so nothing a submitter controls can steer
	 * the request somewhere else.
	 */
	#[DataProvider( 'bad_hash_cases' )]
	public function test_a_malformed_hash_produces_no_url( $hash ): void {
		$this->assertSame( '', Basebelles_Belles::gravatar_url( $hash ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The cached lookup
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Seed a Belle carrying an email hash.
	 *
	 * @param string $hash Email hash, or an empty string for none.
	 * @param array  $meta Extra meta.
	 * @return int
	 */
	private function seed_belle( string $hash = self::HASH, array $meta = array() ): int {
		return Basebelles_Test_State::add_post(
			array(
				'post_type'   => Basebelles_Belles::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Mika Epstein',
			),
			array_merge(
				'' === $hash ? array() : array( Basebelles_Belles::META_EMAIL_HASH => $hash ),
				$meta
			)
		);
	}

	/** d=404 is the only way to tell "has an avatar" from "was given a placeholder". */
	public function test_the_lookup_asks_gravatar_for_a_404_rather_than_a_placeholder(): void {
		$post_id = $this->seed_belle();

		Basebelles_Belles::get_instance()->refresh_gravatar_flag( $post_id );

		$this->assertCount( 1, Basebelles_Test_State::$http_requests );
		$this->assertStringContainsString( 'd=404', Basebelles_Test_State::$http_requests[0] );
		$this->assertStringContainsString( self::HASH, Basebelles_Test_State::$http_requests[0] );
	}

	public function test_a_200_records_that_the_person_has_an_avatar(): void {
		$post_id = $this->seed_belle();

		$result = Basebelles_Belles::get_instance()->refresh_gravatar_flag( $post_id );

		$this->assertTrue( $result );
		$this->assertSame( 1, Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_GRAVATAR ] );
	}

	public function test_a_404_records_that_they_do_not(): void {
		$post_id = $this->seed_belle();

		Basebelles_Test_State::$http_response = array( 'response' => array( 'code' => 404 ) );

		$result = Basebelles_Belles::get_instance()->refresh_gravatar_flag( $post_id );

		$this->assertFalse( $result );
		$this->assertSame( 0, Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_GRAVATAR ] );
	}

	/**
	 * A network blip must not demote someone who does have an avatar — the previous answer
	 * stands until a request actually succeeds.
	 */
	public function test_a_failed_request_leaves_the_previous_answer_alone(): void {
		$post_id = $this->seed_belle( self::HASH, array( Basebelles_Belles::META_GRAVATAR => 1 ) );

		Basebelles_Test_State::$http_response = new WP_Error( 'http_request_failed', 'timeout' );

		$result = Basebelles_Belles::get_instance()->refresh_gravatar_flag( $post_id );

		$this->assertTrue( $result );
		$this->assertSame( 1, Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_GRAVATAR ] );
	}

	public function test_a_belle_with_no_email_hash_is_never_looked_up(): void {
		$post_id = $this->seed_belle( '' );

		$result = Basebelles_Belles::get_instance()->refresh_gravatar_flag( $post_id );

		$this->assertFalse( $result );
		$this->assertSame( array(), Basebelles_Test_State::$http_requests );
		$this->assertSame( 0, Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_GRAVATAR ] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Approval queues the lookup
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Publishing must not block on gravatar.com, so the lookup is deferred to a single cron
	 * event instead of running inline.
	 */
	public function test_approving_a_belle_queues_the_lookup_instead_of_running_it(): void {
		$post_id = $this->seed_belle();
		$post    = Basebelles_Test_State::$posts[ $post_id ];

		Basebelles_Belles::get_instance()->maybe_refresh_gravatar_flag( 'publish', 'pending', $post );

		$this->assertSame( array(), Basebelles_Test_State::$http_requests );
		$this->assertCount( 1, Basebelles_Test_State::$scheduled );
		$this->assertSame( 'bb_belles_check_gravatar', Basebelles_Test_State::$scheduled[0]['hook'] );
		$this->assertSame( array( $post_id ), Basebelles_Test_State::$scheduled[0]['args'] );
	}

	public function test_the_lookup_is_not_queued_twice_for_the_same_belle(): void {
		$post_id = $this->seed_belle();
		$post    = Basebelles_Test_State::$posts[ $post_id ];
		$belles  = Basebelles_Belles::get_instance();

		$belles->maybe_refresh_gravatar_flag( 'publish', 'pending', $post );
		$belles->maybe_refresh_gravatar_flag( 'publish', 'draft', $post );

		$this->assertCount( 1, Basebelles_Test_State::$scheduled );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function non_approval_transitions(): array {
		return array(
			'still pending'        => array( 'pending', 'draft' ),
			'trashed as spam'      => array( 'trash', 'pending' ),
			'already published'    => array( 'publish', 'publish' ),
			'unpublished to draft' => array( 'draft', 'publish' ),
		);
	}

	#[DataProvider( 'non_approval_transitions' )]
	public function test_only_becoming_published_queues_a_lookup( string $new, string $old ): void {
		$post_id = $this->seed_belle();
		$post    = Basebelles_Test_State::$posts[ $post_id ];

		Basebelles_Belles::get_instance()->maybe_refresh_gravatar_flag( $new, $old, $post );

		$this->assertSame( array(), Basebelles_Test_State::$scheduled );
	}

	public function test_other_post_types_are_left_alone(): void {
		$post_id = Basebelles_Test_State::add_post( array( 'post_type' => 'post' ) );

		Basebelles_Belles::get_instance()->maybe_refresh_gravatar_flag(
			'publish',
			'pending',
			Basebelles_Test_State::$posts[ $post_id ]
		);

		$this->assertSame( array(), Basebelles_Test_State::$scheduled );
	}

	/*
	 * -----------------------------------------------------------------------
	 * What the directory is handed
	 * -----------------------------------------------------------------------
	 */

	public function test_a_belle_with_an_avatar_gets_a_gravatar_url(): void {
		$this->seed_belle( self::HASH, array( Basebelles_Belles::META_GRAVATAR => 1 ) );

		$belles = Basebelles_Belles::get_published_belles();

		$this->assertSame(
			'https://gravatar.com/avatar/' . self::HASH . '?s=160&d=blank',
			$belles[0]['gravatar']
		);
	}

	/**
	 * No flag means no avatar, and no avatar means no image request at all — the monogram
	 * carries the card on its own.
	 */
	public function test_a_belle_without_one_gets_no_url(): void {
		$this->seed_belle( self::HASH, array( Basebelles_Belles::META_GRAVATAR => 0 ) );

		$belles = Basebelles_Belles::get_published_belles();

		$this->assertSame( '', $belles[0]['gravatar'] );
	}

	public function test_a_belle_never_checked_gets_no_url(): void {
		$this->seed_belle();

		$belles = Basebelles_Belles::get_published_belles();

		$this->assertSame( '', $belles[0]['gravatar'] );
	}
}
