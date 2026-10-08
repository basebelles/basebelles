<?php
/**
 * Tests for Basebelles_Postseason: seeding, the bye, and the series/round state machine.
 *
 * The schedule fixtures are shaped like the real `schedule?gameType=F,D,L,W&hydrate=team,linescore`
 * payload; the 2026 ALDS ones carry the real scores.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class PostseasonTest extends TestCase {

	const CLE = 114;

	/** Seeds as they fell in 2026. */
	private function seeds(): array {
		return Basebelles_Postseason::compute_seeds( Fixtures::al_standings_2026() );
	}

	private function build( array $games, string $today = '2026-10-08', ?array $seeds = null ): array {
		return Basebelles_Postseason::build_status(
			Fixtures::ps_dates( $games ),
			$seeds ?? $this->seeds(),
			$today,
			self::CLE
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Seeding
	 * -----------------------------------------------------------------------
	 */

	public function test_division_winners_seed_first_by_league_rank_then_wild_cards(): void {
		$this->assertSame(
			array(
				139 => 1, // TB
				114 => 2, // CLE
				117 => 3, // HOU
				147 => 4, // NYY
				111 => 5, // BOS
				145 => 6, // CWS
			),
			$this->seeds()
		);
	}

	public function test_a_division_winner_with_a_worse_record_still_outseeds_every_wild_card(): void {
		// The Astros went 81-81 and the Yankees 93-68; the division title is what counts.
		$seeds = $this->seeds();

		$this->assertLessThan( $seeds[147], $seeds[117] );
	}

	public function test_teams_outside_the_top_three_wild_cards_get_no_seed(): void {
		$seeds = $this->seeds();

		$this->assertArrayNotHasKey( 140, $seeds ); // Rangers, wild card rank 4.
		$this->assertArrayNotHasKey( 142, $seeds );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The real 2026 ALDS
	 * -----------------------------------------------------------------------
	 */

	public function test_mid_series_state_matches_the_real_alds(): void {
		$status = $this->build( Fixtures::alds_2026_before_game_four() );

		$this->assertSame( 'in_series', $status['state'] );
		$this->assertSame( 2, $status['seed'] );
		$this->assertTrue( $status['had_bye'] );
		$this->assertFalse( $status['seed_mismatch'] );
		$this->assertSame( 'Bye', $status['wild_card_label'] );

		$current = $status['current'];
		$this->assertSame( 'AL Division Series', $current['round'] );
		$this->assertSame( 5, $current['games_in_series'] );
		$this->assertSame( 3, $current['wins_needed'] );
		$this->assertSame( 'CWS', $current['opponent']['abbreviation'] );
		$this->assertSame( 6, $current['opponent']['seed'] );
		$this->assertSame( 2, $current['guardians']['seed'] );
		$this->assertSame( 'CWS leads 2-1', $current['status_label'] );
	}

	public function test_game_cells_read_from_cleveland_side(): void {
		$cells = $this->build( Fixtures::alds_2026_before_game_four() )['current']['games'];

		$this->assertSame(
			array(
				array( 'G1 · vs CWS', 'L 0-3', 'loss', false ),
				array( 'G2 · vs CWS', 'L 3-4', 'loss', false ),
				array( 'G3 · @CWS', 'W 9-3', 'win', false ),
				array( 'G4 · @CWS', 'Today 8:00 PM', 'scheduled', true ),
				array( 'G5 · vs CWS', 'Sat 10/10 · if nec.', 'scheduled', false ),
			),
			array_map(
				static function ( $cell ) {
					return array( $cell['label'], $cell['value'], $cell['state'], $cell['next'] );
				},
				$cells
			)
		);
	}

	public function test_a_live_game_shows_the_score_from_cleveland_side_and_is_next(): void {
		$games    = Fixtures::alds_2026_before_game_four();
		$games[3] = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Live', 2, 1, array( 'officialDate' => '2026-10-08' ) );

		$cell = $this->build( $games )['current']['games'][3];

		$this->assertSame( '2-1 · Top 6th', $cell['value'] );
		$this->assertSame( 'live', $cell['state'] );
		$this->assertTrue( $cell['next'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Elimination and advancing
	 * -----------------------------------------------------------------------
	 */

	public function test_losing_the_series_eliminates_and_unplayed_games_are_not_needed(): void {
		$games    = Fixtures::alds_2026_before_game_four();
		$games[3] = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 1, 3, array( 'officialDate' => '2026-10-08' ) );

		$status = $this->build( $games, '2026-10-09' );

		$this->assertSame( 'eliminated', $status['state'] );
		$this->assertSame( 'Lost 1-3 vs CWS', $status['current']['result_label'] );
		$this->assertSame( 'CWS wins 3-1', $status['current']['status_label'] );
		$this->assertSame( '1-3', $status['postseason_record'] );

		$last = $status['current']['games'][4];
		$this->assertSame( 'Not needed', $last['value'] );
		$this->assertSame( 'not_needed', $last['state'] );
		$this->assertFalse( $last['next'] );
	}

	public function test_winning_the_series_with_no_next_opponent_is_between_rounds(): void {
		$games    = Fixtures::alds_2026_before_game_four();
		$games[3] = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 5, 2, array( 'officialDate' => '2026-10-08' ) );
		$games[4] = Fixtures::ps_game( 'D', 5, 5, 145, true, 'Final', 4, 3, array( 'officialDate' => '2026-10-10', 'ifNecessary' => 'Y' ) );

		$status = $this->build( $games, '2026-10-11' );

		$this->assertSame( 'between_rounds', $status['state'] );
		$this->assertSame( 'L', $status['next_round'] );
		$this->assertSame( 'Won 3-2 vs CWS', $status['current']['result_label'] );
	}

	public function test_once_the_next_round_is_scheduled_it_becomes_the_current_series(): void {
		$games   = Fixtures::alds_2026_before_game_four();
		$games[3] = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 5, 2, array( 'officialDate' => '2026-10-08' ) );
		$games[4] = Fixtures::ps_game( 'D', 5, 5, 145, true, 'Final', 4, 3, array( 'officialDate' => '2026-10-10' ) );
		$games[]  = Fixtures::ps_game( 'L', 1, 7, 139, false, 'Preview', null, null, array( 'officialDate' => '2026-10-12' ) );

		$status = $this->build( $games, '2026-10-11' );

		$this->assertSame( 'in_series', $status['state'] );
		$this->assertSame( 'AL Championship Series', $status['current']['round'] );
		$this->assertSame( 'TB', $status['current']['opponent']['abbreviation'] );
		$this->assertSame( 1, $status['current']['opponent']['seed'] );
		$this->assertSame( 'Tied 0-0', $status['current']['status_label'] );
		$this->assertCount( 7, $status['current']['games'] );
		$this->assertSame( 'TBD', $status['current']['games'][6]['value'] );
		$this->assertSame( '3-2', $status['postseason_record'] );
	}

	public function test_winning_the_world_series_is_champion(): void {
		$games = array();

		for ( $n = 1; $n <= 4; $n++ ) {
			$games[] = Fixtures::ps_game( 'W', $n, 7, 119, 0 === $n % 2, 'Final', 5, 1 );
		}

		$this->assertSame( 'champion', $this->build( $games, '2026-10-31' )['state'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The bye
	 * -----------------------------------------------------------------------
	 */

	public function test_a_bye_seed_with_nothing_scheduled_yet_is_awaiting_the_division_series(): void {
		$status = $this->build( array(), '2026-09-29' );

		$this->assertSame( 'awaiting', $status['state'] );
		$this->assertSame( 'D', $status['next_round'] );
		$this->assertTrue( $status['had_bye'] );
		$this->assertSame( 'Bye', $status['wild_card_label'] );
	}

	public function test_no_seed_and_no_games_means_not_qualified(): void {
		$status = $this->build( array(), '2026-10-08', array( 139 => 1 ) );

		$this->assertSame( 'not_qualified', $status['state'] );
	}

	public function test_a_wild_card_team_shows_its_wild_card_result_in_later_rounds(): void {
		$seeds   = array( 114 => 4, 117 => 5 );
		$games   = array(
			Fixtures::ps_game( 'F', 1, 3, 117, true, 'Final', 4, 2, array( 'officialDate' => '2026-09-29' ) ),
			Fixtures::ps_game( 'F', 2, 3, 117, true, 'Final', 6, 1, array( 'officialDate' => '2026-09-30' ) ),
			Fixtures::ps_game( 'D', 1, 5, 139, false, 'Preview', null, null, array( 'officialDate' => '2026-10-03' ) ),
		);

		$status = $this->build( $games, '2026-10-02', $seeds );

		$this->assertFalse( $status['had_bye'] );
		$this->assertFalse( $status['seed_mismatch'] );
		$this->assertSame( 'Won 2-0 vs HOU', $status['wild_card_label'] );
		$this->assertSame( 'D', $status['current']['game_type'] );
		// Game 3 of the Wild Card was never needed, and the schedule had no entry for it.
		$this->assertSame( 'Not needed', $status['series']['F']['games'][2]['value'] );
	}

	public function test_wild_card_games_override_a_bye_seed_and_flag_the_mismatch(): void {
		$games = array( Fixtures::ps_game( 'F', 1, 3, 117, true, 'Preview', null, null, array( 'officialDate' => '2026-09-29' ) ) );

		$status = $this->build( $games, '2026-09-29' );

		$this->assertFalse( $status['had_bye'] );
		$this->assertTrue( $status['seed_mismatch'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Schedule quirks
	 * -----------------------------------------------------------------------
	 */

	public function test_a_postponed_entry_is_replaced_by_its_makeup_game(): void {
		$games    = Fixtures::alds_2026_before_game_four();
		$rained   = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Preview', null, null, array( 'officialDate' => '2026-10-08' ) );
		$rained['status']['detailedState'] = 'Postponed';
		$makeup   = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 6, 2, array( 'officialDate' => '2026-10-09' ) );
		$games[3] = $rained;
		array_splice( $games, 4, 0, array( $makeup ) );

		$status = $this->build( $games, '2026-10-10' );

		$this->assertSame( 'W 6-2', $status['current']['games'][3]['value'] );
		$this->assertSame( 'Tied 2-2', $status['current']['status_label'] );
	}

	public function test_a_makeup_listed_before_the_postponed_entry_still_wins(): void {
		$rained = Fixtures::ps_game( 'D', 1, 5, 145, true, 'Preview', null, null, array( 'officialDate' => '2026-10-03' ) );
		$rained['status']['detailedState'] = 'Postponed';
		$makeup = Fixtures::ps_game( 'D', 1, 5, 145, true, 'Final', 2, 1, array( 'officialDate' => '2026-10-02' ) );

		$status = $this->build( array( $makeup, $rained ), '2026-10-04' );

		$this->assertSame( 'W 2-1', $status['current']['games'][0]['value'] );
	}

	public function test_regular_season_and_spring_games_are_ignored(): void {
		$games   = Fixtures::alds_2026_before_game_four();
		$games[] = Fixtures::ps_game( 'D', 1, 5, 145, true, 'Final', 9, 0, array( 'gameType' => 'R', 'officialDate' => '2026-09-27' ) );

		$this->assertSame( 'CWS leads 2-1', $this->build( $games )['current']['status_label'] );
	}
}
