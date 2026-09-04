<?php
/**
 * Tests for Basebelles_Today_Game_Panels.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PanelsTest extends TestCase {

	protected function setUp(): void {
		Basebelles_Test_State::reset();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Phase resolution
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Every detailedState MLB is known to send for a stopped or cancelled game, plus the ordinary
	 * ones, mapped to the phase it should produce.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function phase_cases(): array {
		return array(
			'delayed start with a cause'   => array( 'Delayed Start: Rain', 'Preview', 'delayed' ),
			'delayed in progress'          => array( 'Delayed: Rain', 'Live', 'delayed' ),
			'delayed start, no cause'      => array( 'Delayed Start', 'Preview', 'delayed' ),
			'delayed, no cause'            => array( 'Delayed', 'Live', 'delayed' ),
			// MLB can report a suspended game with an abstract state of Final even though it is
			// still going to be resumed, which is why the delay check runs before the Final one.
			'suspended reported as final'  => array( 'Suspended: Rain', 'Final', 'delayed' ),
			'suspended while live'         => array( 'Suspended', 'Live', 'delayed' ),
			'umpire delay'                 => array( 'Umpire Delay', 'Live', 'delayed' ),
			'postponed after being final'  => array( 'Postponed', 'Final', 'postponed' ),
			'postponed before first pitch' => array( 'Postponed', 'Preview', 'postponed' ),
			'final'                        => array( 'Final', 'Final', 'final' ),
			'game over'                    => array( 'Game Over', 'Final', 'final' ),
			'in progress'                  => array( 'In Progress', 'Live', 'live' ),
		);
	}

	#[DataProvider( 'phase_cases' )]
	public function test_get_phase_resolves_mlb_states( string $detailed_state, string $abstract_state, string $expected ): void {
		$game = Fixtures::game(
			array(
				'game_status'    => $abstract_state,
				'detailed_state' => array(
					'state'  => $detailed_state,
					'reason' => '',
				),
			)
		);

		$this->assertSame( $expected, Basebelles_Today_Game_Panels::get_phase( $game ) );
	}

	public function test_scheduled_game_is_far_then_near_as_first_pitch_approaches(): void {
		$far = Fixtures::game( array( 'sort_time' => time() + 7200 ) );
		$this->assertSame( 'far', Basebelles_Today_Game_Panels::get_phase( $far ) );

		$near = Fixtures::game( array( 'sort_time' => time() + 600 ) );
		$this->assertSame( 'near', Basebelles_Today_Game_Panels::get_phase( $near ) );
	}

	public function test_delay_clears_once_mlb_drops_the_delay_state(): void {
		// A stale reason string must not keep the game stuck in the delayed phase.
		$game = Fixtures::live_game(
			array(
				'detailed_state' => array(
					'state'  => 'In Progress',
					'reason' => 'Rain',
				),
			)
		);

		$this->assertSame( 'live', Basebelles_Today_Game_Panels::get_phase( $game ) );
	}

	public function test_delayed_game_that_finished_reads_as_final(): void {
		$this->assertSame( 'final', Basebelles_Today_Game_Panels::get_phase( Fixtures::final_game() ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function non_delay_states(): array {
		return array(
			'scheduled'         => array( 'Scheduled' ),
			'pre-game'          => array( 'Pre-Game' ),
			'warmup'            => array( 'Warmup' ),
			'in progress'       => array( 'In Progress' ),
			'manager challenge' => array( 'Manager Challenge' ),
			'review'            => array( 'Review' ),
			'completed early'   => array( 'Completed Early' ),
			'final'             => array( 'Final' ),
			'empty'             => array( '' ),
		);
	}

	/** The delay states are matched as prefixes, so guard against them catching too much. */
	#[DataProvider( 'non_delay_states' )]
	public function test_is_delay_state_does_not_over_match( string $state ): void {
		$this->assertFalse( Basebelles_Today_Game_Panels::is_delay_state( $state ) );
	}

	public function test_delay_in_progress_distinguishes_a_stoppage_from_a_pushed_back_start(): void {
		$this->assertTrue( Basebelles_Today_Game_Panels::delay_is_in_progress( Fixtures::delayed_in_progress() ) );
		$this->assertFalse( Basebelles_Today_Game_Panels::delay_is_in_progress( Fixtures::delayed_start() ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Delay reason and label
	 * -----------------------------------------------------------------------
	 */

	public function test_delay_reason_is_parsed_off_detailed_state_when_the_reason_field_is_empty(): void {
		$game = Fixtures::delayed_in_progress( 'Delayed: Rain' );

		$this->assertSame( 'Rain', Basebelles_Today_Game_Panels::get_delay_reason( $game ) );
	}

	public function test_delay_reason_field_wins_over_the_detailed_state(): void {
		$game = Fixtures::delayed_in_progress(
			'Delayed',
			array(
				'detailed_state' => array(
					'state'  => 'Delayed',
					'reason' => 'Wet Grounds',
				),
			)
		);

		$this->assertSame( 'Wet Grounds', Basebelles_Today_Game_Panels::get_delay_reason( $game ) );
	}

	public function test_delay_reason_is_empty_when_mlb_names_none(): void {
		$this->assertSame( '', Basebelles_Today_Game_Panels::get_delay_reason( Fixtures::delayed_in_progress( 'Umpire Delay' ) ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function delay_label_cases(): array {
		return array(
			'rain in progress'       => array( 'Delayed: Rain', '', 'Rain delay' ),
			'rain before the start'  => array( 'Delayed Start: Rain', '', 'Rain delay' ),
			'reason field only'      => array( 'Delayed', 'Wet Grounds', 'Wet Grounds delay' ),
			'no cause at all'        => array( 'Delayed', '', 'Delayed' ),
			// Umpire and suspended keep their own wording: "Umpire Delay delay" would be silly,
			// and a suspended game is not the same thing as a pause.
			'umpire keeps wording'   => array( 'Umpire Delay', '', 'Umpire delay' ),
			'suspended with cause'   => array( 'Suspended: Rain', '', 'Suspended · Rain' ),
			'suspended, no cause'    => array( 'Suspended', '', 'Suspended' ),
		);
	}

	#[DataProvider( 'delay_label_cases' )]
	public function test_get_delay_label( string $state, string $reason, string $expected ): void {
		$game = Fixtures::game(
			array(
				'detailed_state' => array(
					'state'  => $state,
					'reason' => $reason,
				),
			)
		);

		$this->assertSame( $expected, Basebelles_Today_Game_Panels::get_delay_label( $game ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Header time label
	 * -----------------------------------------------------------------------
	 */

	public function test_live_time_label_pulses(): void {
		$html = Basebelles_Today_Game_Panels::render_time_label( Fixtures::live_game(), 'live' );

		$this->assertStringContainsString( 'is-live', $html );
		$this->assertStringContainsString( 'tg-live-dot', $html );
		$this->assertStringContainsString( 'LIVE', $html );
	}

	/**
	 * The bug this guards: a rain-delayed game used to keep the pulsing LIVE badge, so the card
	 * claimed baseball was happening while the tarp was on.
	 */
	public function test_delayed_time_label_neither_claims_live_nor_pulses(): void {
		$html = Basebelles_Today_Game_Panels::render_time_label( Fixtures::delayed_in_progress(), 'delayed' );

		$this->assertStringContainsString( 'is-delayed', $html );
		$this->assertStringContainsString( 'Rain delay', $html );
		$this->assertStringNotContainsString( 'LIVE', $html );
		$this->assertStringNotContainsString( 'is-live', $html );
		$this->assertStringNotContainsString( 'tg-live-dot', $html );
	}

	public function test_postponed_time_label(): void {
		$game = Fixtures::game(
			array(
				'detailed_state' => array(
					'state'  => 'Postponed',
					'reason' => 'Rain',
				),
			)
		);
		$html = Basebelles_Today_Game_Panels::render_time_label( $game, 'postponed' );

		$this->assertStringContainsString( 'is-postponed', $html );
		$this->assertStringContainsString( 'Postponed', $html );
		$this->assertStringNotContainsString( 'tg-live-dot', $html );
	}

	public function test_scheduled_time_label_shows_first_pitch(): void {
		$html = Basebelles_Today_Game_Panels::render_time_label( Fixtures::game(), 'far' );

		$this->assertStringContainsString( '2:10 PM EDT', $html );
		$this->assertStringNotContainsString( 'is-live', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Score panel routing
	 * -----------------------------------------------------------------------
	 */

	public function test_delayed_start_panel_names_the_scheduled_first_pitch_and_no_countdown(): void {
		$html = Basebelles_Today_Game_Panels::render_score_panel( Fixtures::delayed_start(), 'delayed', array() );

		$this->assertStringContainsString( 'First pitch was scheduled for 2:10 PM EDT', $html );
		$this->assertStringContainsString( 'Rain delay', $html );
		// The old behaviour left a countdown ticking to 0:00 and refreshing itself forever.
		$this->assertStringNotContainsString( 'tg-countdown', $html );
	}

	public function test_delay_panel_does_not_invent_a_restart_time(): void {
		$html = Basebelles_Today_Game_Panels::render_score_panel( Fixtures::delayed_start(), 'delayed', array() );

		$this->assertStringContainsString( 'no updated start time yet', $html );
	}

	public function test_mid_game_delay_says_where_play_stopped_and_keeps_the_score(): void {
		$html = Basebelles_Today_Game_Panels::render_score_panel(
			Fixtures::delayed_in_progress(),
			'delayed',
			Fixtures::live_feed()
		);

		$this->assertStringContainsString( 'Play stopped in the top 6', $html );
		$this->assertStringContainsString( 'DET', $html );
		$this->assertStringContainsString( 'CLE', $html );
		// The at-bat card belongs to a game actually being played.
		$this->assertStringNotContainsString( 'tg-at-bat', $html );
	}

	public function test_mid_game_delay_falls_back_to_the_schedule_inning_without_a_live_feed(): void {
		$html = Basebelles_Today_Game_Panels::render_score_panel( Fixtures::delayed_in_progress(), 'delayed', array() );

		$this->assertStringContainsString( 'tg-delay', $html );
		$this->assertStringContainsString( 'top of the 6th', $html );
	}

	public function test_postponed_panel_is_not_drawn_as_a_final_score(): void {
		// The old code rendered a postponement through the final-score markup with both teams'
		// runs blanked to "-" either side of a PPD badge.
		$game = Fixtures::final_game(
			2,
			6,
			array(
				'detailed_state' => array(
					'state'  => 'Postponed',
					'reason' => 'Rain',
				),
				'scores'         => array(
					'away'    => '-',
					'home'    => '-',
					'winner'  => '',
					'inning'  => 1,
					'isFinal' => true,
				),
			)
		);

		$html = Basebelles_Today_Game_Panels::render_score_panel( $game, 'postponed', array() );

		$this->assertStringContainsString( 'Postponed', $html );
		$this->assertStringContainsString( 'Called for rain', $html );
		$this->assertStringNotContainsString( 'game-scores', $html );
		$this->assertStringNotContainsString( 'FINAL', $html );
	}

	public function test_live_panel_still_draws_the_at_bat(): void {
		$html = Basebelles_Today_Game_Panels::render_score_panel( Fixtures::live_game(), 'live', Fixtures::live_feed() );

		$this->assertStringContainsString( 'tg-at-bat', $html );
		$this->assertStringContainsString( 'Colt Keith', $html );
	}

	public function test_final_panel_declares_the_result(): void {
		$html = Basebelles_Today_Game_Panels::render_score_panel( Fixtures::final_game( 2, 6 ), 'final', array() );

		$this->assertStringContainsString( 'FINAL', $html );
		$this->assertStringContainsString( 'The Guardians win', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Stats panel routing
	 * -----------------------------------------------------------------------
	 */

	public function test_delayed_start_stats_show_the_pregame_matchup(): void {
		$html = Basebelles_Today_Game_Panels::render_stats_panel( Fixtures::delayed_start(), 'delayed', array() );

		$this->assertStringContainsString( 'probable-pitchers', $html );
	}

	public function test_mid_game_delay_stats_show_the_box_score(): void {
		$html = Basebelles_Today_Game_Panels::render_stats_panel(
			Fixtures::delayed_in_progress(),
			'delayed',
			Fixtures::live_feed()
		);

		$this->assertStringContainsString( 'tg-innings', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Doubleheader switcher labels
	 * -----------------------------------------------------------------------
	 */

	public function test_switcher_label_for_a_live_game_carries_the_pulse(): void {
		$html = Basebelles_Today_Game_Panels::render_switcher_label( Fixtures::live_game(), 'live' );

		$this->assertStringContainsString( 'tg-live-dot', $html );
		$this->assertStringContainsString( 'Live', $html );
	}

	public function test_switcher_label_for_a_final_game_carries_the_score(): void {
		$game = Fixtures::final_game( 2, 6, array( 'game_number' => 1 ) );

		$this->assertStringContainsString( 'Final 2', Basebelles_Today_Game_Panels::render_switcher_label( $game, 'final' ) );
	}

	/** The pill is too narrow for "Rain delay" on a phone, and the panel says why anyway. */
	public function test_switcher_label_for_a_delayed_game_stays_short(): void {
		$html = Basebelles_Today_Game_Panels::render_switcher_label( Fixtures::delayed_in_progress(), 'delayed' );

		$this->assertSame( 'Game 1 · Delayed', $html );
	}

	public function test_switcher_label_for_a_postponed_game(): void {
		$game = Fixtures::game(
			array(
				'detailed_state' => array(
					'state'  => 'Postponed',
					'reason' => 'Rain',
				),
			)
		);

		$this->assertSame( 'Game 1 · PPD', Basebelles_Today_Game_Panels::render_switcher_label( $game, 'postponed' ) );
	}

	public function test_switcher_label_shortens_the_second_game_placeholder_time(): void {
		// MLB gives "TBD (30m after G1)" for the back half of a traditional doubleheader, which
		// does not fit a pill.
		$game = Fixtures::game(
			array(
				'game_number' => 2,
				'game_time'   => 'TBD (30m after G1)',
			)
		);

		$this->assertSame( 'Game 2 · TBD', Basebelles_Today_Game_Panels::render_switcher_label( $game, 'far' ) );
	}

	public function test_switcher_label_falls_back_to_the_game_number_without_a_time(): void {
		$game = Fixtures::game( array( 'game_time' => '' ) );

		$this->assertSame( 'Game 1', Basebelles_Today_Game_Panels::render_switcher_label( $game, 'far' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Which game a doubleheader opens on
	 * -----------------------------------------------------------------------
	 */

	/**
	 * @return array<string, array{0: array<int, string>, 1: int}>
	 */
	public static function default_game_cases(): array {
		return array(
			'live game wins'                  => array( array( 'final', 'live' ), 1 ),
			'live outranks delayed'           => array( array( 'delayed', 'live' ), 1 ),
			// A suspended opener beside a completed nightcap is a real pairing, and the
			// unfinished game is the one still worth opening.
			'delayed outranks final'          => array( array( 'delayed', 'final' ), 0 ),
			'delayed outranks final, ordered' => array( array( 'final', 'delayed' ), 1 ),
			'delayed outranks upcoming'       => array( array( 'delayed', 'far' ), 0 ),
			'both scheduled opens earlier'    => array( array( 'far', 'far' ), 0 ),
			'both final opens later'          => array( array( 'final', 'final' ), 1 ),
			'finished then upcoming'          => array( array( 'final', 'near' ), 1 ),
			'postponed opener skipped'        => array( array( 'postponed', 'far' ), 1 ),
			'postponed nightcap skipped'      => array( array( 'far', 'postponed' ), 0 ),
		);
	}

	#[DataProvider( 'default_game_cases' )]
	public function test_get_default_game_index( array $phases, int $expected ): void {
		$games = array( Fixtures::game(), Fixtures::game( array( 'game_number' => 2 ) ) );

		$this->assertSame( $expected, Basebelles_Today_Game_Panels::get_default_game_index( $games, $phases ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Switcher markup
	 * -----------------------------------------------------------------------
	 */

	public function test_game_switcher_marks_exactly_one_tab_active(): void {
		$games  = array(
			Fixtures::final_game( 2, 6 ),
			Fixtures::live_game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
		);
		$phases = array( 'final', 'live' );

		$html = Basebelles_Today_Game_Panels::render_game_switcher( $games, $phases, 1 );

		$this->assertSame( 2, preg_match_all( '#class="tg-game-tab#', $html ) );
		$this->assertSame( 1, preg_match_all( '#class="tg-game-tab is-active"#', $html ) );
		$this->assertSame( 1, preg_match_all( '#aria-selected="true"#', $html ) );
		$this->assertStringContainsString( 'data-game-tab="777002"', $html );
		$this->assertStringContainsString( 'role="tablist"', $html );
	}

	public function test_header_score_is_hidden_before_first_pitch(): void {
		// MLB's linescore reports zeroed runs before a game starts, so this gates on status.
		$this->assertSame( '', Basebelles_Today_Game_Panels::render_header_score( Fixtures::game(), Fixtures::live_feed() ) );
	}

	public function test_header_score_shows_once_underway(): void {
		$html = Basebelles_Today_Game_Panels::render_header_score( Fixtures::live_game(), Fixtures::live_feed() );

		$this->assertStringContainsString( 'tg-header-score', $html );
		$this->assertStringContainsString( '3', $html );
	}
}
