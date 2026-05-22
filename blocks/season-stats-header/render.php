<?php
/**
 * Season Snapshot — stats bar for season-type taxonomy archives.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'basebelles_season_snapshot_context_note' ) ) {
	/**
	 * Standings-derived contextual line by season segment.
	 *
	 * @param array  $stats    Archive stats from API.
	 * @param string $api_type API season type.
	 * @return string
	 */
	function basebelles_season_snapshot_context_note( $stats, $api_type ) {
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
}

if ( ! class_exists( 'Basebelles_API' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--placeholder"><p>API unavailable.</p></div>';
	}
	return;
}

if ( is_admin() ) {
	echo '<div class="basebelles-season-stats-header basebelles-season-stats-header--placeholder"><p>Season Snapshot appears on <strong>season-type</strong> and <strong>team</strong> archives with <code>?season_year=</code>.</p></div>';
	return;
}

if ( ! is_tax( 'season-type' ) && ! is_tax( 'team' ) ) {
	return;
}

// Get the current year from ACF Options
$season_settings = get_field( 'season_settings', 'option' ) ?? array();
$current_season  = (int) ( $season_settings['current_season'] ?? (int) gmdate( 'Y' ) );

$queried_season_term = get_queried_object();
if ( ! $queried_season_term || empty( $queried_season_term->slug ) ) {
	return;
}

$slug_to_type = array(
	'spring-training' => 'springTraining',
	'regular-season'  => 'regularSeason',
	'post-season'     => 'postseason',
);

$api_type = $slug_to_type[ $queried_season_term->slug ] ?? 'regularSeason';

$season_year = (int) get_query_var( 'season_year' );
if ( $season_year < 1900 ) {
	$season_year = (int) gmdate( 'Y' );
}

$api     = Basebelles_API::get_instance();
$stats   = $api->get_season_archive_stats( $season_year, $api_type );
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

$record_label = sprintf( '%d – %d', (int) ( $stats['wins'] ?? 0 ), (int) ( $stats['losses'] ?? 0 ) );
$context_note = basebelles_season_snapshot_context_note( $stats, $api_type );

$class_season_type = 'basebelles-season-stats-header-regular';
if ( 'springTraining' === $api_type ) {
	$class_season_type = 'basebelles-season-stats-header-spring';
} elseif ( 'postseason' === $api_type ) {
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
	'<div class="%1$s"><div class="season-stats-inner"><div class="season-stats-ghost" aria-hidden="true"></div><div class="season-stats-grid">',
	esc_attr( implode( ' ', $classes ) )
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
