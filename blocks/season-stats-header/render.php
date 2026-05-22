<?php
/**
 * Season Snapshot — stats bar for season-type taxonomy archives.
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

/**
 * Standings-derived contextual line by season segment.
 *
 * @param array  $stats    Archive stats from API.
 * @return string
 */
function basebelles_season_snapshot_context_note( $stats ) {
	$l10 = (string) ( $stats['last_ten'] ?? null );
	$stk = (string) ( $stats['streak'] ?? null );
	$gb  = (string) ( $stats['games_back'] ?? null );

	// If the value is NOT - then don't show it
	if ( ! is_null( $gb ) && '-' !== $gb ) {
		// translators: %1$s is the games back
		$gb = sprintf( __( 'GB: %1$s', 'basebelles' ), $gb );
	}
	if ( ! is_null( $l10 ) && '-' !== $l10 ) {
		// translators: %1$s is the last ten record
		$l10 = sprintf( __( 'L10: %1$s', 'basebelles' ), $l10 );
	}
	if ( ! is_null( $stk ) && '-' !== $stk ) {
		// translators: %1$s is the streak code
		$stk = sprintf( __( 'STK: %1$s', 'basebelles' ), $stk );
	}

	$context_note = array( $gb, $l10, $stk );

	foreach ( $context_note as $key => $note ) {
		if ( '-' === $note ) {
			unset( $context_note[ $key ] );
		}
	}

	return implode( ' <br /> ', $context_note );
}

// Defaults
$team_id = Basebelles_API::GUARDIANS_TEAM_ID;

// Get the current year from ACF Options and default to current calendar year if not set.
$season_settings = get_field( 'season_settings', 'option' ) ?? array();
$current_season  = (int) ( $season_settings['current_season'] ?? (int) gmdate( 'Y' ) );
$season_year     = $current_season;

$queried_season_term = get_queried_object();
if ( ! $queried_season_term || empty( $queried_season_term->slug ) ) {
	return;
}

// Get the expected season type from ACF Options.
$slug_to_type = array(
	'spring-training' => 'springTraining',
	'regular-season'  => 'regularSeason',
	'post-season'     => 'postseason',
);
$season_type  = $season_settings['current_type'] ?? 'regularSeason';

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

// If we're on a team archive, look up the MLB team ID from list.json.
if ( is_tax( 'team' ) ) {
	$team_id     = 0; // Reset so the guard below correctly catches a failed lookup.
	$plugin_path = dirname( __DIR__, 2 );
	$teams_json  = file_exists( $plugin_path . '/team-info/list.json' ) ? file_get_contents( $plugin_path . '/team-info/list.json' ) : '{}';

	// If there's no team data, bail early.
	if ( ! $teams_json || empty( $teams_json ) ) {
		if ( is_admin() ) {
			echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--error" role="alert"><p>Team data unavailable.</p></div>';
			return;
		}
	}

	// Decode the JSON and look up the team ID from the slug.
	$teams_data = json_decode( $teams_json, true );
	$team_slug  = $queried_season_term->slug;

	// Taxonomy slugs are chicago-cubs but the slug in the json is just cubs, so we need to do some reverse mapping to find the right team.
	foreach ( $teams_data as $slug => $team ) {
		if ( $team['taxonomy_term'] === $team_slug ) {
			$team_id = $team['mlb_team_id'];
			break;
		}
	}

	// If we couldn't find a team ID and we're in the admin, show an error. If we're on the frontend, just bail silently.
	if ( ! $team_id ) {
		if ( is_admin() ) {
			echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--error" role="alert"><p>Team not found.</p></div>';
		}
		return;
	}
}

// Call the API
$api     = Basebelles_API::get_instance();
$stats   = $api->get_season_archive_stats( $season_year, $season_type, $team_id );
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

$record_label     = sprintf( '%d – %d', (int) ( $stats['wins'] ?? 0 ), (int) ( $stats['losses'] ?? 0 ) );
$context_note     = basebelles_season_snapshot_context_note( $stats );
$snapshot_heading = sprintf( '%d Standings', $season_year );

// For teams we show the season type of "now" since the context is the team, not the season type.
if ( is_tax( 'team' ) ) {
	$season_type_labels = array(
		'springTraining' => 'Spring Training',
		'regularSeason'  => 'Regular Season',
		'postseason'     => 'Post-Season',
	);
	$season_type_label  = $season_type_labels[ $season_type ] ?? 'Regular Season';
	$snapshot_heading   = sprintf( 'Current %d %s Standing', $season_year, $season_type_label );
}

$class_season_type = 'basebelles-season-stats-header-regular';
if ( 'springTraining' === $season_type ) {
	$class_season_type = 'basebelles-season-stats-header-spring';
} elseif ( 'postseason' === $season_type ) {
	$class_season_type = 'basebelles-season-stats-header-postseason';
}

$class_current_season = ( gmdate( 'Y' ) === $current_season ) ? 'is-current' : '';

$classes = array(
	'basebelles-season-stats-header',
	$class_season_type,
	$class_current_season,
);
if ( $is_past ) {
	$classes[] = 'is-past';
}

printf(
	'<div class="%1$s"><div class="season-stats-inner"><div class="season-stats-ghost" aria-hidden="true"></div><div class="season-stats-heading"><h4>%2$s</h4></div><div class="season-stats-grid">',
	esc_attr( implode( ' ', $classes ) ),
	esc_html( $snapshot_heading )
);
?>
	<div class="season-stats-cell season-stats-record">
		<span class="season-stats-label"><?php esc_html_e( 'Record', 'basebelles' ); ?></span>
		<span class="season-stats-value"><?php echo esc_html( $record_label ); ?></span>
	</div>
	<div class="season-stats-cell season-stats-rank">
		<span class="season-stats-label"><?php esc_html_e( 'Rank', 'basebelles' ); ?></span>
		<span class="season-stats-value"><?php echo esc_html( (string) ( $stats['rank'] ?? '—' ) ); ?></span>
	</div>
	<div class="season-stats-cell season-stats-diff">
		<span class="season-stats-label"><?php esc_html_e( 'Run diff', 'basebelles' ); ?></span>
		<span class="season-stats-value"><?php echo esc_html( (string) ( $stats['run_diff'] ?? '—' ) ); ?></span>
	</div>
	<div class="season-stats-cell season-stats-context">
		<span class="season-stats-label"><?php esc_html_e( 'Snapshot', 'basebelles' ); ?></span>
		<span class="season-stats-value"><?php echo wp_kses_post( $context_note ); ?></span>
	</div>
<?php
echo '</div></div></div>';
