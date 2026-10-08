<?php
/**
 * Tests for the standings block template (blocks/standings/render.php).
 *
 * The template is included and its output captured, so these assert on the markup the block
 * actually emits rather than on a reimplementation of it.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StandingsBlockTest extends TestCase {

	protected function setUp(): void {
		Basebelles_Test_State::reset();
	}

	/**
	 * Render the block with a given standings payload.
	 *
	 * @param array $overrides Standings fields to replace.
	 * @return string Rendered markup.
	 */
	private function render( array $overrides = array() ): string {
		Basebelles_Test_State::$standings = Fixtures::standings( $overrides );

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/standings/render.php';

		return (string) ob_get_clean();
	}

	/**
	 * Pull the label => value pairs out of one ticker row, in document order.
	 *
	 * @param string $html Rendered markup.
	 * @param string $row Row modifier class, is-primary or is-secondary.
	 * @return array<string, string>
	 */
	private function pairs( string $html, string $row ): array {
		if ( ! preg_match( '#<dl class="bb-ticker-row ' . preg_quote( $row, '#' ) . '">(.*?)</dl>#s', $html, $matches ) ) {
			return array();
		}

		preg_match_all(
			'#<dt class="bb-ticker-label">(.*?)</dt>\s*<dd class="bb-ticker-value">(.*?)</dd>#s',
			$matches[1],
			$found,
			PREG_SET_ORDER
		);

		$pairs = array();

		foreach ( $found as $pair ) {
			$pairs[ html_entity_decode( trim( $pair[1] ), ENT_QUOTES, 'UTF-8' ) ] = trim( $pair[2] );
		}

		return $pairs;
	}

	/**
	 * Labels of the items carrying the is-negative class.
	 *
	 * @param string $html Rendered markup.
	 * @return string[]
	 */
	private function negatives( string $html ): array {
		preg_match_all(
			'#<div class="bb-ticker-item ([^"]*)">\s*<dt class="bb-ticker-label">(.*?)</dt>#s',
			$html,
			$found,
			PREG_SET_ORDER
		);

		$negatives = array();

		foreach ( $found as $item ) {
			if ( false !== strpos( $item[1], 'is-negative' ) ) {
				$negatives[] = html_entity_decode( trim( $item[2] ), ENT_QUOTES, 'UTF-8' );
			}
		}

		return $negatives;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Structure
	 * -----------------------------------------------------------------------
	 */

	public function test_the_zebra_tables_are_gone(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( '<table', $html );
		$this->assertStringNotContainsString( 'standings-table', $html );
	}

	public function test_both_rows_always_render(): void {
		// display_mode was retired: the secondary row is no longer conditional.
		$html = $this->render();

		$this->assertSame( 2, preg_match_all( '#<dl class="bb-ticker-row#', $html ) );
		$this->assertStringContainsString( 'bb-ticker-row is-primary', $html );
		$this->assertStringContainsString( 'bb-ticker-row is-secondary', $html );
		$this->assertStringNotContainsString( 'mode-standard', $html );
		$this->assertStringNotContainsString( 'mode-expanded', $html );
	}

	/**
	 * A definition list rather than div soup, so dropping the table does not cost the
	 * label-to-value association a screen reader relies on.
	 */
	public function test_labels_and_values_use_definition_list_semantics(): void {
		$html = $this->render();

		$this->assertStringContainsString( '<dt class="bb-ticker-label">', $html );
		$this->assertStringContainsString( '<dd class="bb-ticker-value">', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Primary row
	 * -----------------------------------------------------------------------
	 */

	public function test_primary_row_pairs_each_label_with_its_value(): void {
		$pairs = $this->pairs( $this->render(), 'is-primary' );

		$this->assertSame(
			array(
				'Standing' => '2nd in the AL Central',
				'W–L'      => '70-70',
				'PCT'      => '.500',
				'GB'       => '3.0',
				'WCGB'     => '-',
			),
			$pairs
		);
	}

	public function test_wins_and_losses_are_combined_into_one_item(): void {
		// The old markup had separate W and L columns.
		$pairs = $this->pairs( $this->render( array( 'wins' => 88, 'losses' => 52 ) ), 'is-primary' );

		$this->assertSame( '88-52', $pairs['W–L'] );
	}

	public function test_standing_item_is_flagged_for_its_own_layout(): void {
		$this->assertStringContainsString( 'bb-ticker-item is-standing', $this->render() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Secondary row
	 * -----------------------------------------------------------------------
	 */

	public function test_secondary_row_order_matches_the_design(): void {
		$pairs = $this->pairs( $this->render(), 'is-secondary' );

		$this->assertSame(
			array( 'L10', 'STK', 'RS', 'RA', 'DIFF', 'Home', 'Away', '>.500' ),
			array_keys( $pairs )
		);
	}

	public function test_secondary_row_values(): void {
		$pairs = $this->pairs( $this->render(), 'is-secondary' );

		$this->assertSame( '6-4', $pairs['L10'] );
		$this->assertSame( 'L2', $pairs['STK'] );
		$this->assertSame( '566', $pairs['RS'] );
		$this->assertSame( '577', $pairs['RA'] );
		$this->assertSame( '-11', $pairs['DIFF'] );
		$this->assertSame( '33-38', $pairs['Home'] );
		$this->assertSame( '37-32', $pairs['Away'] );
		$this->assertSame( '23-32', $pairs['>.500'] );
	}

	public function test_the_over_500_label_is_escaped(): void {
		$this->assertStringContainsString( '&gt;.500', $this->render() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Negative flagging
	 * -----------------------------------------------------------------------
	 */

	/**
	 * @return array<string, array{0: string, 1: string, 2: string[]}>
	 */
	public static function negative_cases(): array {
		return array(
			'losing streak and negative diff' => array( 'L2', '-11', array( 'STK', 'DIFF' ) ),
			'winning streak, negative diff'   => array( 'W3', '-11', array( 'DIFF' ) ),
			'losing streak, positive diff'    => array( 'L2', '+42', array( 'STK' ) ),
			'winning streak, positive diff'   => array( 'W5', '+8', array() ),
			'zero differential is not down'   => array( 'W1', '0', array() ),
			'placeholder streak is not a loss' => array( '-', '+3', array() ),
			'empty streak is not a loss'      => array( '', '+3', array() ),
		);
	}

	#[DataProvider( 'negative_cases' )]
	public function test_negative_stats_are_flagged( string $streak, string $diff, array $expected ): void {
		$html = $this->render(
			array(
				'streak'           => $streak,
				'run_differential' => $diff,
			)
		);

		$this->assertSame( $expected, $this->negatives( $html ) );
	}

	/**
	 * The red is reinforcement, not the message: the L prefix and the minus sign have to survive
	 * so the state is still readable without colour.
	 */
	public function test_sign_and_prefix_survive_so_colour_is_never_the_only_signal(): void {
		$pairs = $this->pairs( $this->render( array( 'streak' => 'L7', 'run_differential' => '-99' ) ), 'is-secondary' );

		$this->assertSame( 'L7', $pairs['STK'] );
		$this->assertSame( '-99', $pairs['DIFF'] );

		$positive = $this->pairs( $this->render( array( 'run_differential' => '+99' ) ), 'is-secondary' );
		$this->assertSame( '+99', $positive['DIFF'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Edge cases
	 * -----------------------------------------------------------------------
	 */

	public function test_empty_fields_still_render_both_rows(): void {
		$html = $this->render(
			array(
				'summary'          => '',
				'games_back'       => '',
				'streak'           => '',
				'run_differential' => '',
			)
		);

		$this->assertSame( 2, preg_match_all( '#<dl class="bb-ticker-row#', $html ) );
		$this->assertSame( array(), $this->negatives( $html ) );
	}

	public function test_a_zero_record_renders(): void {
		$pairs = $this->pairs( $this->render( array( 'wins' => 0, 'losses' => 0 ) ), 'is-primary' );

		$this->assertSame( '0-0', $pairs['W–L'] );
	}

	public function test_string_wins_and_losses_are_cast(): void {
		$pairs = $this->pairs( $this->render( array( 'wins' => '70', 'losses' => '70' ) ), 'is-primary' );

		$this->assertSame( '70-70', $pairs['W–L'] );
	}

	public function test_the_summary_is_escaped(): void {
		$html = $this->render( array( 'summary' => '1st <script>alert(1)</script>' ) );

		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Postseason
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render with Season Type set and a postseason status built by the real state machine.
	 *
	 * @param string     $season_type ACF season_type value.
	 * @param array|null $games Raw postseason games; null leaves the fake API's default.
	 * @param string     $today Y-m-d.
	 * @return string
	 */
	private function render_postseason( string $season_type, ?array $games, string $today = '2026-10-08' ): string {
		Basebelles_Test_State::$fields['season_settings'] = array( 'season_type' => $season_type );

		if ( null !== $games ) {
			Basebelles_Test_State::$postseason = Basebelles_Postseason::build_status(
				Fixtures::ps_dates( $games ),
				Basebelles_Postseason::compute_seeds( Fixtures::al_standings_2026() ),
				$today,
				114
			);
		}

		return $this->render();
	}

	public function test_postseason_mid_series_ticker(): void {
		$html = $this->render_postseason( 'postseason', Fixtures::alds_2026_before_game_four() );

		$this->assertStringContainsString( 'is-postseason is-in-series', $html );

		$primary = $this->pairs( $html, 'is-primary' );
		$this->assertSame( array( 'Round', 'Matchup', 'Series · Best of 5', 'Wild Card' ), array_keys( $primary ) );
		$this->assertSame( 'AL Division Series', $primary['Round'] );
		$this->assertSame( 'CWS leads 2-1', $primary['Series · Best of 5'] );
		$this->assertSame( 'Bye', $primary['Wild Card'] );

		// Seeds sit next to each abbreviation.
		$this->assertMatchesRegularExpression( '#CLE</span> <span class="bb-team-seed">\(2\)</span>#', $primary['Matchup'] );
		$this->assertMatchesRegularExpression( '#CWS</span> <span class="bb-team-seed">\(6\)</span>#', $primary['Matchup'] );

		$games = $this->pairs( $html, 'is-secondary is-games' );
		$this->assertSame( array( 'G1 · vs CWS', 'G2 · vs CWS', 'G3 · @CWS', 'G4 · @CWS', 'G5 · vs CWS' ), array_keys( $games ) );
		$this->assertSame( 'W 9-3', $games['G3 · @CWS'] );
		$this->assertSame( array( 'Series · Best of 5', 'G1 · vs CWS', 'G2 · vs CWS' ), $this->negatives( $html ) );
		$this->assertMatchesRegularExpression( '#bb-ticker-item is-next">\s*<dt class="bb-ticker-label">G4 · @CWS#', $html );
	}

	public function test_wild_card_season_type_also_switches_to_the_postseason_ticker(): void {
		$html = $this->render_postseason( 'wildCard', Fixtures::alds_2026_before_game_four() );

		$this->assertStringContainsString( 'is-postseason', $html );
	}

	public function test_eliminated_ticker(): void {
		$games    = Fixtures::alds_2026_before_game_four();
		$games[3] = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 1, 3, array( 'officialDate' => '2026-10-08' ) );

		$html    = $this->render_postseason( 'postseason', $games, '2026-10-09' );
		$primary = $this->pairs( $html, 'is-primary' );

		$this->assertSame( 'Eliminated', $primary['Season'] );
		$this->assertSame( 'Lost 1-3', $primary['ALDS'] );
		$this->assertSame( '1-3', $primary['Postseason'] );
		$this->assertArrayNotHasKey( 'Wild Card', $primary );
		$this->assertSame( 'Not needed', $this->pairs( $html, 'is-secondary is-games' )['G5 · vs CWS'] );
	}

	public function test_between_rounds_ticker_has_no_games_row(): void {
		$games    = Fixtures::alds_2026_before_game_four();
		$games[3] = Fixtures::ps_game( 'D', 4, 5, 145, false, 'Final', 5, 2, array( 'officialDate' => '2026-10-08' ) );
		$games[4] = Fixtures::ps_game( 'D', 5, 5, 145, true, 'Final', 4, 3, array( 'officialDate' => '2026-10-10' ) );

		$html    = $this->render_postseason( 'postseason', $games, '2026-10-11' );
		$primary = $this->pairs( $html, 'is-primary' );

		$this->assertSame( 'ALCS', $primary['Round'] );
		$this->assertStringContainsString( 'bb-team is-tbd">TBD', $primary['Matchup'] );
		$this->assertSame( 'Won 3-2 vs CWS', $primary['ALDS'] );
		$this->assertSame( 'Bye', $primary['Wild Card'] );
		$this->assertStringNotContainsString( 'is-games', $html );
	}

	public function test_not_qualified_falls_back_to_the_regular_season_standings(): void {
		// The fake API defaults to not_qualified.
		$html = $this->render_postseason( 'postseason', null );

		$this->assertStringNotContainsString( 'is-postseason', $html );
		$this->assertSame( '2nd in the AL Central', $this->pairs( $html, 'is-primary' )['Standing'] );
	}

	public function test_a_postseason_api_error_falls_back_to_the_regular_season_standings(): void {
		Basebelles_Test_State::$postseason = new WP_Error( 'http_error', 'boom' );

		$html = $this->render_postseason( 'postseason', null );

		$this->assertStringNotContainsString( 'is-postseason', $html );
		$this->assertStringContainsString( 'bb-ticker-row is-secondary', $html );
	}

	public function test_regular_season_type_ignores_the_postseason(): void {
		$html = $this->render_postseason( 'regularSeason', Fixtures::alds_2026_before_game_four() );

		$this->assertStringNotContainsString( 'is-postseason', $html );
	}

	public function test_an_api_error_renders_nothing_on_the_front_end(): void {
		Basebelles_Test_State::$standings = new WP_Error( 'http_error', 'boom' );

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/standings/render.php';
		$html = (string) ob_get_clean();

		// is_admin() is stubbed false, so a visitor sees an empty block rather than a warning.
		$this->assertSame( '', trim( $html ) );
	}
}
