<?php
/**
 * Tests for turning a WPForms submission into a pending Belle.
 *
 * These go through the real hook callback rather than poking at the private mapping methods, so
 * what they assert is what actually lands in post meta.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BellesIntakeTest extends TestCase {

	private const FORM_ID = 42;

	private Basebelles_Belles $belles;

	protected function setUp(): void {
		Basebelles_Test_State::reset();
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_FORM_ID ] = self::FORM_ID;

		$this->belles = Basebelles_Belles::get_instance();
	}

	/**
	 * Run a submission through the intake hook.
	 *
	 * @param array $fields  Submitted fields, as Fixtures::entry() builds them.
	 * @param int   $form_id Form the submission came from.
	 * @return void
	 */
	private function submit( array $fields, int $form_id = self::FORM_ID ): void {
		$this->belles->create_belle_from_entry(
			$fields,
			array(),
			array( 'id' => $form_id ),
			9001
		);
	}

	/**
	 * The single Belle in the store, or null when intake declined to create one.
	 *
	 * @return object|null
	 */
	private function belle(): ?object {
		foreach ( Basebelles_Test_State::$posts as $post ) {
			if ( Basebelles_Belles::POST_TYPE === $post->post_type ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Read one meta value off the created Belle.
	 *
	 * @param string $key Meta key.
	 * @return mixed
	 */
	private function meta( string $key ) {
		$belle = $this->belle();

		return null === $belle ? null : ( Basebelles_Test_State::$meta[ $belle->ID ][ $key ] ?? null );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The happy path
	 * -----------------------------------------------------------------------
	 */

	public function test_a_submission_becomes_a_belle_awaiting_review(): void {
		$this->submit( Fixtures::entry() );

		$belle = $this->belle();

		$this->assertNotNull( $belle );
		$this->assertSame( 'pending', $belle->post_status );
		$this->assertSame( 'Mika Epstein', $belle->post_title );
	}

	public function test_every_submitted_field_is_stored(): void {
		$this->submit( Fixtures::entry() );

		$this->assertSame( 'mika@example.com', $this->meta( Basebelles_Belles::META_EMAIL ) );
		$this->assertSame( 'Cleveland, OH, USA', $this->meta( Basebelles_Belles::META_LOCATION ) );
		$this->assertSame( 'Kenny Lofton', $this->meta( Basebelles_Belles::META_HISTORICAL ) );
		$this->assertSame( 9001, $this->meta( Basebelles_Belles::META_ENTRY_ID ) );
	}

	/**
	 * Addresses are lowercased before hashing, so the same person submitting as Mika@ or mika@
	 * resolves to one Gravatar and one duplicate check.
	 */
	public function test_the_email_is_lowercased_and_hashed_with_sha256(): void {
		$this->submit( Fixtures::entry() );

		$this->assertSame(
			hash( 'sha256', 'mika@example.com' ),
			$this->meta( Basebelles_Belles::META_EMAIL_HASH )
		);
	}

	public function test_the_raw_address_is_stored_lowercased(): void {
		$this->submit( Fixtures::entry( array( 'Email Address' => '  Mika@Example.com ' ) ) );

		$this->assertSame( 'mika@example.com', $this->meta( Basebelles_Belles::META_EMAIL ) );
	}

	/** Reserved for linking a Belle to a real WP user later; every Belle starts unlinked. */
	public function test_the_user_link_starts_empty(): void {
		$this->submit( Fixtures::entry() );

		$this->assertSame( 0, $this->meta( Basebelles_Belles::META_USER_ID ) );
	}

	public function test_a_creation_action_fires_for_other_code_to_hook(): void {
		$this->submit( Fixtures::entry() );

		$tags = array_column( Basebelles_Test_State::$actions, 0 );

		$this->assertContains( 'basebelles_belle_created', $tags );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The current player
	 * -----------------------------------------------------------------------
	 */

	public function test_the_current_player_gains_its_mlb_id_from_the_synced_roster(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_ROSTER_MAP ] = array(
			'josé ramírez' => 608070,
		);

		$this->submit( Fixtures::entry() );

		$this->assertSame(
			array(
				'name' => 'José Ramírez',
				'id'   => 608070,
			),
			$this->meta( Basebelles_Belles::META_CURRENT )
		);
	}

	/**
	 * The ID is a convenience for grouping later, not a requirement, so a name the roster does
	 * not recognise still has to store.
	 */
	public function test_a_player_missing_from_the_roster_map_stores_with_no_id(): void {
		$this->submit( Fixtures::entry( array( 'Favorite Current Player' => 'Rocky Colavito' ) ) );

		$this->assertSame(
			array(
				'name' => 'Rocky Colavito',
				'id'   => 0,
			),
			$this->meta( Basebelles_Belles::META_CURRENT )
		);
	}

	public function test_no_player_selected_stores_an_empty_pick(): void {
		$this->submit( Fixtures::entry( array( 'Favorite Current Player' => '' ) ) );

		$this->assertSame(
			array(
				'name' => '',
				'id'   => 0,
			),
			$this->meta( Basebelles_Belles::META_CURRENT )
		);
	}

	/**
	 * The field is a single-select dropdown, but a checkbox field would newline-join its
	 * selections. Only the first survives, so the directory can never show two names.
	 */
	public function test_only_the_first_selection_is_kept_if_a_field_returns_several(): void {
		$this->submit(
			Fixtures::entry(
				array( 'Favorite Current Player' => "Steven Kwan\nBo Naylor\nJosé Ramírez" )
			)
		);

		$this->assertSame( 'Steven Kwan', $this->meta( Basebelles_Belles::META_CURRENT )['name'] );
	}

	/**
	 * WPForms hands back an array for a few field types. Casting that to string would store the
	 * literal word "Array".
	 */
	public function test_an_array_value_does_not_become_the_word_array(): void {
		$fields = Fixtures::entry();

		foreach ( $fields as $id => $field ) {
			if ( 'Favorite Current Player' === $field['name'] ) {
				$fields[ $id ]['value'] = array( 'Steven Kwan', 'Bo Naylor' );
			}
		}

		$this->submit( $fields );

		$this->assertSame( 'Steven Kwan', $this->meta( Basebelles_Belles::META_CURRENT )['name'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Label matching
	 *
	 * Fields are matched on their labels so the form can be reworded in the WPForms builder
	 * without a code change. This is the table that keeps that promise honest.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function label_cases(): array {
		return array(
			'plain location'          => array( 'Location', Basebelles_Belles::META_LOCATION ),
			'question phrasing'       => array( 'Where are you?', Basebelles_Belles::META_LOCATION ),
			'city and state'          => array( 'City/State', Basebelles_Belles::META_LOCATION ),
			'hometown'                => array( 'Hometown', Basebelles_Belles::META_LOCATION ),
			'where from'              => array( 'Where from?', Basebelles_Belles::META_LOCATION ),
			// "Current City" contains "current", so location has to win the tie.
			'current city'            => array( 'Current City', Basebelles_Belles::META_LOCATION ),
			'historical'              => array( 'Favorite Historical Player', Basebelles_Belles::META_HISTORICAL ),
			'all-time'                => array( 'All-Time Favorite', Basebelles_Belles::META_HISTORICAL ),
			'retired'                 => array( 'Favorite Retired Player', Basebelles_Belles::META_HISTORICAL ),
		);
	}

	#[DataProvider( 'label_cases' )]
	public function test_labels_are_matched_loosely( string $label, string $expected_meta ): void {
		$this->submit(
			Fixtures::entry(
				array(
					'Location'                   => null,
					'Favorite Historical Player' => null,
					'Favorite Current Player'    => null,
					$label                       => 'Somewhere Specific',
				)
			)
		);

		$this->assertSame( 'Somewhere Specific', $this->meta( $expected_meta ) );
	}

	public function test_a_current_player_label_is_not_mistaken_for_a_location(): void {
		$this->submit(
			Fixtures::entry(
				array(
					'Location'                => null,
					'Favorite Current Player' => 'Steven Kwan',
				)
			)
		);

		$this->assertSame( 'Steven Kwan', $this->meta( Basebelles_Belles::META_CURRENT )['name'] );
		$this->assertSame( '', $this->meta( Basebelles_Belles::META_LOCATION ) );
	}

	public function test_a_player_label_is_never_read_as_the_persons_name(): void {
		// "Favorite Historical Player" contains no "name", but "Player Name" does.
		$this->submit(
			Fixtures::entry(
				array(
					'Your Name'                  => null,
					'Favorite Historical Player' => null,
					'Favorite Player Name'       => 'Omar Vizquel',
				)
			)
		);

		$belle = $this->belle();

		$this->assertNotNull( $belle );
		$this->assertNotSame( 'Omar Vizquel', $belle->post_title );
	}

	/**
	 * The GDPR checkbox submits the consent sentence itself as its value, which is long and
	 * full of ordinary words. It must not be mistaken for a name or a location.
	 */
	public function test_the_consent_sentence_never_lands_in_another_field(): void {
		$this->submit( Fixtures::entry() );

		$belle = $this->belle();

		$this->assertNotNull( $belle );
		$this->assertSame( 'Mika Epstein', $belle->post_title );
		$this->assertSame( 'Cleveland, OH, USA', $this->meta( Basebelles_Belles::META_LOCATION ) );
		$this->assertSame( 'Kenny Lofton', $this->meta( Basebelles_Belles::META_HISTORICAL ) );
		$this->assertSame( 'José Ramírez', $this->meta( Basebelles_Belles::META_CURRENT )['name'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function consent_label_cases(): array {
		return array(
			'wpforms default' => array( 'GDPR Agreement' ),
			'plain consent'   => array( 'Consent' ),
			'agreement'       => array( 'Terms Agreement' ),
			'i agree'         => array( 'I agree to the above' ),
			'permission'      => array( 'Permission to list me' ),
		);
	}

	#[DataProvider( 'consent_label_cases' )]
	public function test_consent_labels_are_recognised( string $label ): void {
		$this->submit(
			Fixtures::entry(
				array(
					'GDPR Agreement' => null,
					$label           => 'Yes',
				)
			)
		);

		$this->assertSame( 1, $this->meta( Basebelles_Belles::META_CONSENT ) );
	}

	/**
	 * An opt-in worded around consent must still lose to the actual email field, which is the
	 * one value intake cannot proceed without.
	 */
	public function test_an_email_label_still_wins_over_consent_wording(): void {
		$this->submit(
			Fixtures::entry(
				array(
					'Email Address'                => null,
					'Consent to email me updates?' => 'mika@example.com',
				)
			)
		);

		$this->assertSame( 'mika@example.com', $this->meta( Basebelles_Belles::META_EMAIL ) );
	}

	public function test_the_field_map_filter_pins_exact_field_ids(): void {
		// Field 3 is Location by label; the filter reassigns it to the historical player.
		Basebelles_Test_State::$filters['basebelles_belles_field_map'] = static function () {
			return array( 'historical_player' => 3 );
		};

		$this->submit( Fixtures::entry( array( 'Favorite Historical Player' => null ) ) );

		$this->assertSame( 'Cleveland, OH, USA', $this->meta( Basebelles_Belles::META_HISTORICAL ) );
		$this->assertSame( '', $this->meta( Basebelles_Belles::META_LOCATION ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Consent
	 *
	 * The WPForms entry is the primary evidence and META_ENTRY_ID points at it. These record a
	 * second copy on the Belle so it still carries its own proof if entries are ever pruned.
	 * -----------------------------------------------------------------------
	 */

	public function test_consent_is_recorded(): void {
		$this->submit( Fixtures::entry() );

		$this->assertSame( 1, $this->meta( Basebelles_Belles::META_CONSENT ) );
	}

	/** GMT, so the record does not shift if the site's timezone setting changes. */
	public function test_consent_is_timestamped_in_gmt(): void {
		$this->submit( Fixtures::entry() );

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			(string) $this->meta( Basebelles_Belles::META_CONSENTED_AT )
		);
		$this->assertSame(
			gmdate( 'Y-m-d' ),
			substr( (string) $this->meta( Basebelles_Belles::META_CONSENTED_AT ), 0, 10 )
		);
	}

	public function test_an_unticked_box_records_a_refusal_with_no_timestamp(): void {
		$this->submit( Fixtures::entry( array( 'GDPR Agreement' => '' ) ) );

		$this->assertSame( 0, $this->meta( Basebelles_Belles::META_CONSENT ) );
		$this->assertNull( $this->meta( Basebelles_Belles::META_CONSENTED_AT ) );
	}

	/**
	 * A form with no consent field is not the same as consent being withheld, so it records
	 * nothing rather than writing a misleading zero.
	 */
	public function test_a_form_without_a_consent_field_records_nothing_either_way(): void {
		$this->submit( Fixtures::entry( array( 'GDPR Agreement' => null ) ) );

		$this->assertNotNull( $this->belle() );
		$this->assertNull( $this->meta( Basebelles_Belles::META_CONSENT ) );
		$this->assertNull( $this->meta( Basebelles_Belles::META_CONSENTED_AT ) );
	}

	/** The entry is the primary record; the Belle has to be able to point back at it. */
	public function test_the_belle_links_back_to_the_wpforms_entry(): void {
		$this->submit( Fixtures::entry() );

		$this->assertSame( 9001, $this->meta( Basebelles_Belles::META_ENTRY_ID ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Refusals
	 * -----------------------------------------------------------------------
	 */

	public function test_a_submission_to_another_form_is_ignored(): void {
		$this->submit( Fixtures::entry(), 99 );

		$this->assertNull( $this->belle() );
	}

	public function test_nothing_happens_until_a_form_is_connected(): void {
		Basebelles_Test_State::$options[ Basebelles_Belles::OPTION_FORM_ID ] = 0;

		$this->submit( Fixtures::entry(), 0 );

		$this->assertNull( $this->belle() );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function unusable_email_cases(): array {
		return array(
			'missing'      => array( null ),
			'empty'        => array( '' ),
			'no at sign'   => array( 'mika.example.com' ),
			'no domain'    => array( 'mika@' ),
			'no tld'       => array( 'mika@example' ),
			'just a space' => array( ' ' ),
		);
	}

	/**
	 * The address is both the avatar source and the deduplication key, so a submission without a
	 * usable one is dropped rather than stored half-formed.
	 */
	#[DataProvider( 'unusable_email_cases' )]
	public function test_a_submission_without_a_usable_email_is_dropped( $email ): void {
		$this->submit( Fixtures::entry( array( 'Email Address' => $email ) ) );

		$this->assertNull( $this->belle() );
	}

	public function test_a_missing_name_falls_back_to_the_email_local_part(): void {
		$this->submit( Fixtures::entry( array( 'Your Name' => null ) ) );

		$belle = $this->belle();

		$this->assertNotNull( $belle );
		$this->assertSame( 'Mika', $belle->post_title );
	}

	public function test_a_failed_insert_stores_no_meta(): void {
		Basebelles_Test_State::$insert_post_result = new WP_Error( 'db_error', 'nope' );

		$this->submit( Fixtures::entry() );

		$this->assertSame( array(), Basebelles_Test_State::$meta );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Duplicates
	 * -----------------------------------------------------------------------
	 */

	public function test_a_first_time_address_is_not_flagged(): void {
		$this->submit( Fixtures::entry() );

		$this->assertNull( $this->meta( Basebelles_Belles::META_DUPLICATE ) );
	}

	/**
	 * Flagged for a moderator rather than dropped: a repeat submission is usually someone
	 * correcting a typo, and that is a judgement call.
	 */
	public function test_a_repeat_address_is_flagged_for_review(): void {
		Basebelles_Test_State::add_post(
			array(
				'post_type'   => Basebelles_Belles::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Mika Epstein',
			),
			array( Basebelles_Belles::META_EMAIL_HASH => hash( 'sha256', 'mika@example.com' ) )
		);

		$this->submit( Fixtures::entry() );

		$belles = array_filter(
			Basebelles_Test_State::$posts,
			static function ( $post ) {
				return Basebelles_Belles::POST_TYPE === $post->post_type;
			}
		);

		$this->assertCount( 2, $belles );

		$newest = Basebelles_Test_State::$meta[ array_key_last( Basebelles_Test_State::$posts ) ];

		$this->assertSame( 1, $newest[ Basebelles_Belles::META_DUPLICATE ] );
	}

	public function test_a_different_address_is_not_a_duplicate(): void {
		Basebelles_Test_State::add_post(
			array(
				'post_type'   => Basebelles_Belles::POST_TYPE,
				'post_status' => 'publish',
			),
			array( Basebelles_Belles::META_EMAIL_HASH => hash( 'sha256', 'someone@else.com' ) )
		);

		$this->submit( Fixtures::entry() );

		$newest = Basebelles_Test_State::$meta[ array_key_last( Basebelles_Test_State::$posts ) ];

		$this->assertArrayNotHasKey( Basebelles_Belles::META_DUPLICATE, $newest );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Wiring
	 * -----------------------------------------------------------------------
	 */

	public function test_intake_is_hooked_to_wpforms(): void {
		new Basebelles_Belles();

		$this->assertArrayHasKey( 'wpforms_process_complete', Basebelles_Test_State::$hooks );
	}

	/**
	 * Belles are cards in a directory, not pages, and the post type must not expose stored
	 * email addresses through the REST API.
	 */
	public function test_the_post_type_is_registered_private_and_out_of_rest(): void {
		new Basebelles_Belles();

		$args = Basebelles_Test_State::$post_types[ Basebelles_Belles::POST_TYPE ] ?? array();

		$this->assertNotSame( array(), $args );
		$this->assertFalse( $args['public'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertTrue( $args['show_ui'] );
	}
}
