<?php
/**
 * Season-type archive header (spring training, regular season, and a post season the
 * Guardians weren't in). Always the Guardians, so there's no team identity: a title, a
 * one-line summary of how the season went, and a stat strip.
 *
 * Included by render.php, which has already set $api, $team_id, $stats, $season_type,
 * $season_year, $current_season, $is_past and $bb_has_value.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_type_labels = array(
	'springTraining' => __( 'Spring Training', 'basebelles' ),
	'regularSeason'  => __( 'Regular Season', 'basebelles' ),
	'postseason'     => __( 'Post Season', 'basebelles' ),
);
$bb_title       = $season_year . ' ' . ( $bb_type_labels[ $season_type ] ?? $bb_type_labels['regularSeason'] );
$bb_logo        = $api->get_team_logo_url( 'guardians' );

// Whether the phase being shown is finished. A past year always is; for the current one the
// Season Settings phase says so. Once it's over, live-only numbers (GB, last ten, streak) go.
$bb_phase    = class_exists( 'Basebelles_Season' ) ? Basebelles_Season::current_type() : 'regularSeason';
$bb_is_over  = $season_year < $current_season || $is_past;
if ( ! $bb_is_over ) {
	if ( 'springTraining' === $season_type ) {
		$bb_is_over = 'springTraining' !== $bb_phase;
	} elseif ( 'regularSeason' === $season_type ) {
		$bb_is_over = in_array( $bb_phase, array( 'postSeason', 'offSeason' ), true );
	} else {
		$bb_is_over = 'offSeason' === $bb_phase;
	}
}

/**
 * Add a signed class so a positive or negative number reads at a glance.
 *
 * @param string $value Value like "+10" or "-4".
 * @return string
 */
$bb_sign_class = static function ( $value ) {
	$first = substr( trim( (string) $value ), 0, 1 );

	return '+' === $first ? 'is-positive' : ( '-' === $first ? 'is-negative' : '' );
};

// "1st (AL Central)" -> "1st". The summary line names the division.
$bb_rank     = trim( (string) preg_replace( '/\s*\(.*\)\s*$/', '', (string) ( $stats['rank'] ?? '' ) ) );
$bb_division = (string) ( $stats['division'] ?? '' );
$bb_record   = sprintf( '%d – %d', (int) ( $stats['wins'] ?? 0 ), (int) ( $stats['losses'] ?? 0 ) );
$bb_summary  = '';
$bb_cells    = array(); // label, value, class.

if ( 'springTraining' === $season_type ) {
	$bb_summary = $bb_has_value( $bb_rank )
		/* translators: %s: Cactus League position, e.g. 4th */
		? sprintf( __( '%s in the Cactus League', 'basebelles' ), $bb_rank )
		: __( 'Cactus League', 'basebelles' );

	$bb_cells[] = array( __( 'Record', 'basebelles' ), $bb_record, '' );
	if ( $bb_has_value( $stats['pct'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Win %', 'basebelles' ), (string) $stats['pct'], '' );
	}
	if ( $bb_has_value( $stats['run_diff'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Run diff', 'basebelles' ), (string) $stats['run_diff'], $bb_sign_class( $stats['run_diff'] ) );
	}
} elseif ( 'regularSeason' === $season_type ) {
	$bb_seed = $bb_is_over ? $api->get_team_playoff_seed( $team_id, 'AL', $season_year ) : null;

	if ( $bb_is_over && '1st' === $bb_rank && '' !== $bb_division ) {
		/* translators: %s: division, e.g. AL Central */
		$bb_summary = sprintf( __( 'Won the %s', 'basebelles' ), $bb_division );
	} elseif ( $bb_has_value( $bb_rank ) && '' !== $bb_division ) {
		/* translators: 1: division position, e.g. 2nd, 2: division, e.g. AL Central */
		$bb_summary = sprintf( __( '%1$s in the %2$s', 'basebelles' ), $bb_rank, $bb_division );
	}

	if ( $bb_is_over ) {
		$bb_playoffs = null === $bb_seed
			? __( 'Missed playoffs', 'basebelles' )
			/* translators: %d: playoff seed */
			: sprintf( __( '#%d seed', 'basebelles' ), $bb_seed );
		$bb_summary  = trim( $bb_summary . ' · ' . $bb_playoffs, ' ·' );
	}

	$bb_cells[] = array( __( 'Record', 'basebelles' ), $bb_record, '' );
	if ( $bb_has_value( $bb_rank ) ) {
		$bb_cells[] = array( __( 'Division', 'basebelles' ), $bb_rank, '' );
	}
	if ( $bb_has_value( $stats['run_diff'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Run diff', 'basebelles' ), (string) $stats['run_diff'], $bb_sign_class( $stats['run_diff'] ) );
	}
	if ( $bb_is_over ) {
		$bb_cells[] = array(
			__( 'Playoffs', 'basebelles' ),
			null === $bb_seed ? __( 'Missed', 'basebelles' ) : sprintf( '#%d', $bb_seed ),
			null === $bb_seed ? 'is-negative' : 'is-positive',
		);
	} elseif ( $bb_has_value( $stats['games_back'] ?? '' ) && '0' !== (string) $stats['games_back'] ) {
		$bb_cells[] = array( __( 'GB', 'basebelles' ), (string) $stats['games_back'], '' );
	}
} else {
	// A post season the Guardians didn't reach: the post-season archive only lands here then.
	$bb_summary = __( 'Did not reach the post season', 'basebelles' );
	$bb_cells[] = array( __( 'Record', 'basebelles' ), $bb_record, '' );
}

// Live-only form, for spring training and the regular season while they're under way.
if ( ! $bb_is_over && 'postseason' !== $season_type ) {
	if ( $bb_has_value( $stats['last_ten'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Last 10', 'basebelles' ), str_replace( '-', ' – ', (string) $stats['last_ten'] ), '' );
	}
	if ( $bb_has_value( $stats['streak'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Streak', 'basebelles' ), (string) $stats['streak'], 'L' === substr( (string) $stats['streak'], 0, 1 ) ? 'is-negative' : '' );
	}
}

$bb_classes = array(
	'basebelles-season-stats-header',
	'basebelles-season-stats-header--season',
	'springTraining' === $season_type ? 'basebelles-season-stats-header-spring' : ( 'postseason' === $season_type ? 'basebelles-season-stats-header-postseason' : 'basebelles-season-stats-header-regular' ),
);
if ( $is_past ) {
	$bb_classes[] = 'is-past';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $bb_classes ) ); ?>">
	<div class="season-stats-inner season-team">
		<div class="season-stats-ghost" aria-hidden="true"></div>
		<div class="season-team-main">
			<?php if ( '' !== $bb_logo ) : ?>
				<img class="season-team-logo" src="<?php echo esc_url( $bb_logo ); ?>" alt="" width="84" height="84" loading="lazy" />
			<?php endif; ?>
			<div class="season-team-id">
				<h4 class="season-team-name"><?php echo esc_html( $bb_title ); ?></h4>
				<?php if ( '' !== $bb_summary ) : ?>
					<div class="season-team-sub"><?php echo esc_html( $bb_summary ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<dl class="season-team-strip">
			<?php foreach ( $bb_cells as $bb_cell ) : ?>
				<div class="season-team-stat <?php echo esc_attr( $bb_cell[2] ); ?>">
					<dt class="season-stats-label"><?php echo esc_html( $bb_cell[0] ); ?></dt>
					<dd class="season-team-value"><?php echo esc_html( $bb_cell[1] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>
</div>
