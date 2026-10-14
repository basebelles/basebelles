<?php
/**
 * Which part of the season it is, worked out the same way everywhere.
 *
 * The Season Settings "Current Type" is the starting point, but the dates on the same page can
 * move it on: a past Current Season, or a phase whose end date has passed, means the next phase
 * (and the off season after the postseason). The season header, Today's Game and the standings
 * all ask this, so the off-season picture and the hidden blocks always switch together.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Season {

	/**
	 * Parse an ACF date picker value (return format "F j, Y") into a date in the site timezone.
	 *
	 * The value has no time or timezone, so strtotime() would read it as midnight UTC and
	 * wp_date() would then shift it back a day for sites west of UTC.
	 *
	 * @param mixed $value Date string from ACF.
	 * @return DateTimeImmutable|null Null when empty, 'TBD' or unparseable.
	 */
	public static function parse_date( $value ) {
		if ( empty( $value ) || 'TBD' === $value ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( '!F j, Y', (string) $value, wp_timezone() );

		if ( false === $date ) {
			try {
				$date = new DateTimeImmutable( (string) $value, wp_timezone() );
			} catch ( Exception $e ) {
				return null;
			}
		}

		return $date;
	}

	/**
	 * The effective season type: offSeason, springTraining, regularSeason or postSeason.
	 *
	 * Note 'postSeason' (the season header's spelling), not the ACF/MLB 'postseason'.
	 *
	 * @return string
	 */
	public static function current_type() {
		$settings = get_field( 'season_settings', 'option' );
		$settings = is_array( $settings ) ? $settings : array();
		$type     = (string) ( $settings['season_type'] ?? '' );
		$type     = '' !== $type ? $type : 'regularSeason';
		$type     = ( 'postseason' === $type ) ? 'postSeason' : $type;
		$season   = (int) ( $settings['current_season'] ?? 0 );
		$season   = $season >= 1900 ? $season : (int) wp_date( 'Y' );
		$today    = wp_date( 'Y-m-d' );

		$is_past = static function ( $field ) use ( $today ) {
			$date = self::parse_date( get_field( $field, 'option' ) );

			return $date && $date->format( 'Y-m-d' ) < $today;
		};

		// 1. A past season year is over, whatever the type says.
		if ( $season < (int) wp_date( 'Y' ) ) {
			return 'offSeason';
		}

		// 2-4. Each phase whose end date has passed hands on to the next one.
		if ( 'springTraining' === $type && $is_past( 'spring_end' ) ) {
			$type = 'regularSeason';
		}

		if ( 'regularSeason' === $type && $is_past( 'reg_end' ) ) {
			$type = 'postSeason';
		}

		if ( 'postSeason' === $type && $is_past( 'post_end' ) ) {
			$type = 'offSeason';
		}

		return $type;
	}

	/**
	 * Whether it's the off season.
	 *
	 * @return bool
	 */
	public static function is_off_season() {
		return 'offSeason' === self::current_type();
	}

	/**
	 * Year of the last World Series win, from Guardians Settings, or '' when it isn't set.
	 *
	 * ACF returns "F j, Y"; older saves may be the raw Ymd.
	 *
	 * @return string
	 */
	public static function last_world_series_year() {
		$raw  = trim( (string) get_field( 'last_world_series_win', 'option' ) );
		$date = '' !== $raw ? DateTimeImmutable::createFromFormat( '!F j, Y', $raw ) : false;

		if ( false !== $date ) {
			return $date->format( 'Y' );
		}

		return preg_match( '/^(\d{4})/', $raw, $match ) ? $match[1] : '';
	}

	/**
	 * The off-season picture: the custom one from Season Settings, or the bundled default.
	 *
	 * @return string URL.
	 */
	public static function off_season_image_url() {
		$settings = get_field( 'season_settings', 'option' );
		$custom   = is_array( $settings ) ? ( $settings['off_season_image'] ?? '' ) : '';

		// The field returns a URL, but an ID or array from an older field setting still works.
		if ( is_array( $custom ) ) {
			$custom = $custom['url'] ?? '';
		} elseif ( is_numeric( $custom ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$custom = (string) wp_get_attachment_image_url( (int) $custom, 'full' );
		}

		if ( is_string( $custom ) && '' !== $custom ) {
			return $custom;
		}

		return plugin_dir_url( dirname( __DIR__ ) . '/basebelles.php' ) . 'blocks/season-header/off-season.jpg';
	}
}
