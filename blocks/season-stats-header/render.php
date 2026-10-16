<?php
/**
 * Season header for season-type and team taxonomy archives.
 *
 * Three layouts, picked from what the archive is about:
 * - Post-season archive: the Guardians' series status (series.php).
 * - Team archive: team identity plus a stat strip that adapts to the season type (team.php).
 * - Any other season-type archive: the Guardians' season title, summary and stat strip (season.php).
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If there's no API, we can't do anything, so show a placeholder in the editor and bail.
if ( ! class_exists( 'Basebelles_API' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--placeholder"><p>API unavailable.</p></div>';
	}
	return;
}

// This block only makes sense on season-type or team archives, so if we're in the editor, show a placeholder and bail.
if ( is_admin() ) {
	echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--placeholder"><p>Season Snapshot appears on <strong>season-type</strong> and <strong>team</strong> archives with <code>?season_year=</code>.</p></div>';
	return;
}

// This block only makes sense on season-type or team archives, so if we're on the frontend and not on one of those archive types, bail.
if ( ! is_tax( 'season-type' ) && ! is_tax( 'team' ) ) {
	return;
}

// Defaults
$team_id   = Basebelles_API::GUARDIANS_TEAM_ID;
$team_data = array();

// Get the current year from ACF Options and default to current calendar year if not set.
$season_settings = get_field( 'season_settings', 'option' ) ?? array();
$current_season  = (int) ( $season_settings['current_season'] ?? (int) gmdate( 'Y' ) );
$season_year     = $current_season;

$queried_season_term = get_queried_object();
if ( ! $queried_season_term || empty( $queried_season_term->slug ) ) {
	return;
}

// Get the expected season type from the archive's term slug.
$slug_to_type = array(
	'spring-training' => 'springTraining',
	'regular-season'  => 'regularSeason',
	'post-season'     => 'postseason',
);
// Map the Season Settings "Current Type" choice onto an API season type. The off season shows
// the regular season.
$settings_type_map = array(
	'springTraining' => 'springTraining',
	'regularSeason'  => 'regularSeason',
	'postseason'     => 'postseason',
);
$season_type       = $settings_type_map[ $season_settings['season_type'] ?? '' ] ?? 'regularSeason';

// Read the season_year query var for both archive types.
$season_year_qv = (int) get_query_var( 'season_year' );
if ( $season_year_qv >= 1900 ) {
	$season_year = $season_year_qv;
}

// On a season-type archive, also override the season type from the term slug.
if ( is_tax( 'season-type' ) ) {
	$season_type = $slug_to_type[ $queried_season_term->slug ] ?? 'regularSeason';
}

// If we somehow ended up pre 1900, default to the current year to avoid bad API calls.
if ( $season_year < 1900 ) {
	$season_year = (int) gmdate( 'Y' );
}

$api = Basebelles_API::get_instance();

// If we're on a team archive, look up the MLB team ID via the API class.
if ( is_tax( 'team' ) ) {
	$team_data = $api->get_team_by_taxonomy_slug( $queried_season_term->slug );
	$team_id   = (int) ( $team_data['mlb_team_id'] ?? 0 );

	if ( ! $team_id ) {
		return;
	}
}

// Post-season archive: the Guardians' series, not a standings bar. A season with no post-season
// for the Guardians (or an API hiccup) falls through to the standings bar below.
if ( is_tax( 'season-type' ) && 'postseason' === $season_type && method_exists( $api, 'get_postseason_status' ) ) {
	$postseason = $api->get_postseason_status( $season_year );

	if ( ! is_wp_error( $postseason ) && is_array( $postseason ) && 'not_qualified' !== ( $postseason['state'] ?? 'not_qualified' ) ) {
		require __DIR__ . '/series.php';
		return;
	}
}

// Call the API
$stats = $api->get_season_archive_stats( $season_year, $season_type, $team_id );

// On a team page during the postseason, a team with no postseason games yet (or that didn't
// make it) falls back to its regular season record rather than "Coming soon".
$fell_back = false;
if ( is_tax( 'team' ) && 'postseason' === $season_type && is_wp_error( $stats ) && 'basebelles_api_postseason_empty' === $stats->get_error_code() ) {
	$season_type = 'regularSeason';
	$fell_back   = true;
	$stats       = $api->get_season_archive_stats( $season_year, $season_type, $team_id );
}

$is_past = $season_year < (int) wp_date( 'Y' );

if ( is_wp_error( $stats ) ) {
	$code = $stats->get_error_code();
	if ( 'basebelles_api_future' === $code || 'basebelles_api_empty' === $code || 'basebelles_api_postseason_empty' === $code || 'basebelles_api_team_missing' === $code ) {
		printf(
			'<div class="basebelles-season-stats-header basebelles-season-stats-header--empty%s" role="status"><p class="season-stats-coming-soon">%s</p></div>',
			$is_past ? ' is-past' : '',
			esc_html__( 'Coming soon — stats are not available for this season yet.', 'basebelles' )
		);
		return;
	}
	echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--error" role="alert"><p>';
	echo esc_html( $stats->get_error_message() );
	echo '</p></div>';
	return;
}

/**
 * Whether a standings value is worth showing. The API uses '-' or '—' for "no data".
 *
 * @param mixed $value Raw value.
 * @return bool
 */
$bb_has_value = static function ( $value ) {
	$value = trim( (string) $value );

	return '' !== $value && ! in_array( $value, array( '-', '—', '–' ), true );
};

// Team archive: identity plus an adaptive stat strip.
if ( is_tax( 'team' ) ) {
	require __DIR__ . '/team.php';
	return;
}

// Season-type archive (spring training, regular season, or a post season the Guardians missed).
require __DIR__ . '/season.php';
