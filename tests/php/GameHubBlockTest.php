<?php
/**
 * Tests for the Game Hub block template (blocks/game-hub/render.php) and its cards.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class GameHubBlockTest extends TestCase {

	protected function setUp(): void {
		Basebelles_Test_State::reset();
		Basebelles_Test_State::$fields['game_date'] = '2026-09-04';
		Basebelles_Test_State::$schedule            = Fixtures::schedule( array( Fixtures::game() ) );
	}

	/**
	 * Render the block with Season Type set.
	 *
	 * @param string $season_type ACF season_type value.
	 * @param int    $season      Current Season; a past year means the off season.
	 * @return string
	 */
	private function render( string $season_type, int $season = 0, array $block = array() ): string {
		Basebelles_Test_State::$fields['season_settings'] = array(
			'season_type'    => $season_type,
			'current_season' => $season > 0 ? $season : (int) gmdate( 'Y' ),
		);

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/game-hub/render.php';

		return (string) ob_get_clean();
	}

	private function division(): array {
		$row = static function ( string $abbr, string $name, int $w, int $l, string $gb, string $l10, string $streak, bool $us = false ) {
			return array(
				'id'           => 0,
				'abbreviation' => $abbr,
				'name'         => $name,
				'wins'         => $w,
				'losses'       => $l,
				'games_back'   => $gb,
				'last_ten'     => $l10,
				'streak'       => $streak,
				'is_us'        => $us,
				'logo_url'     => '',
			);
		};

		return array(
			'name'      => 'AL Central',
			'rows'      => array(
				$row( 'CWS', 'White Sox', 72, 52, '—', '7-3', 'W2' ),
				$row( 'CLE', 'Guardians', 68, 56, '4.0', '6-4', 'W1', true ),
				$row( 'DET', 'Tigers', 64, 60, '8.0', '4-6', 'L1' ),
			),
			'wild_card' => 'AL Wild Card: 2nd · +1.5',
		);
	}

	/**
	 * Postseason status mid-ALCS: CLE won Game 1 at TB, Game 2 is today, 3-5 at home, 6-7 at TB
	 * if necessary. Bye, then beat CWS in the ALDS.
	 *
	 * @return array
	 */
	private function alcs_status(): array {
		$games = array(
			Fixtures::ps_game( 'D', 1, 5, 145, true, 'Final', 2, 3, array( 'officialDate' => '2026-10-04' ) ),
			Fixtures::ps_game( 'D', 2, 5, 145, true, 'Final', 3, 4, array( 'officialDate' => '2026-10-05' ) ),
			Fixtures::ps_game( 'D', 3, 5, 145, false, 'Final', 5, 1, array( 'officialDate' => '2026-10-07' ) ),
			Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 4, 2, array( 'officialDate' => '2026-10-08' ) ),
			Fixtures::ps_game( 'D', 5, 5, 145, true, 'Final', 6, 3, array( 'officialDate' => '2026-10-10' ) ),
			Fixtures::ps_game( 'L', 1, 7, 139, false, 'Final', 7, 3, array( 'officialDate' => '2026-10-12' ) ),
			Fixtures::ps_game( 'L', 2, 7, 139, false, 'Preview', null, null, array( 'officialDate' => '2026-10-13', 'gameDate' => '2026-10-14T00:00:00Z' ) ),
			Fixtures::ps_game( 'L', 3, 7, 139, true, 'Preview', null, null, array( 'officialDate' => '2026-10-15', 'gameDate' => '2026-10-15T23:00:00Z' ) ),
			Fixtures::ps_game( 'L', 4, 7, 139, true, 'Preview', null, null, array( 'officialDate' => '2026-10-16', 'gameDate' => '2026-10-16T23:00:00Z' ) ),
			Fixtures::ps_game( 'L', 5, 7, 139, true, 'Preview', null, null, array( 'officialDate' => '2026-10-17', 'gameDate' => '2026-10-17T23:00:00Z', 'ifNecessary' => 'Y' ) ),
			Fixtures::ps_game( 'L', 6, 7, 139, false, 'Preview', null, null, array( 'officialDate' => '2026-10-19', 'gameDate' => '2026-10-19T23:00:00Z', 'ifNecessary' => 'Y' ) ),
			Fixtures::ps_game( 'L', 7, 7, 139, false, 'Preview', null, null, array( 'officialDate' => '2026-10-20', 'gameDate' => '2026-10-20T23:00:00Z', 'ifNecessary' => 'Y' ) ),
		);

		return Basebelles_Postseason::build_status(
			Fixtures::ps_dates( $games ),
			Basebelles_Postseason::compute_seeds( Fixtures::al_standings_2026() ),
			'2026-10-13',
			114
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Regular season
	 * -----------------------------------------------------------------------
	 */

	public function test_regular_season_pairs_todays_game_with_the_division(): void {
		Basebelles_Test_State::$division_table  = $this->division();
		Basebelles_Test_State::$last_game       = array( 'result' => 'W', 'score' => '6-2', 'where' => '@', 'opponent' => 'KC', 'date' => '2026-09-03', 'game_pk' => 1 );
		Basebelles_Test_State::$next_games_line = 'Wed–Thu vs CWS · Fri @ DET';

		$html = $this->render( 'regularSeason' );

		$this->assertStringContainsString( 'basebelles-game-hub is-regular', $html );
		$this->assertStringContainsString( 'basebelles-today-game', $html );
		$this->assertStringContainsString( 'bb-hub-division', $html );
		$this->assertStringContainsString( '<h3 class="bb-hub-eyebrow bb-hub-division-name">AL Central</h3>', $html );
		$this->assertMatchesRegularExpression( '#bb-hub-last-result is-win">W 6-2</span>\s*<span class="bb-hub-last-opponent">@ KC</span>#', $html );
		$this->assertStringContainsString( 'AL Wild Card: 2nd · +1.5', $html );
		$this->assertStringContainsString( 'Wed–Thu vs CWS · Fri @ DET', $html );
		$this->assertStringNotContainsString( 'bb-hub-head', $html );
	}

	public function test_full_standings_replaces_the_recap_link(): void {
		Basebelles_Test_State::$division_table = $this->division();

		$html = $this->render( 'regularSeason' );

		$this->assertMatchesRegularExpression( '#<a class="bb-hub-link" href="https://www.mlb.com/standings/"[^>]*>Full Standings#', $html );
		$this->assertStringNotContainsString( 'Recap', $html );
	}

	public function test_the_guardians_row_is_flagged_and_streaks_are_coloured(): void {
		Basebelles_Test_State::$division_table = $this->division();

		$html = $this->render( 'regularSeason' );

		$this->assertSame( 1, substr_count( $html, '<tr class="is-us">' ) );
		$this->assertMatchesRegularExpression( '#<tr class="is-us">.*?Guardians.*?</tr>#s', $html );
		$this->assertStringContainsString( '<td class="is-win">W2</td>', $html );
		$this->assertStringContainsString( '<td class="is-loss">L1</td>', $html );
	}

	public function test_the_table_uses_header_cells_for_teams_and_columns(): void {
		Basebelles_Test_State::$division_table = $this->division();

		$html = $this->render( 'regularSeason' );

		$this->assertStringContainsString( '<caption class="screen-reader-text">AL Central standings</caption>', $html );
		$this->assertSame( 3, substr_count( $html, '<th scope="row"' ) );
		$this->assertSame( 6, substr_count( $html, '<th scope="col"' ) );
	}

	public function test_a_badge_stands_in_for_a_missing_logo(): void {
		Basebelles_Test_State::$division_table = $this->division();

		$html = $this->render( 'regularSeason' );

		$this->assertStringContainsString( '<span class="bb-hub-team-badge" aria-hidden="true">CWS</span>', $html );
	}

	public function test_a_division_leader_gets_no_wild_card_line_and_missing_pieces_are_skipped(): void {
		$division              = $this->division();
		$division['wild_card'] = '';

		Basebelles_Test_State::$division_table = $division;

		$html = $this->render( 'regularSeason' );

		$this->assertStringNotContainsString( 'bb-hub-wildcard', $html );
		$this->assertStringNotContainsString( 'bb-hub-last', $html );
		$this->assertStringNotContainsString( 'bb-hub-next', $html );
	}

	public function test_a_standings_error_keeps_the_card_with_a_note(): void {
		Basebelles_Test_State::$division_table = new WP_Error( 'http', 'boom' );

		$html = $this->render( 'regularSeason' );

		$this->assertStringContainsString( 'Standings are unavailable right now.', $html );
		$this->assertStringNotContainsString( 'bb-hub-standings', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Postseason
	 * -----------------------------------------------------------------------
	 */

	private function render_alcs(): string {
		Basebelles_Test_State::$postseason = $this->alcs_status();
		Basebelles_Test_State::$schedule   = Fixtures::schedule(
			array(
				Fixtures::game(
					array(
						'game_type' => 'L',
						'series'    => array( 'game_number' => 2, 'games_total' => 7, 'status_label' => 'CLE Leads 1-0' ),
					)
				),
			)
		);

		return $this->render( 'postseason' );
	}

	public function test_the_postseason_heading_names_the_round_and_game(): void {
		$html = $this->render_alcs();

		$this->assertStringContainsString( 'basebelles-game-hub is-postseason', $html );
		$this->assertStringContainsString( '<h3 class="bb-hub-title">ALCS · Game 2</h3>', $html );
		$this->assertMatchesRegularExpression( '#<p class="bb-hub-kicker">\d{4} Postseason</p>#', $html );
	}

	public function test_the_series_card_lists_every_game(): void {
		$html = $this->render_alcs();

		$this->assertStringContainsString( '<h3 class="bb-hub-eyebrow">AL Championship Series · Best of 7</h3>', $html );
		$this->assertStringContainsString( '<p class="bb-hub-series-status is-positive">CLE leads 1-0</p>', $html );
		$this->assertSame( 7, substr_count( $html, '<li class="bb-hub-series-game' ) );
		$this->assertMatchesRegularExpression( '#bb-hub-series-game is-won">\s*<span class="bb-hub-series-num">G1</span>\s*<span class="bb-hub-series-where">@ TB</span>\s*<span class="bb-hub-series-value">\s*W 7-3#', $html );
		$this->assertMatchesRegularExpression( '#bb-hub-series-game is-next" aria-current="true">\s*<span class="bb-hub-series-num">G2</span>#', $html );
		$this->assertMatchesRegularExpression( '#G3</span>\s*<span class="bb-hub-series-where">vs TB</span>#', $html );
	}

	public function test_if_necessary_games_get_an_asterisk_and_a_note(): void {
		$html = $this->render_alcs();

		$this->assertSame( 3, substr_count( $html, '<span class="bb-footnote-mark" aria-hidden="true">*</span>' ) );
		$this->assertMatchesRegularExpression( '#<p class="bb-hub-foot">\s*<span class="bb-hub-if-necessary" aria-hidden="true">\* If necessary\.</span>\s*</p>#', $html );
		// The summary at the top of the page covers the earlier rounds.
		$this->assertStringNotContainsString( 'Road here', $html );
	}

	public function test_todays_game_in_the_postseason_drops_its_series_line(): void {
		$html = $this->render_alcs();

		$this->assertStringContainsString( 'basebelles-today-game', $html );
		$this->assertStringNotContainsString( 'tg-series-status', $html );
		$this->assertStringNotContainsString( 'CLE Leads 1-0', $html );
	}

	public function test_between_rounds_shows_the_next_round_without_games(): void {
		Basebelles_Test_State::$postseason = array(
			'state'      => 'between_rounds',
			'next_round' => 'W',
			'current'    => null,
			'road'       => array(
				array( 'round' => 'ALCS', 'result' => 'Won 4-2 vs TB' ),
			),
		);

		$html = $this->render( 'postseason' );

		$this->assertStringContainsString( '<h3 class="bb-hub-title">World Series</h3>', $html );
		$this->assertStringContainsString( 'Opponent TBD', $html );
		$this->assertStringNotContainsString( 'bb-hub-series-games', $html );
		// No road line, and with no games there's no footnote either, so no footer at all.
		$this->assertStringNotContainsString( 'ALCS won 4-2 vs TB', $html );
		$this->assertStringNotContainsString( 'bb-hub-foot', $html );
	}

	public function test_once_eliminated_the_off_season_layout_takes_over(): void {
		Basebelles_Test_State::$postseason   = array( 'state' => 'eliminated' );
		Basebelles_Test_State::$spring_start = '2099-02-19';

		$html = $this->render( 'postseason' );

		$this->assertStringContainsString( 'basebelles-game-hub is-offseason', $html );
		$this->assertStringContainsString( 'bb-hub-countdown', $html );
		$this->assertStringNotContainsString( 'basebelles-today-game', $html );
	}

	public function test_a_postseason_api_error_leaves_todays_game_alone(): void {
		Basebelles_Test_State::$postseason = new WP_Error( 'http', 'boom' );

		$html = $this->render( 'postseason' );

		$this->assertStringContainsString( 'basebelles-game-hub is-solo', $html );
		$this->assertStringContainsString( 'bb-hub-grid is-single', $html );
		$this->assertStringContainsString( 'basebelles-today-game', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Off season and Spring Training
	 * -----------------------------------------------------------------------
	 */

	public function test_the_off_season_counts_down_to_spring_training(): void {
		$start = gmdate( 'Y-m-d', strtotime( '+129 days' ) );

		Basebelles_Test_State::$spring_start = $start;

		$html = $this->render( 'regularSeason', (int) gmdate( 'Y' ) - 1 );

		$this->assertStringContainsString( 'basebelles-game-hub is-offseason', $html );
		$this->assertStringContainsString( '<span class="bb-hub-count-number">129</span>', $html );
		$this->assertStringContainsString( '<span class="bb-hub-count-unit">days</span>', $html );
		$this->assertMatchesRegularExpression( '#until Spring Training begins\s*· <time datetime="' . $start . '">#', $html );
		$this->assertStringContainsString( 'See you in spring', $html );
	}

	public function test_one_day_out_is_singular_and_the_day_itself_says_so(): void {
		Basebelles_Test_State::$spring_start = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		$this->assertStringContainsString( '<span class="bb-hub-count-unit">day</span>', $this->render( 'regularSeason', (int) gmdate( 'Y' ) - 1 ) );

		Basebelles_Test_State::$spring_start = gmdate( 'Y-m-d' );
		$this->assertStringContainsString( 'Spring Training begins today', $this->render( 'regularSeason', (int) gmdate( 'Y' ) - 1 ) );
	}

	public function test_no_known_date_hides_the_number_but_keeps_the_card(): void {
		Basebelles_Test_State::$spring_start = '';

		$html = $this->render( 'regularSeason', (int) gmdate( 'Y' ) - 1 );

		$this->assertStringContainsString( 'See you in spring', $html );
		$this->assertStringNotContainsString( 'bb-hub-count-number', $html );
		$this->assertStringContainsString( 'season recap', $html );
	}

	public function test_the_recap_links_to_the_season_type_archive(): void {
		$year = (int) gmdate( 'Y' ) - 1;

		Basebelles_Test_State::$postseason = array( 'state' => 'not_qualified' );
		$this->assertStringContainsString( 'href="https://example.test/season-type/regular-season/?season_year=' . $year . '">' . $year . ' season recap</a>', $this->render( 'regularSeason', $year ) );

		Basebelles_Test_State::$postseason = array( 'state' => 'eliminated' );
		$this->assertStringContainsString( 'href="https://example.test/season-type/post-season/?season_year=' . $year . '">' . $year . ' season recap</a>', $this->render( 'regularSeason', $year ) );
	}

	public function test_the_news_card_lists_headlines_linking_out(): void {
		Basebelles_Test_State::$news = array(
			array( 'title' => 'Guards rally past Rays & even the score', 'url' => 'https://www.mlb.com/guardians/news/one', 'timestamp' => 1791871200 ),
			array( 'title' => 'ALDS comeback', 'url' => 'https://www.mlb.com/guardians/news/two', 'timestamp' => 0 ),
		);

		$html = $this->render( 'regularSeason', (int) gmdate( 'Y' ) - 1 );

		$this->assertSame( 2, substr_count( $html, '<li class="bb-hub-news-item">' ) );
		$this->assertStringContainsString( '<a href="https://www.mlb.com/guardians/news/one" target="_blank" rel="noopener noreferrer">Guards rally past Rays &amp; even the score</a>', $html );
		$this->assertStringContainsString( '<time datetime="2026-10-13T06:00:00+00:00">Oct 13</time>', $html );
		$this->assertSame( 1, substr_count( $html, '<time datetime="2026' ) );
		$this->assertStringContainsString( 'href="https://www.mlb.com/guardians/news"', $html );
	}

	public function test_an_empty_feed_says_so(): void {
		$html = $this->render( 'regularSeason', (int) gmdate( 'Y' ) - 1 );

		$this->assertStringContainsString( 'No headlines right now.', $html );
	}

	public function test_spring_training_pairs_todays_game_with_the_news(): void {
		$html = $this->render( 'springTraining' );

		$this->assertStringContainsString( 'basebelles-game-hub is-spring', $html );
		$this->assertStringContainsString( 'basebelles-today-game', $html );
		$this->assertStringContainsString( 'bb-hub-news', $html );
		$this->assertStringNotContainsString( 'bb-hub-division', $html );
	}

	public function test_an_empty_hub_renders_nothing_on_the_front_end(): void {
		Basebelles_Test_State::$postseason = new WP_Error( 'http', 'boom' );
		Basebelles_Test_State::$schedule   = new WP_Error( 'http', 'boom' );

		$this->assertSame( '', trim( $this->render( 'postseason' ) ) );
	}

	public function test_the_editor_alignment_anchor_and_class_reach_the_markup(): void {
		$html = $this->render( 'springTraining', 0, array( 'align' => 'full', 'anchor' => 'game-day', 'className' => 'is-style-x extra' ) );

		$this->assertStringContainsString( '<div class="basebelles-game-hub is-spring alignfull is-style-x extra" id="game-day">', $html );
	}

	public function test_no_alignment_means_no_align_class(): void {
		$html = $this->render( 'springTraining' );

		$this->assertStringContainsString( '<div class="basebelles-game-hub is-spring">', $html );
	}
}
