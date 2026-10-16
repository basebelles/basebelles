<?php
/**
 * Tests for Basebelles_Team_Colors (helpers/class-team-colors.php).
 *
 * The colours are the real ones from team-info/list.json.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class TeamColorsTest extends TestCase {

	private static function team( string ...$hexes ): array {
		return array(
			'colors' => array_map(
				static function ( $hex ) {
					return array( 'hex' => $hex );
				},
				$hexes
			),
		);
	}

	public function test_dark_primary_gets_cream_text_and_second_colour_as_accent(): void {
		$colors = Basebelles_Team_Colors::for_team( self::team( '#27251F', '#C4CED4', '#FFFFFF' ) );

		$this->assertSame( '#27251F', $colors['bg'] );
		$this->assertSame( Basebelles_Team_Colors::CREAM, $colors['fg'] );
		$this->assertSame( '#C4CED4', $colors['accent'] );
		$this->assertFalse( $colors['light'] );
	}

	public function test_light_primary_switches_to_dark_ink(): void {
		// Marlins blue and Giants orange both fall under 4.5:1 with cream.
		foreach ( array( '#00A3E0', '#FD5A1E' ) as $hex ) {
			$colors = Basebelles_Team_Colors::for_team( self::team( $hex, '#000000' ) );

			$this->assertTrue( $colors['light'], $hex );
			$this->assertSame( Basebelles_Team_Colors::INK, $colors['fg'], $hex );
		}
	}

	public function test_falls_back_to_black_when_ink_also_fails(): void {
		// Phillies red: 4.0:1 with cream, 4.1:1 with ink, 4.8:1 with black.
		$colors = Basebelles_Team_Colors::for_team( self::team( '#E81828', '#002D72' ) );

		$this->assertTrue( $colors['light'] );
		$this->assertSame( Basebelles_Team_Colors::BLACK, $colors['fg'] );
	}

	public function test_chosen_text_always_passes_aa(): void {
		$teams = json_decode( (string) file_get_contents( BASEBELLES_PLUGIN_DIR . '/team-info/list.json' ), true );

		foreach ( $teams as $key => $team ) {
			$colors = Basebelles_Team_Colors::for_team( $team );

			$this->assertNotEmpty( $colors, $key );
			$this->assertGreaterThanOrEqual( 4.5, Basebelles_Team_Colors::contrast( $colors['bg'], $colors['fg'] ), $key );
		}
	}

	public function test_lowercase_hex_is_normalised_and_bad_values_skipped(): void {
		$colors = Basebelles_Team_Colors::for_team( self::team( 'not-a-colour', '#00385d', '#e50022' ) );

		$this->assertSame( '#00385D', $colors['bg'] );
		$this->assertSame( '#E50022', $colors['accent'] );
	}

	public function test_single_colour_uses_text_colour_as_accent(): void {
		$colors = Basebelles_Team_Colors::for_team( self::team( '#00385D' ) );

		$this->assertSame( $colors['fg'], $colors['accent'] );
	}

	public function test_no_colours_returns_empty_and_no_style(): void {
		$this->assertSame( array(), Basebelles_Team_Colors::for_team( array() ) );
		$this->assertSame( '', Basebelles_Team_Colors::style( array() ) );
	}

	public function test_style_sets_the_three_custom_properties(): void {
		$style = Basebelles_Team_Colors::style( Basebelles_Team_Colors::for_team( self::team( '#00385D', '#E50022' ) ) );

		$this->assertSame( '--bb-team-bg:#00385D;--bb-team-fg:#F3EFE0;--bb-team-accent:#E50022;', $style );
	}
}
