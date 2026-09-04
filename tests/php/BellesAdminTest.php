<?php
/**
 * Tests for the Belles moderation screens: the list table columns a moderator decides from, and
 * the edit screen that has to be able to correct a submission.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BellesAdminTest extends TestCase {

	private Basebelles_Belles $belles;

	protected function setUp(): void {
		Basebelles_Test_State::reset();
		Basebelles_Test_State::$is_admin = true;

		$this->belles = Basebelles_Belles::get_instance();

		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Seed one Belle.
	 *
	 * @param array $meta Meta key => value.
	 * @param string $status Post status.
	 * @return int
	 */
	private function add_belle( array $meta = array(), string $status = 'pending' ): int {
		return Basebelles_Test_State::add_post(
			array(
				'post_type'   => Basebelles_Belles::POST_TYPE,
				'post_status' => $status,
				'post_title'  => 'Mika Epstein',
			),
			$meta
		);
	}

	/**
	 * Capture one list table column.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private function column( string $column, int $post_id ): string {
		ob_start();
		$this->belles->admin_column_content( $column, $post_id );

		return (string) ob_get_clean();
	}

	/*
	 * -----------------------------------------------------------------------
	 * List table columns
	 * -----------------------------------------------------------------------
	 */

	/**
	 * A moderator should be able to approve or trash most submissions from the list without
	 * opening anything, which is what this column order is for.
	 */
	public function test_the_columns_put_the_moderation_signals_in_reading_order(): void {
		$columns = $this->belles->admin_columns(
			array(
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame(
			array( 'cb', 'bb_avatar', 'title', 'bb_location', 'bb_faves', 'date' ),
			array_keys( $columns )
		);
	}

	public function test_the_columns_are_relabelled_for_belles(): void {
		$columns = $this->belles->admin_columns(
			array(
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame( 'Belle', $columns['title'] );
		$this->assertSame( 'Submitted', $columns['date'] );
	}

	public function test_unrelated_columns_are_left_in_place(): void {
		$columns = $this->belles->admin_columns(
			array(
				'cb'       => '',
				'title'    => 'Title',
				'comments' => 'Comments',
				'date'     => 'Date',
			)
		);

		$this->assertArrayHasKey( 'comments', $columns );
	}

	public function test_the_avatar_column_shows_a_gravatar_thumbnail(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_EMAIL_HASH => str_repeat( 'b', 64 ) )
		);

		$html = $this->column( 'bb_avatar', $post_id );

		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 's=48', $html );
		$this->assertStringContainsString( str_repeat( 'b', 64 ), $html );
	}

	/** In the list a generated placeholder is more useful than an empty box. */
	public function test_the_avatar_column_falls_back_to_a_placeholder_image(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_EMAIL_HASH => str_repeat( 'b', 64 ) )
		);

		$this->assertStringContainsString( 'd=mp', $this->column( 'bb_avatar', $post_id ) );
	}

	public function test_the_avatar_column_handles_a_belle_with_no_hash(): void {
		$post_id = $this->add_belle();

		$this->assertSame( '—', $this->column( 'bb_avatar', $post_id ) );
	}

	public function test_the_location_column_shows_the_location(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_LOCATION => 'Cleveland, OH' )
		);

		$this->assertSame( 'Cleveland, OH', $this->column( 'bb_location', $post_id ) );
	}

	public function test_an_empty_column_reads_as_a_dash(): void {
		$post_id = $this->add_belle();

		$this->assertSame( '—', $this->column( 'bb_location', $post_id ) );
		$this->assertSame( '—', $this->column( 'bb_faves', $post_id ) );
	}

	public function test_the_favorites_column_shows_both_picks(): void {
		$post_id = $this->add_belle(
			array(
				Basebelles_Belles::META_CURRENT    => array(
					'name' => 'José Ramírez',
					'id'   => 608070,
				),
				Basebelles_Belles::META_HISTORICAL => 'Kenny Lofton',
			)
		);

		$html = $this->column( 'bb_faves', $post_id );

		$this->assertStringContainsString( 'José Ramírez', $html );
		$this->assertStringContainsString( 'Kenny Lofton', $html );
	}

	public function test_the_favorites_column_escapes_what_was_submitted(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_HISTORICAL => '<script>alert(1)</script>' )
		);

		$html = $this->column( 'bb_faves', $post_id );

		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The edit screen
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Submit the meta box.
	 *
	 * @param int   $post_id Post being saved.
	 * @param array $fields  Field name => value, without the bb_belle_ prefix.
	 * @return void
	 */
	private function save( int $post_id, array $fields ): void {
		$_POST = array( 'bb_belle_details_nonce' => 'nonce' );

		foreach ( $fields as $key => $value ) {
			$_POST[ 'bb_belle_' . $key ] = $value;
		}

		$this->belles->save_meta_box( $post_id, Basebelles_Test_State::$posts[ $post_id ] );
	}

	public function test_edits_are_stored(): void {
		$post_id = $this->add_belle();

		$this->save(
			$post_id,
			array(
				'email'      => 'mika@example.com',
				'location'   => 'Lakewood, OH',
				'current'    => 'Steven Kwan',
				'historical' => 'Omar Vizquel',
			)
		);

		$meta = Basebelles_Test_State::$meta[ $post_id ];

		$this->assertSame( 'mika@example.com', $meta[ Basebelles_Belles::META_EMAIL ] );
		$this->assertSame( 'Lakewood, OH', $meta[ Basebelles_Belles::META_LOCATION ] );
		$this->assertSame( 'Omar Vizquel', $meta[ Basebelles_Belles::META_HISTORICAL ] );
		$this->assertSame( 'Steven Kwan', $meta[ Basebelles_Belles::META_CURRENT ]['name'] );
	}

	public function test_a_corrected_player_name_picks_up_its_mlb_id(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_ROSTER_MAP ] = array(
			'steven kwan' => 663581,
		);

		$post_id = $this->add_belle();

		$this->save( $post_id, array( 'current' => 'Steven Kwan' ) );

		$this->assertSame(
			663581,
			Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_CURRENT ]['id']
		);
	}

	public function test_editing_the_email_rehashes_it(): void {
		$post_id = $this->add_belle();

		$this->save( $post_id, array( 'email' => 'Mika@Example.com' ) );

		$meta = Basebelles_Test_State::$meta[ $post_id ];

		$this->assertSame( 'mika@example.com', $meta[ Basebelles_Belles::META_EMAIL ] );
		$this->assertSame(
			hash( 'sha256', 'mika@example.com' ),
			$meta[ Basebelles_Belles::META_EMAIL_HASH ]
		);
	}

	public function test_a_changed_email_queues_a_fresh_avatar_lookup(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_EMAIL => 'old@example.com' )
		);

		$this->save( $post_id, array( 'email' => 'new@example.com' ) );

		$hooks = array_column( Basebelles_Test_State::$scheduled, 'hook' );

		$this->assertContains( 'bb_belles_check_gravatar', $hooks );
	}

	public function test_resaving_the_same_email_does_not_requeue_a_lookup(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_EMAIL => 'mika@example.com' )
		);

		$this->save( $post_id, array( 'email' => 'mika@example.com' ) );

		$hooks = array_column( Basebelles_Test_State::$scheduled, 'hook' );

		$this->assertNotContains( 'bb_belles_check_gravatar', $hooks );
	}

	public function test_an_invalid_email_does_not_overwrite_a_good_one(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_EMAIL => 'mika@example.com' )
		);

		$this->save( $post_id, array( 'email' => 'not an address' ) );

		$this->assertSame(
			'mika@example.com',
			Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_EMAIL ]
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Duplicate flag
	 *
	 * Correcting a typo'd address is the main reason a moderator edits a Belle, so the warning
	 * has to be able to clear — otherwise it sticks forever with no way to dismiss it.
	 * -----------------------------------------------------------------------
	 */

	public function test_correcting_a_duplicate_address_clears_the_warning(): void {
		$this->add_belle(
			array( Basebelles_Belles::META_EMAIL_HASH => hash( 'sha256', 'taken@example.com' ) ),
			'publish'
		);

		$post_id = $this->add_belle(
			array(
				Basebelles_Belles::META_EMAIL_HASH => hash( 'sha256', 'taken@example.com' ),
				Basebelles_Belles::META_DUPLICATE  => 1,
			)
		);

		$this->save( $post_id, array( 'email' => 'mine@example.com' ) );

		$this->assertArrayNotHasKey(
			Basebelles_Belles::META_DUPLICATE,
			Basebelles_Test_State::$meta[ $post_id ]
		);
	}

	public function test_editing_into_a_collision_raises_the_warning(): void {
		$this->add_belle(
			array( Basebelles_Belles::META_EMAIL_HASH => hash( 'sha256', 'taken@example.com' ) ),
			'publish'
		);

		$post_id = $this->add_belle();

		$this->save( $post_id, array( 'email' => 'taken@example.com' ) );

		$this->assertSame(
			1,
			Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_DUPLICATE ]
		);
	}

	/** A Belle must never be considered a duplicate of itself. */
	public function test_resaving_a_belle_does_not_flag_it_against_itself(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_EMAIL_HASH => hash( 'sha256', 'mika@example.com' ) )
		);

		$this->save( $post_id, array( 'email' => 'mika@example.com' ) );

		$this->assertArrayNotHasKey(
			Basebelles_Belles::META_DUPLICATE,
			Basebelles_Test_State::$meta[ $post_id ]
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Guards
	 * -----------------------------------------------------------------------
	 */

	/** Quick Edit and bulk edit post no nonce, and must leave the Belle's meta untouched. */
	public function test_a_save_without_the_nonce_changes_nothing(): void {
		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_LOCATION => 'Cleveland, OH' )
		);

		$_POST = array( 'bb_belle_location' => 'Somewhere Else' );

		$this->belles->save_meta_box( $post_id, Basebelles_Test_State::$posts[ $post_id ] );

		$this->assertSame(
			'Cleveland, OH',
			Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_LOCATION ]
		);
	}

	public function test_a_failed_nonce_check_changes_nothing(): void {
		Basebelles_Test_State::$nonce_valid = false;

		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_LOCATION => 'Cleveland, OH' )
		);

		$this->save( $post_id, array( 'location' => 'Somewhere Else' ) );

		$this->assertSame(
			'Cleveland, OH',
			Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_LOCATION ]
		);
	}

	public function test_a_user_without_permission_changes_nothing(): void {
		Basebelles_Test_State::$user_can = false;

		$post_id = $this->add_belle(
			array( Basebelles_Belles::META_LOCATION => 'Cleveland, OH' )
		);

		$this->save( $post_id, array( 'location' => 'Somewhere Else' ) );

		$this->assertSame(
			'Cleveland, OH',
			Basebelles_Test_State::$meta[ $post_id ][ Basebelles_Belles::META_LOCATION ]
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Consent record
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the meta box.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function meta_box( int $post_id ): string {
		ob_start();
		$this->belles->render_meta_box( Basebelles_Test_State::$posts[ $post_id ] );

		return (string) ob_get_clean();
	}

	public function test_given_consent_is_shown_with_its_timestamp(): void {
		$post_id = $this->add_belle(
			array(
				Basebelles_Belles::META_CONSENT      => 1,
				Basebelles_Belles::META_CONSENTED_AT => '2026-09-04 18:22:01',
				Basebelles_Belles::META_ENTRY_ID     => 9001,
			)
		);

		$html = $this->meta_box( $post_id );

		$this->assertStringContainsString( 'Given', $html );
		$this->assertStringContainsString( '2026-09-04 18:22:01 UTC', $html );
		$this->assertStringContainsString( 'entry #9001', $html );
	}

	public function test_withheld_consent_is_shown_as_withheld(): void {
		$post_id = $this->add_belle( array( Basebelles_Belles::META_CONSENT => 0 ) );

		$this->assertStringContainsString( 'Withheld', $this->meta_box( $post_id ) );
	}

	/** Belles submitted before the consent field existed must not read as refusals. */
	public function test_a_belle_with_no_consent_record_says_so(): void {
		$post_id = $this->add_belle();

		$html = $this->meta_box( $post_id );

		$this->assertStringContainsString( 'Not recorded', $html );
		$this->assertStringNotContainsString( 'Withheld', $html );
	}

	/**
	 * Evidence you can edit is not evidence, so the consent record gets no input field and no
	 * save path — correcting it means going back to the WPForms entry.
	 */
	public function test_the_consent_record_is_not_editable(): void {
		$post_id = $this->add_belle(
			array(
				Basebelles_Belles::META_CONSENT      => 1,
				Basebelles_Belles::META_CONSENTED_AT => '2026-09-04 18:22:01',
			)
		);

		$this->assertStringNotContainsString( 'name="bb_belle_consent"', $this->meta_box( $post_id ) );

		$this->save(
			$post_id,
			array(
				'consent'      => '0',
				'consented_at' => '1999-01-01 00:00:00',
			)
		);

		$meta = Basebelles_Test_State::$meta[ $post_id ];

		$this->assertSame( 1, $meta[ Basebelles_Belles::META_CONSENT ] );
		$this->assertSame( '2026-09-04 18:22:01', $meta[ Basebelles_Belles::META_CONSENTED_AT ] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Setup notice
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Capture the admin notice.
	 *
	 * @return string
	 */
	private function notice(): string {
		Basebelles_Test_State::$screen = (object) array( 'id' => 'edit-' . Basebelles_Belles::POST_TYPE );

		ob_start();
		$this->belles->admin_setup_notice();

		return (string) ob_get_clean();
	}

	public function test_an_unconnected_form_is_called_out(): void {
		$this->assertStringContainsString( 'No WPForms form is connected', $this->notice() );
	}

	public function test_a_connected_form_produces_no_notice(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_FORM_ID ] = 42;

		$this->assertSame( '', $this->notice() );
	}

	public function test_a_failing_roster_sync_is_surfaced_where_moderators_will_see_it(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_FORM_ID ]    = 42;
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_SYNC_STATUS ] = array(
			'time'    => time(),
			'ok'      => false,
			'message' => 'The MLB API request failed.',
		);

		$html = $this->notice();

		$this->assertStringContainsString( 'roster sync is failing', $html );
		$this->assertStringContainsString( 'The MLB API request failed.', $html );
	}

	/** An Editor cannot reach the settings screen, so pointing them at it is just noise. */
	public function test_users_who_cannot_fix_it_are_not_nagged(): void {
		Basebelles_Test_State::$user_can = false;

		$this->assertSame( '', $this->notice() );
	}

	public function test_the_notice_stays_on_the_belles_screen(): void {
		Basebelles_Test_State::$screen = (object) array( 'id' => 'edit-post' );

		ob_start();
		$this->belles->admin_setup_notice();

		$this->assertSame( '', (string) ob_get_clean() );
	}
}
