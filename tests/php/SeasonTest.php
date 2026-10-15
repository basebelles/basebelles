<?php
/**
 * Tests for Basebelles_Season (helpers/class-season.php) and the season header's off-season view.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class SeasonTest extends TestCase {

	protected function setUp(): void {
		Basebelles_Test_State::reset();
	}

	/**
	 * Set Season Settings.
	 *
	 * @param array $settings season_settings sub-fields.
	 * @return void
	 */
	private function settings( array $settings ): void {
		Basebelles_Test_State::$fields['season_settings'] = $settings;
	}

	private function render_header(): string {
		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/season-header/render.php';

		return (string) ob_get_clean();
	}

	public function test_the_setting_is_the_starting_point(): void {
		$this->settings( array( 'season_type' => 'regularSeason' ) );
		$this->assertSame( 'regularSeason', Basebelles_Season::current_type() );

		$this->settings( array( 'season_type' => 'offSeason' ) );
		$this->assertTrue( Basebelles_Season::is_off_season() );
	}

	public function test_postseason_is_spelled_the_way_the_header_expects(): void {
		$this->settings( array( 'season_type' => 'postseason' ) );

		$this->assertSame( 'postSeason', Basebelles_Season::current_type() );
	}

	public function test_no_setting_means_regular_season(): void {
		$this->assertSame( 'regularSeason', Basebelles_Season::current_type() );
	}

	public function test_a_past_season_year_is_the_off_season(): void {
		$this->settings(
			array(
				'season_type'    => 'regularSeason',
				'current_season' => 2001,
			)
		);

		$this->assertTrue( Basebelles_Season::is_off_season() );
	}

	public function test_past_end_dates_move_each_phase_on(): void {
		$this->settings( array( 'season_type' => 'springTraining' ) );
		Basebelles_Test_State::$fields['spring_end'] = 'March 20, 2000';
		$this->assertSame( 'regularSeason', Basebelles_Season::current_type() );

		Basebelles_Test_State::$fields['reg_end'] = 'September 30, 2000';
		$this->assertSame( 'postSeason', Basebelles_Season::current_type() );

		Basebelles_Test_State::$fields['post_end'] = 'November 1, 2000';
		$this->assertSame( 'offSeason', Basebelles_Season::current_type() );
	}

	public function test_an_unset_end_date_never_counts_as_past(): void {
		$this->settings( array( 'season_type' => 'postseason' ) );
		Basebelles_Test_State::$fields['post_end'] = '';

		$this->assertFalse( Basebelles_Season::is_off_season() );
	}

	public function test_last_world_series_year_from_either_date_format(): void {
		Basebelles_Test_State::$fields['last_world_series_win'] = 'October 11, 1948';
		$this->assertSame( '1948', Basebelles_Season::last_world_series_year() );

		Basebelles_Test_State::$fields['last_world_series_win'] = '19481011';
		$this->assertSame( '1948', Basebelles_Season::last_world_series_year() );

		Basebelles_Test_State::$fields['last_world_series_win'] = '';
		$this->assertSame( '', Basebelles_Season::last_world_series_year() );
	}

	public function test_off_season_image_defaults_to_the_bundled_picture(): void {
		$this->assertStringEndsWith( 'blocks/season-header/off-season.jpg', Basebelles_Season::off_season_image_url() );
	}

	public function test_off_season_image_uses_the_custom_picture(): void {
		$this->settings( array( 'off_season_image' => 'https://example.test/uploads/snowy-field.jpg' ) );
		$this->assertSame( 'https://example.test/uploads/snowy-field.jpg', Basebelles_Season::off_season_image_url() );

		$this->settings( array( 'off_season_image' => array( 'url' => 'https://example.test/uploads/array.jpg' ) ) );
		$this->assertSame( 'https://example.test/uploads/array.jpg', Basebelles_Season::off_season_image_url() );
	}

	public function test_the_header_shows_the_picture_and_the_last_title_in_the_off_season(): void {
		$this->settings(
			array(
				'season_type'      => 'offSeason',
				'off_season_image' => 'https://example.test/uploads/snowy-field.jpg',
			)
		);
		Basebelles_Test_State::$fields['last_world_series_win'] = 'October 11, 1948';

		$html = $this->render_header();

		$this->assertStringContainsString( 'src="https://example.test/uploads/snowy-field.jpg"', $html );
		$this->assertMatchesRegularExpression( '#off-season-image"[^>]*/>\s*<p class="off-season-last-ws">The Guardians last won the World Series in 1948</p>#', $html );
		$this->assertStringNotContainsString( 'season-col', $html );
	}

	public function test_the_header_skips_the_last_title_line_when_it_isnt_set(): void {
		$this->settings( array( 'season_type' => 'offSeason' ) );

		$html = $this->render_header();

		$this->assertStringContainsString( 'blocks/season-header/off-season.jpg', $html );
		$this->assertStringNotContainsString( 'off-season-last-ws', $html );
	}

	public function test_the_header_shows_the_timeline_outside_the_off_season(): void {
		$this->settings( array( 'season_type' => 'regularSeason' ) );
		Basebelles_Test_State::$fields['last_world_series_win'] = 'October 11, 1948';

		$html = $this->render_header();

		$this->assertStringContainsString( 'bb-tl-bar', $html );
		$this->assertSame( 3, preg_match_all( '#class="bb-tl-label #', $html ) );
		$this->assertStringNotContainsString( 'off-season', $html );
	}

	public function test_the_off_season_keeps_the_timeline_above_the_picture(): void {
		$this->settings( array( 'season_type' => 'offSeason' ) );

		$html = $this->render_header();

		$this->assertStringContainsString( 'bb-tl-bar', $html );
		$this->assertLessThan( strpos( $html, 'off-season-container' ), strpos( $html, 'bb-tl-bar' ) );
		$this->assertStringContainsString( 'Season complete', $html );
	}

	public function test_the_header_draws_real_widths_and_the_record_from_settings(): void {
		$this->settings(
			array(
				'season_type'    => 'regularSeason',
				'current_season' => (int) gmdate( 'Y' ),
			)
		);
		// Relative to today, so the regular season is always the one under way.
		$day  = static fn ( int $offset ): string => gmdate( 'F j, Y', time() + $offset * DAY_IN_SECONDS );
		$year = gmdate( 'Y' );
		Basebelles_Test_State::$fields['spring_start']  = $day( -60 );
		Basebelles_Test_State::$fields['spring_end']    = $day( -31 );
		Basebelles_Test_State::$fields['spring_record'] = '6 – 4';
		Basebelles_Test_State::$fields['reg_start']     = $day( -30 );
		Basebelles_Test_State::$fields['reg_end']       = $day( 30 );
		Basebelles_Test_State::$fields['reg_record']    = '85 – 77';
		Basebelles_Test_State::$fields['reg_standing']  = 'AL Central champs';
		Basebelles_Test_State::$fields['post_start']    = $day( 31 );
		Basebelles_Test_State::$fields['post_end']      = $day( 60 );

		$post_span = gmdate( 'M j', time() + 31 * DAY_IN_SECONDS ) . ' – ' . gmdate( 'M j', time() + 60 * DAY_IN_SECONDS );

		$html = $this->render_header();

		$this->assertStringContainsString( '<span class="bb-tl-result">6 – 4</span>', $html );
		$this->assertStringContainsString( 'Now: Regular season · 85 – 77 · AL Central champs', $html );
		$this->assertStringContainsString( '<span class="bb-tl-result">' . $post_span . '</span>', $html );
		$this->assertStringContainsString( 'bb-tl-pin', $html );
		$this->assertStringContainsString( '/season-type/regular-season/?season_year=' . $year, $html );
	}
}
