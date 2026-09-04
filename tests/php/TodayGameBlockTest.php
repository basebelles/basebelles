<?php
/**
 * Tests for the today-game block template (blocks/today-game/render.php).
 *
 * This covers the doubleheader shell the template itself builds -- the phase pre-pass, which game
 * opens, the heading, and which card carries the hidden attribute -- none of which lives in
 * Basebelles_Today_Game_Panels.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class TodayGameBlockTest extends TestCase {

	protected function setUp(): void {
		Basebelles_Test_State::reset();
		Basebelles_Test_State::$fields['game_date'] = '2026-09-04';
	}

	/**
	 * Render the block for a given set of games.
	 *
	 * @param array $games Normalized game arrays.
	 * @return string Rendered markup.
	 */
	private function render( array $games ): string {
		Basebelles_Test_State::$schedule = Fixtures::schedule( $games );

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/today-game/render.php';

		return (string) ob_get_clean();
	}

	/**
	 * Which game cards are visible, by game_pk.
	 *
	 * @param string $html Rendered markup.
	 * @return string[]
	 */
	private function visible_cards( string $html ): array {
		preg_match_all( '#<div class="game-card"([^>]*)>#', $html, $found, PREG_SET_ORDER );

		$visible = array();

		foreach ( $found as $card ) {
			if ( false !== strpos( $card[1], ' hidden' ) ) {
				continue;
			}

			if ( preg_match( '#data-game-card="(\d+)"#', $card[1], $pk ) ) {
				$visible[] = $pk[1];
			}
		}

		return $visible;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Single game
	 * -----------------------------------------------------------------------
	 */

	public function test_a_single_game_gets_no_switcher(): void {
		$html = $this->render( array( Fixtures::game() ) );

		$this->assertStringNotContainsString( 'tg-doubleheader', $html );
		$this->assertStringNotContainsString( 'tg-game-switcher', $html );
		$this->assertStringNotContainsString( 'has-doubleheader', $html );
		$this->assertStringNotContainsString( 'hidden', $html );
	}

	public function test_a_single_game_still_renders_its_card(): void {
		$html = $this->render( array( Fixtures::game() ) );

		$this->assertStringContainsString( 'game-card', $html );
		$this->assertStringContainsString( 'tg-tabs', $html );
		$this->assertStringContainsString( '2:10 PM EDT', $html );
	}

	public function test_an_off_day_shows_the_off_day_image_only(): void {
		Basebelles_Test_State::$schedule = array(
			'day_date' => 'Mon 9/7',
			'off_day'  => true,
			'games'    => array(),
		);

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/today-game/render.php';
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'off-day-box', $html );
		$this->assertStringNotContainsString( 'game-card', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Doubleheader shell
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Two games and one panel.
	 */
	public function test_a_doubleheader_wraps_both_games_in_one_switcher(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::live_game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertStringContainsString( 'has-doubleheader', $html );
		$this->assertStringContainsString( 'tg-doubleheader', $html );
		$this->assertStringContainsString( 'tg-dh-panel', $html );
		$this->assertSame( 2, preg_match_all( '#data-game-card="#', $html ) );
		$this->assertSame( 1, preg_match_all( '#class="tg-game-switcher"#', $html ) );
	}

	public function test_the_heading_names_the_day_and_the_matchup(): void {
		$html = $this->render(
			array(
				Fixtures::game(),
				Fixtures::game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertStringContainsString( 'Fri 9/4 · Tigers @ Guardians', $html );
	}

	/**
	 * Exactly one card visible, and it is the live one -- the whole point of the switcher is that
	 * a finished opener stops taking up the space above a game in progress.
	 */
	public function test_a_doubleheader_opens_on_the_live_game(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::live_game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertSame( array( '777002' ), $this->visible_cards( $html ) );
		$this->assertStringContainsString( 'data-active-game="777002"', $html );
	}

	public function test_a_doubleheader_opens_on_the_delayed_game_over_the_finished_one(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::delayed_start( 'Delayed Start: Rain', array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertSame( array( '777002' ), $this->visible_cards( $html ) );
	}

	public function test_two_scheduled_games_open_on_the_earlier_one(): void {
		$html = $this->render(
			array(
				Fixtures::game(),
				Fixtures::game(
					array(
						'game_pk'     => 777002,
						'game_number' => 2,
						'game_time'   => 'TBD (30m after G1)',
						'sort_time'   => time() + 18000,
					)
				),
			)
		);

		$this->assertSame( array( '777001' ), $this->visible_cards( $html ) );
	}

	public function test_two_finished_games_open_on_the_later_one(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::final_game( 4, 3, array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertSame( array( '777002' ), $this->visible_cards( $html ) );
	}

	/**
	 * Both games stay in the DOM. Hiding rather than unmounting is what lets each keep its own
	 * live polling and its own Status/Plays/Stats/Players selection across a switch.
	 */
	public function test_the_inactive_game_is_hidden_not_dropped(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::live_game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertSame( 2, preg_match_all( '#<div class="tg-tabs#', $html ) );
		$this->assertSame( 1, preg_match_all( '#<div class="game-card"[^>]* hidden>#', $html ) );
		$this->assertStringContainsString( 'data-game-card="777001"', $html );
	}

	/** The pill above already says "Game 1", so the per-card label would just repeat it. */
	public function test_the_per_card_game_label_is_suppressed_on_a_doubleheader(): void {
		$html = $this->render(
			array(
				Fixtures::game(),
				Fixtures::game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertStringNotContainsString( 'class="game-label"', $html );
	}

	public function test_switcher_pills_reflect_each_game_state(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::live_game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertStringContainsString( 'Final 2', $html );
		$this->assertStringContainsString( 'Game 2 &middot; Live', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Delay states in the card header
	 * -----------------------------------------------------------------------
	 */

	public function test_a_delayed_game_header_does_not_claim_to_be_live(): void {
		$html = $this->render( array( Fixtures::delayed_in_progress() ) );

		$this->assertStringContainsString( 'is-delayed', $html );
		$this->assertStringContainsString( 'Rain delay', $html );
		$this->assertStringNotContainsString( 'is-live', $html );
	}

	public function test_a_live_game_header_pulses(): void {
		$html = $this->render( array( Fixtures::live_game() ) );

		$this->assertStringContainsString( 'game-time is-live', $html );
		$this->assertStringContainsString( 'tg-live-dot', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Feed fetching and script wiring
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The live feed has to be fetched for every game before the first card is drawn, because the
	 * switcher above the cards needs every game's phase to pick its default.
	 */
	public function test_the_live_feed_is_fetched_once_per_game(): void {
		$this->render(
			array(
				Fixtures::game(),
				Fixtures::game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertSame( array( 777001, 777002 ), Basebelles_Test_State::$live_feed_calls );
	}

	public function test_a_live_feed_error_does_not_break_the_render(): void {
		Basebelles_Test_State::$live_feed_default = new WP_Error( 'http_error', 'boom' );

		$html = $this->render( array( Fixtures::live_game() ) );

		$this->assertStringContainsString( 'game-card', $html );
		$this->assertStringContainsString( 'tg-tabs', $html );
	}

	public function test_the_polling_script_is_enqueued_once_with_its_rest_url(): void {
		$this->render(
			array(
				Fixtures::game(),
				Fixtures::game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertCount( 1, Basebelles_Test_State::$enqueued_scripts );
		$this->assertSame( 'basebelles-today-game', Basebelles_Test_State::$enqueued_scripts[0][0] );

		$this->assertCount( 1, Basebelles_Test_State::$localized_scripts );
		$localized = Basebelles_Test_State::$localized_scripts[0];
		$this->assertSame( 'bbTodayGame', $localized[1] );
		$this->assertArrayHasKey( 'restUrl', $localized[2] );
		$this->assertStringContainsString( 'basebelles/v1/today-game/', $localized[2]['restUrl'] );
	}

	public function test_each_tab_instance_carries_its_own_game_pk_and_phase(): void {
		$html = $this->render(
			array(
				Fixtures::final_game( 2, 6 ),
				Fixtures::live_game( array( 'game_pk' => 777002, 'game_number' => 2 ) ),
			)
		);

		$this->assertStringContainsString( 'data-game-pk="777001"', $html );
		$this->assertStringContainsString( 'data-game-pk="777002"', $html );
		$this->assertStringContainsString( 'data-phase="final"', $html );
		$this->assertStringContainsString( 'data-phase="live"', $html );
	}

	public function test_an_api_error_renders_nothing_on_the_front_end(): void {
		Basebelles_Test_State::$schedule = new WP_Error( 'http_error', 'boom' );

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/today-game/render.php';
		$html = (string) ob_get_clean();

		$this->assertSame( '', trim( $html ) );
	}
}
