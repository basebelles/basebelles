<?php
/**
 * Tests for Basebelles_Season_Timeline (helpers/class-season-timeline.php).
 *
 * The 2026 dates throughout are the real ones from Season Settings: spring Feb 20 - Mar 23,
 * regular season Mar 25 - Sep 27, postseason Sep 29 - Oct 31.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class SeasonTimelineTest extends TestCase {

	private static function day( string $ymd ): DateTimeImmutable {
		return new DateTimeImmutable( $ymd, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The 2026 phases.
	 *
	 * @param array $overrides Phase key => fields to replace.
	 * @return array
	 */
	private static function phases( array $overrides = array() ): array {
		$phases = array(
			'spring_training' => array(
				'start'  => self::day( '2026-02-20' ),
				'end'    => self::day( '2026-03-23' ),
				'record' => '16 – 14',
				'link'   => '/season-type/spring-training/?season_year=2026',
			),
			'regular_season'  => array(
				'start'  => self::day( '2026-03-25' ),
				'end'    => self::day( '2026-09-27' ),
				'record' => '85 – 77',
				'link'   => '/season-type/regular-season/?season_year=2026',
			),
			'post_season'     => array(
				'start'  => self::day( '2026-09-29' ),
				'end'    => self::day( '2026-10-31' ),
				'record' => '',
				'link'   => '/season-type/post-season/?season_year=2026',
			),
		);

		foreach ( $overrides as $key => $fields ) {
			$phases[ $key ] = array_merge( $phases[ $key ], $fields );
		}

		return $phases;
	}

	/** Mid-ALCS 2026: tied 1-1 after a Wild Card bye and an ALDS win over the White Sox. */
	private static function alcs_tied(): array {
		return array(
			'state'   => 'in_series',
			'current' => array(
				'round_short'  => 'ALCS',
				'status_label' => 'Tied 1-1',
			),
			'road'    => array(
				array( 'round' => 'Wild Card', 'result' => 'Bye' ),
				array( 'round' => 'ALDS', 'result' => 'Won 3-2 vs CWS' ),
			),
			'rounds'  => array(
				array( 'round' => 'Wild Card', 'result' => 'Bye' ),
				array( 'round' => 'ALDS', 'result' => 'Won 3-2 vs CWS' ),
			),
		);
	}

	private static function segment( array $timeline, string $key ): array {
		foreach ( $timeline['segments'] as $segment ) {
			if ( $key === $segment['key'] ) {
				return $segment;
			}
		}

		return array();
	}

	public function test_widths_follow_the_calendar(): void {
		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'postSeason', self::day( '2026-10-15' ), self::alcs_tied() );

		$this->assertEqualsWithDelta( 12.6, self::segment( $timeline, 'spring_training' )['width'], 0.2 );
		$this->assertEqualsWithDelta( 73.6, self::segment( $timeline, 'regular_season' )['width'], 0.2 );
		$this->assertEqualsWithDelta( 13.0, self::segment( $timeline, 'post_season' )['width'], 0.2 );
	}

	public function test_mid_alcs_reads_as_the_mockup(): void {
		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'postSeason', self::day( '2026-10-15' ), self::alcs_tied() );

		$this->assertSame( 'done', self::segment( $timeline, 'spring_training' )['state'] );
		$this->assertSame( 'done', self::segment( $timeline, 'regular_season' )['state'] );
		$this->assertSame( 'current', self::segment( $timeline, 'post_season' )['state'] );
		$this->assertSame( '16 – 14', self::segment( $timeline, 'spring_training' )['result'] );
		$this->assertSame( '85 – 77', self::segment( $timeline, 'regular_season' )['result'] );
		$this->assertSame( 'ALDS W 3-2', self::segment( $timeline, 'post_season' )['result'] );
		$this->assertSame( 'Now: ALCS · Tied 1-1', $timeline['now'] );
	}

	public function test_the_pin_sits_at_today_and_hugs_the_end(): void {
		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'postSeason', self::day( '2026-10-15' ), self::alcs_tied() );

		// Oct 15 is day 238 of 254.
		$this->assertEqualsWithDelta( 93.5, $timeline['pin'], 0.5 );
		$this->assertSame( 'end', $timeline['pin_side'] );
		$this->assertEqualsWithDelta( 51.5, self::segment( $timeline, 'post_season' )['fill'], 1 );
	}

	public function test_finished_phases_are_full_and_upcoming_ones_empty(): void {
		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'regularSeason', self::day( '2026-07-01' ) );

		$this->assertSame( 100.0, self::segment( $timeline, 'spring_training' )['fill'] );
		$this->assertSame( 0.0, self::segment( $timeline, 'post_season' )['fill'] );
		$this->assertSame( 'upcoming', self::segment( $timeline, 'post_season' )['state'] );
		// Before it starts, a phase shows its dates rather than an empty record.
		$this->assertSame( 'Sep 29 – Oct 31', self::segment( $timeline, 'post_season' )['result'] );
		$this->assertSame( 'Now: Regular season · 85 – 77', $timeline['now'] );
		$this->assertSame( '', $timeline['pin_side'] );
	}

	public function test_spring_set_but_not_started_has_no_pin(): void {
		$timeline = Basebelles_Season_Timeline::build(
			self::phases( array( 'spring_training' => array( 'record' => '' ) ) ),
			'springTraining',
			self::day( '2026-02-01' )
		);

		$this->assertNull( $timeline['pin'] );
		$this->assertSame( 'Spring training starts Feb 20', $timeline['now'] );
		$this->assertSame( 'Feb 20 – Mar 23', self::segment( $timeline, 'spring_training' )['result'] );
	}

	public function test_the_off_season_is_all_done_with_no_pin(): void {
		$finished           = self::alcs_tied();
		$finished['state']  = 'eliminated';
		$finished['rounds'] = array(
			array( 'round' => 'Wild Card', 'result' => 'Bye' ),
			array( 'round' => 'ALDS', 'result' => 'Won 3-2 vs CWS' ),
			array( 'round' => 'ALCS', 'result' => 'Lost 2-4 vs TB' ),
		);

		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'offSeason', self::day( '2026-12-01' ), $finished );

		foreach ( $timeline['segments'] as $segment ) {
			$this->assertSame( 'done', $segment['state'] );
		}

		$this->assertNull( $timeline['pin'] );
		$this->assertSame( 'Season complete · See you in spring', $timeline['now'] );
		$this->assertSame( 'ALCS L 2-4', self::segment( $timeline, 'post_season' )['result'] );
	}

	public function test_a_title_says_so(): void {
		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'offSeason', self::day( '2026-12-01' ), array( 'state' => 'champion' ) );

		$this->assertSame( 'World Series champions', self::segment( $timeline, 'post_season' )['result'] );
	}

	public function test_between_rounds_shows_the_round_just_won_and_whats_next(): void {
		$status = array(
			'state'      => 'between_rounds',
			'next_round' => 'L',
			'rounds'     => array(
				array( 'round' => 'Wild Card', 'result' => 'Bye' ),
				array( 'round' => 'ALDS', 'result' => 'Won 3-2 vs CWS' ),
			),
			'road'       => array(
				array( 'round' => 'Wild Card', 'result' => 'Bye' ),
			),
		);

		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'postSeason', self::day( '2026-10-11' ), $status );

		$this->assertSame( 'ALDS W 3-2', self::segment( $timeline, 'post_season' )['result'] );
		$this->assertSame( 'Now: Postseason · ALCS next', $timeline['now'] );
	}

	public function test_the_regular_season_standing_follows_the_record(): void {
		$phases   = self::phases( array( 'regular_season' => array( 'standing' => 'AL Central champs' ) ) );
		$finished = Basebelles_Season_Timeline::build( $phases, 'postSeason', self::day( '2026-10-15' ), self::alcs_tied() );
		$running  = Basebelles_Season_Timeline::build( $phases, 'regularSeason', self::day( '2026-07-01' ) );

		$this->assertSame( '85 – 77 · AL Central champs', self::segment( $finished, 'regular_season' )['result'] );
		$this->assertSame( 'Now: Regular season · 85 – 77 · AL Central champs', $running['now'] );
	}

	public function test_a_standing_without_a_record_still_shows(): void {
		$phases   = self::phases( array( 'regular_season' => array( 'record' => '', 'standing' => '2nd in AL Central' ) ) );
		$timeline = Basebelles_Season_Timeline::build( $phases, 'postSeason', self::day( '2026-10-15' ), self::alcs_tied() );

		$this->assertSame( '2nd in AL Central', self::segment( $timeline, 'regular_season' )['result'] );
	}

	public function test_the_postseason_standing_beats_the_live_result(): void {
		$phases   = self::phases( array( 'post_season' => array( 'standing' => 'AL champions' ) ) );
		$timeline = Basebelles_Season_Timeline::build( $phases, 'offSeason', self::day( '2026-12-01' ), self::alcs_tied() );

		$this->assertSame( 'AL champions', self::segment( $timeline, 'post_season' )['result'] );
	}

	public function test_a_standing_waits_until_its_phase_starts(): void {
		$phases   = self::phases( array( 'post_season' => array( 'standing' => 'AL champions' ) ) );
		$timeline = Basebelles_Season_Timeline::build( $phases, 'regularSeason', self::day( '2026-07-01' ) );

		$this->assertSame( 'Sep 29 – Oct 31', self::segment( $timeline, 'post_season' )['result'] );
	}

	public function test_without_postseason_data_the_record_field_is_the_fallback(): void {
		$timeline = Basebelles_Season_Timeline::build(
			self::phases( array( 'post_season' => array( 'record' => '4 – 3' ) ) ),
			'postSeason',
			self::day( '2026-10-15' ),
			null
		);

		$this->assertSame( '4 – 3', self::segment( $timeline, 'post_season' )['result'] );
		$this->assertSame( 'Now: Postseason', $timeline['now'] );
	}

	public function test_missing_dates_fall_back_to_a_typical_season(): void {
		$timeline = Basebelles_Season_Timeline::build(
			self::phases( array( 'post_season' => array( 'end' => null ) ) ),
			'regularSeason',
			self::day( '2026-07-01' )
		);

		$this->assertSame( 12.0, self::segment( $timeline, 'spring_training' )['width'] );
		$this->assertSame( 74.0, self::segment( $timeline, 'regular_season' )['width'] );
		$this->assertNull( $timeline['pin'] );
		$this->assertSame( array(), $timeline['months'] );
		$this->assertSame( 'Sep 29 – TBD', self::segment( $timeline, 'post_season' )['result'] );
	}

	public function test_month_ticks_run_march_to_october(): void {
		$timeline = Basebelles_Season_Timeline::build( self::phases(), 'regularSeason', self::day( '2026-07-01' ) );

		$this->assertSame( array( 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct' ), array_column( $timeline['months'], 0 ) );
	}
}
