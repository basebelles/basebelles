<?php
/**
 * Tests for the Game Matchup block template (blocks/matchup/render.php).
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class MatchupBlockTest extends TestCase {

	private const POST_ID = 42;

	protected function setUp(): void {
		Basebelles_Test_State::reset();

		Basebelles_Test_State::$team_info = array(
			'white-sox' => array(
				'name'          => 'Chicago White Sox',
				'taxonomy_term' => 'chicago-white-sox',
				'short_name'    => 'White Sox',
				'colors'        => array(
					array( 'hex' => '#27251F' ),
					array( 'hex' => '#C4CED4' ),
				),
			),
			'marlins'   => array(
				'name'          => 'Miami Marlins',
				'taxonomy_term' => 'miami-marlins',
				'short_name'    => 'Marlins',
				'colors'        => array(
					array( 'hex' => '#00A3E0' ),
					array( 'hex' => '#EF3340' ),
				),
			),
		);
	}

	private static function term( string $taxonomy, string $name, string $slug ): object {
		return (object) array(
			'name'     => $name,
			'slug'     => $slug,
			'taxonomy' => $taxonomy,
		);
	}

	private static function set_terms( string $taxonomy, array $terms ): void {
		Basebelles_Test_State::$post_terms[ self::POST_ID ][ $taxonomy ] = $terms;
	}

	/**
	 * Render the block the way a template does: the post arrives through block context.
	 *
	 * @param array $context Block context.
	 * @param bool  $is_preview Editor render.
	 * @return string
	 */
	private function render( array $context = array( 'postId' => self::POST_ID ), bool $is_preview = false ): string {
		$block = array();

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/matchup/render.php';

		return (string) ob_get_clean();
	}

	public function test_opponent_in_team_colours_with_pills_and_link(): void {
		self::set_terms( 'team', array( self::term( 'team', 'Chicago White Sox', 'chicago-white-sox' ) ) );
		self::set_terms( 'season-type', array( self::term( 'season-type', 'Post-season', 'post-season' ) ) );
		self::set_terms( 'venue-type', array( self::term( 'venue-type', 'Away', 'away' ), self::term( 'venue-type', 'Home', 'home' ) ) );

		$html = $this->render();

		$this->assertStringContainsString( 'class="basebelles-matchup has-team-colors"', $html );
		$this->assertStringContainsString( '--bb-team-bg:#27251F;--bb-team-fg:#F3EFE0;--bb-team-accent:#C4CED4;', $html );
		$this->assertStringContainsString( 'Guardians vs Chicago White Sox</h2>', $html );
		$this->assertStringContainsString( 'logos/guardians.png', $html );
		$this->assertStringContainsString( 'logos/white-sox.png', $html );
		$this->assertStringContainsString( 'href="https://example.test/team/chicago-white-sox/">All White Sox games', $html );

		preg_match_all( '#class="matchup-pill"[^>]*>([^<]+)<#', $html, $pills );
		$this->assertSame( array( 'Post-season', 'Away', 'Home' ), $pills[1] );
	}

	public function test_light_team_colour_marks_the_card_light(): void {
		self::set_terms( 'team', array( self::term( 'team', 'Miami Marlins', 'miami-marlins' ) ) );

		$html = $this->render();

		$this->assertStringContainsString( 'has-team-colors is-light', $html );
		$this->assertStringContainsString( '--bb-team-fg:#111111;', $html );
	}

	public function test_team_missing_from_list_falls_back_to_site_colours_without_logo(): void {
		self::set_terms( 'team', array( self::term( 'team', 'Expos', 'montreal-expos' ) ) );

		$html = $this->render();

		$this->assertStringContainsString( 'class="basebelles-matchup"', $html );
		$this->assertStringNotContainsString( '--bb-team-bg', $html );
		$this->assertStringContainsString( 'Guardians vs Expos</h2>', $html );
		$this->assertStringContainsString( 'All Expos games', $html );
		$this->assertStringNotContainsString( 'matchup-vs', $html );
	}

	public function test_guardians_term_is_not_an_opponent(): void {
		self::set_terms(
			'team',
			array(
				self::term( 'team', 'Cleveland Guardians', 'cleveland-guardians' ),
				self::term( 'team', 'Chicago White Sox', 'chicago-white-sox' ),
			)
		);

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<section' ) );
		$this->assertStringNotContainsString( 'Guardians vs Cleveland', $html );
	}

	public function test_no_pills_list_without_season_or_venue_terms(): void {
		self::set_terms( 'team', array( self::term( 'team', 'Chicago White Sox', 'chicago-white-sox' ) ) );

		$this->assertStringNotContainsString( 'matchup-pills', $this->render() );
	}

	public function test_no_team_renders_nothing_on_the_front_end(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_no_team_shows_a_placeholder_in_the_editor(): void {
		$this->assertStringContainsString( 'basebelles-matchup--placeholder', $this->render( array(), true ) );
	}

	public function test_post_id_falls_back_to_acf_post_id(): void {
		self::set_terms( 'team', array( self::term( 'team', 'Chicago White Sox', 'chicago-white-sox' ) ) );

		$post_id = self::POST_ID;
		$context = array();
		$block   = array();

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/matchup/render.php';
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Guardians vs Chicago White Sox', $html );
	}
}
