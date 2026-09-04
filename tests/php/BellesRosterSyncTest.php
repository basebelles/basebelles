<?php
/**
 * Tests for writing the Guardians roster into the sign-up form's choices.
 *
 * The reason this writes to the stored form at all — rather than filtering choices in at render
 * time — is that WPForms validates a submitted choice against an allowlist it reads straight
 * back out of the database. Render-only choices would display and then fail on submit. These
 * tests pin the write, and the read-modify-write that has to surround it.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BellesRosterSyncTest extends TestCase {

	private const FORM_ID  = 42;
	private const FIELD_ID = '4';

	protected function setUp(): void {
		Basebelles_Test_State::reset();

		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_FORM_ID ]      = self::FORM_ID;
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_ROSTER_FIELD ] = self::FIELD_ID;
		Basebelles_Test_State::$forms[ self::FORM_ID ]                           = Fixtures::wpforms_form( self::FIELD_ID );
		Basebelles_Test_State::$roster                                           = Fixtures::roster();
	}

	/**
	 * Run the sync.
	 *
	 * @return true|WP_Error
	 */
	private function sync() {
		return Basebelles_Belles::get_instance()->sync_roster_choices();
	}

	/**
	 * The choices now stored on the roster field.
	 *
	 * @return array
	 */
	private function stored_choices(): array {
		return Basebelles_Test_State::$forms[ self::FORM_ID ]['fields'][ self::FIELD_ID ]['choices'] ?? array();
	}

	/*
	 * -----------------------------------------------------------------------
	 * The write
	 * -----------------------------------------------------------------------
	 */

	public function test_a_sync_succeeds(): void {
		$this->assertTrue( $this->sync() );
	}

	/**
	 * Keys are 1-indexed to match what WPForms' own field defaults look like, so the form
	 * builder sees nothing unusual.
	 */
	public function test_the_roster_becomes_one_indexed_choices(): void {
		$this->sync();

		$this->assertSame(
			array(
				1 => array(
					'label' => 'Bo Naylor',
					'value' => '',
					'image' => '',
				),
				2 => array(
					'label' => 'José Ramírez',
					'value' => '',
					'image' => '',
				),
				3 => array(
					'label' => 'Steven Kwan',
					'value' => '',
					'image' => '',
				),
			),
			$this->stored_choices()
		);
	}

	public function test_stale_choices_are_replaced_not_appended(): void {
		$this->sync();

		$labels = array_column( $this->stored_choices(), 'label' );

		$this->assertNotContains( 'Somebody Stale', $labels );
		$this->assertCount( 3, $labels );
	}

	/**
	 * With show_values off, the label doubles as the submitted value — which is why intake
	 * matches players by name and why the label has to be the bare name.
	 */
	public function test_labels_are_bare_player_names(): void {
		$this->sync();

		foreach ( $this->stored_choices() as $choice ) {
			$this->assertMatchesRegularExpression( '/^[^(\d]+$/u', $choice['label'] );
		}
	}

	/**
	 * update() replaces the entire form, so anything the sync fails to carry forward is lost.
	 * This is the guard on that.
	 */
	public function test_the_rest_of_the_form_survives_the_write(): void {
		$this->sync();

		$form = Basebelles_Test_State::$forms[ self::FORM_ID ];

		$this->assertSame( 'Be a Belle', $form['settings']['form_title'] );
		$this->assertArrayHasKey( '2', $form['fields'] );
		$this->assertSame( 'email', $form['fields']['2']['type'] );
		$this->assertSame( 'Favorite Current Player', $form['fields'][ self::FIELD_ID ]['label'] );
	}

	/** The sync runs on cron, where there is no logged-in user to hold a capability. */
	public function test_the_write_bypasses_the_capability_check(): void {
		$this->sync();

		$this->assertCount( 1, Basebelles_Test_State::$form_updates );
		$this->assertFalse( Basebelles_Test_State::$form_updates[0]['args']['cap'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The roster map
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Intake looks players up by lowercased name to attach an MLB ID, so the map the sync
	 * leaves behind is what makes that possible.
	 */
	public function test_a_lowercased_name_to_id_map_is_stored(): void {
		$this->sync();

		$this->assertSame(
			array(
				'bo naylor'    => 680757,
				'josé ramírez' => 608070,
				'steven kwan'  => 663581,
			),
			Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_ROSTER_MAP ]
		);
	}

	public function test_the_map_is_not_written_when_the_sync_fails(): void {
		Basebelles_Test_State::$form_update_fails = true;

		$this->sync();

		$this->assertArrayNotHasKey(
			Basebelles_Belles::OPTION_ROSTER_MAP,
			Basebelles_Test_State::$options
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Failures
	 *
	 * A sync that has been quietly failing for weeks looks exactly like one that is working, so
	 * every path has to record why it stopped.
	 * -----------------------------------------------------------------------
	 */

	public function test_an_unconfigured_form_is_an_error(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_FORM_ID ] = 0;

		$result = $this->sync();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'basebelles_belles_unconfigured', $result->get_error_code() );
	}

	public function test_an_unconfigured_field_is_an_error(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_ROSTER_FIELD ] = '';

		$result = $this->sync();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'basebelles_belles_unconfigured', $result->get_error_code() );
	}

	public function test_wpforms_being_gone_is_an_error(): void {
		Basebelles_Test_State::$wpforms = null;

		$result = $this->sync();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'basebelles_belles_no_wpforms', $result->get_error_code() );
	}

	public function test_an_api_failure_is_passed_straight_through(): void {
		Basebelles_Test_State::$roster = new WP_Error( 'basebelles_api_http', 'The MLB API request failed.' );

		$result = $this->sync();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'basebelles_api_http', $result->get_error_code() );
	}

	/** Somebody deleted the field in the builder; the sync must say so rather than half-write. */
	public function test_a_missing_field_is_an_error(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_ROSTER_FIELD ] = '99';

		$result = $this->sync();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'basebelles_belles_no_field', $result->get_error_code() );
	}

	public function test_a_refused_save_is_an_error(): void {
		Basebelles_Test_State::$form_update_fails = true;

		$result = $this->sync();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'basebelles_belles_update_failed', $result->get_error_code() );
	}

	public function test_nothing_is_written_when_the_roster_cannot_be_fetched(): void {
		Basebelles_Test_State::$roster = new WP_Error( 'basebelles_api_http', 'boom' );

		$this->sync();

		$this->assertSame( array(), Basebelles_Test_State::$form_updates );
		$this->assertSame( 'Somebody Stale', $this->stored_choices()[1]['label'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Recorded status
	 * -----------------------------------------------------------------------
	 */

	public function test_a_successful_sync_is_recorded(): void {
		$this->sync();

		$status = Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_SYNC_STATUS ];

		$this->assertTrue( $status['ok'] );
		$this->assertSame( '', $status['message'] );
		$this->assertGreaterThan( 0, $status['time'] );
	}

	public function test_a_failed_sync_records_the_reason(): void {
		Basebelles_Test_State::$roster = new WP_Error( 'basebelles_api_http', 'The MLB API request failed.' );

		$this->sync();

		$status = Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_SYNC_STATUS ];

		$this->assertFalse( $status['ok'] );
		$this->assertSame( 'The MLB API request failed.', $status['message'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Scheduling
	 * -----------------------------------------------------------------------
	 */

	public function test_the_daily_sync_is_scheduled_in_the_admin(): void {
		Basebelles_Test_State::$is_admin = true;

		new Basebelles_Belles();

		$hooks = array_column( Basebelles_Test_State::$scheduled, 'hook' );

		$this->assertContains( 'bb_belles_sync_roster', $hooks );
		$this->assertSame( 'daily', Basebelles_Test_State::$scheduled[0]['recurring'] );
	}

	/** Front-end requests should not pay for a cron lookup on every page view. */
	public function test_front_end_requests_do_not_touch_the_schedule(): void {
		Basebelles_Test_State::$is_admin = false;

		new Basebelles_Belles();

		$this->assertSame( array(), Basebelles_Test_State::$scheduled );
	}

	public function test_the_sync_is_not_scheduled_twice(): void {
		Basebelles_Test_State::$is_admin = true;

		new Basebelles_Belles();
		new Basebelles_Belles();

		$hooks = array_filter(
			array_column( Basebelles_Test_State::$scheduled, 'hook' ),
			static function ( $hook ) {
				return 'bb_belles_sync_roster' === $hook;
			}
		);

		$this->assertCount( 1, $hooks );
	}
}
