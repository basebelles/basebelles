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

	public function test_an_api_error_renders_nothing_on_the_front_end(): void {
		Basebelles_Test_State::$standings = new WP_Error( 'http_error', 'boom' );

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/standings/render.php';
		$html = (string) ob_get_clean();

		// is_admin() is stubbed false, so a visitor sees an empty block rather than a warning.
		$this->assertSame( '', trim( $html ) );
	}
}
