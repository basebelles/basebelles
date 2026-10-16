<?php
/**
 * Team colours from team-info/list.json, ready for a background: the primary as the
 * background, the second colour as an accent, and a text colour that passes WCAG AA on it.
 *
 * Pure logic, no markup. Used by the team archive header and the Game Matchup block.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Team_Colors {

	/** The site's cream, used for text on a dark team colour. */
	const CREAM = '#F3EFE0';

	/** Dark ink, used for text when cream would fail contrast (Giants, Marlins, Orioles...). */
	const INK = '#111111';

	/** Last resort when even dark ink falls short (Phillies red: 4.1:1 with ink, 4.8:1 with black). */
	const BLACK = '#000000';

	/** WCAG AA for normal text. */
	const MIN_CONTRAST = 4.5;

	/**
	 * Colours for a team.
	 *
	 * @param array $team A list.json entry (anything with a 'colors' list of array( 'hex' => '#RRGGBB' )).
	 * @return array{bg: string, fg: string, accent: string, light: bool}|array Empty when the team has no usable colours.
	 */
	public static function for_team( array $team ): array {
		$hexes = array();

		foreach ( (array) ( $team['colors'] ?? array() ) as $color ) {
			$hex = strtoupper( (string) ( $color['hex'] ?? '' ) );

			if ( preg_match( '/^#[0-9A-F]{6}$/', $hex ) ) {
				$hexes[] = $hex;
			}
		}

		if ( ! $hexes ) {
			return array();
		}

		$bg    = $hexes[0];
		$light = self::contrast( $bg, self::CREAM ) < self::MIN_CONTRAST;
		$fg    = self::CREAM;

		if ( $light ) {
			$fg = self::contrast( $bg, self::INK ) >= self::MIN_CONTRAST ? self::INK : self::BLACK;
		}

		return array(
			'bg'     => $bg,
			'fg'     => $fg,
			'accent' => $hexes[1] ?? $fg,
			'light'  => $light,
		);
	}

	/**
	 * The colours as CSS custom properties for a style attribute.
	 *
	 * @param array $colors What for_team() returned.
	 * @return string '' when there are no colours.
	 */
	public static function style( array $colors ): string {
		if ( empty( $colors['bg'] ) ) {
			return '';
		}

		return sprintf(
			'--bb-team-bg:%1$s;--bb-team-fg:%2$s;--bb-team-accent:%3$s;',
			$colors['bg'],
			$colors['fg'],
			$colors['accent']
		);
	}

	/**
	 * WCAG relative luminance of a #RRGGBB colour.
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	public static function luminance( string $hex ): float {
		$channels = array_map(
			static function ( $pair ) {
				$value = hexdec( $pair ) / 255;
				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( substr( $hex, 1 ), 2 )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * WCAG contrast ratio between two #RRGGBB colours.
	 *
	 * @param string $one First colour.
	 * @param string $two Second colour.
	 * @return float
	 */
	public static function contrast( string $one, string $two ): float {
		$first  = self::luminance( $one );
		$second = self::luminance( $two );

		return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
	}
}
