<?php
/**
 * Post-season archive header: where the Guardians are in the bracket. Included by render.php,
 * which has already fetched $postseason (see Basebelles_Postseason::build_status() for its
 * shape) and set $api, $season_year and $team_id.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_state   = (string) ( $postseason['state'] ?? '' );
$bb_current = is_array( $postseason['current'] ?? null ) ? $postseason['current'] : null;
$bb_is_past = $season_year < (int) wp_date( 'Y' );

// Round names for the "path" chips, in order.
$bb_rounds = Basebelles_Postseason::ROUND_SHORT;

// Which rounds are behind the team: the road (decided rounds, plus a bye) and, when the team
// won the last series, that one too.
$bb_done = array();
foreach ( (array) ( $postseason['series'] ?? array() ) as $bb_type => $bb_series ) {
	if ( ! empty( $bb_series['complete'] ) && ! empty( $bb_series['won'] ) ) {
		$bb_done[ $bb_type ] = true;
	}
}
if ( ! empty( $postseason['had_bye'] ) ) {
	$bb_done['F'] = true;
}

$bb_now = '';
if ( 'in_series' === $bb_state && $bb_current ) {
	$bb_now = (string) $bb_current['game_type'];
} elseif ( in_array( $bb_state, array( 'awaiting', 'between_rounds' ), true ) ) {
	$bb_now = (string) ( $postseason['next_round'] ?? '' );
}

// Heading and the small line beside it.
if ( in_array( $bb_state, array( 'awaiting', 'between_rounds' ), true ) ) {
	$bb_heading = Basebelles_Postseason::ROUND_FULL[ $bb_now ] ?? '';
	$bb_meta    = ( 'between_rounds' === $bb_state && $bb_current ) ? $bb_current['round_short'] . ': ' . $bb_current['result_label'] : __( 'Opponent to be determined', 'basebelles' );
} elseif ( $bb_current ) {
	$bb_heading = (string) $bb_current['round'];
	if ( 'in_series' === $bb_state ) {
		/* translators: %d: number of games in the series (3, 5 or 7) */
		$bb_meta = sprintf( __( 'Best of %d', 'basebelles' ), (int) $bb_current['games_in_series'] ) . ' · ' . $bb_current['status_label'];
	} elseif ( 'champion' === $bb_state ) {
		$bb_meta = __( 'World Series champions', 'basebelles' );
	} else {
		$bb_meta = __( 'Season over', 'basebelles' ) . ' · ' . $bb_current['result_label'];
	}
} else {
	$bb_heading = '';
	$bb_meta    = '';
}

// The two sides. Before a series exists there's only our own team, from the standings.
$bb_us   = $bb_current ? $bb_current['guardians'] : array(
	'abbreviation' => 'CLE',
	'name'         => 'Guardians',
	'seed'         => $postseason['seed'] ?? null,
	'logo_url'     => (string) ( $postseason['guardians_logo_url'] ?? '' ),
);
$bb_them = $bb_current && 'between_rounds' !== $bb_state ? $bb_current['opponent'] : null;

/**
 * One team in the matchup: logo, name, seed.
 *
 * @param array|null $team  Team array, or null for a not-yet-known opponent.
 * @param string     $side  'left' or 'right'.
 * @return void
 */
$bb_render_team = static function ( $team, $side ) {
	$known = is_array( $team ) && '' !== (string) ( $team['abbreviation'] ?? '' );
	?>
	<div class="season-series-team is-<?php echo esc_attr( $side ); ?><?php echo $known ? '' : ' is-tbd'; ?>">
		<?php if ( $known && ! empty( $team['logo_url'] ) ) : ?>
			<img class="season-series-logo" src="<?php echo esc_url( $team['logo_url'] ); ?>" alt="" width="48" height="48" loading="lazy" />
		<?php else : ?>
			<span class="season-series-logo is-placeholder" aria-hidden="true"><?php echo $known ? esc_html( $team['abbreviation'] ) : '?'; ?></span>
		<?php endif; ?>
		<div>
			<div class="season-series-name"><?php echo $known ? esc_html( $team['name'] ?: $team['abbreviation'] ) : esc_html__( 'TBD', 'basebelles' ); ?></div>
			<?php if ( $known && null !== ( $team['seed'] ?? null ) ) : ?>
				<div class="season-series-seed">
					<?php
					/* translators: %d: playoff seed */
					echo esc_html( sprintf( __( '#%d seed', 'basebelles' ), (int) $team['seed'] ) );
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
};

// Next game: the first cell flagged as next, or the live one.
$bb_next = null;
if ( $bb_current && 'in_series' === $bb_state ) {
	foreach ( (array) $bb_current['games'] as $bb_game ) {
		if ( ! empty( $bb_game['next'] ) ) {
			$bb_next = $bb_game;
			break;
		}
	}
}

$bb_classes = array(
	'basebelles-season-stats-header',
	'basebelles-season-stats-header-postseason',
	'basebelles-season-stats-header--series',
	'is-' . str_replace( '_', '-', $bb_state ),
);
if ( $bb_is_past ) {
	$bb_classes[] = 'is-past';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $bb_classes ) ); ?>">
	<div class="season-stats-inner season-series">
		<div class="season-stats-ghost" aria-hidden="true"></div>

		<?php if ( '' !== $bb_heading ) : ?>
			<div class="season-series-top">
				<h4 class="season-series-round"><?php echo esc_html( $bb_heading ); ?></h4>
				<?php if ( '' !== $bb_meta ) : ?>
					<div class="season-series-meta"><?php echo esc_html( $bb_meta ); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="season-series-matchup">
			<?php $bb_render_team( $bb_us, 'left' ); ?>

			<?php if ( $bb_current && 'between_rounds' !== $bb_state ) : ?>
				<div class="season-series-score">
					<span class="season-series-score-num" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: our series wins, 2: their series wins */ __( 'Series: %1$d to %2$d', 'basebelles' ), (int) $bb_current['guardians_wins'], (int) $bb_current['opponent_wins'] ) ); ?>">
						<?php echo (int) $bb_current['guardians_wins']; ?> – <?php echo (int) $bb_current['opponent_wins']; ?>
					</span>
					<span class="season-stats-label"><?php esc_html_e( 'Series', 'basebelles' ); ?></span>
				</div>
			<?php else : ?>
				<div class="season-series-score is-vs"><span class="season-stats-label"><?php esc_html_e( 'vs', 'basebelles' ); ?></span></div>
			<?php endif; ?>

			<?php $bb_render_team( $bb_them, 'right' ); ?>
		</div>

		<div class="season-series-foot">
			<?php if ( $bb_next ) : ?>
				<div class="season-series-next">
					<strong><?php esc_html_e( 'Next:', 'basebelles' ); ?></strong>
					<?php echo esc_html( $bb_next['label'] . ' · ' . $bb_next['value'] ); ?>
				</div>
			<?php endif; ?>
			<div class="season-series-record">
				<?php esc_html_e( 'Post-season:', 'basebelles' ); ?>
				<strong><?php echo esc_html( str_replace( '-', ' – ', (string) ( $postseason['postseason_record'] ?? '0-0' ) ) ); ?></strong>
			</div>
		</div>

		<ol class="season-series-path">
			<?php foreach ( $bb_rounds as $bb_type => $bb_label ) : ?>
				<?php
				$bb_chip = isset( $bb_done[ $bb_type ] ) ? 'is-won' : ( $bb_type === $bb_now ? 'is-now' : '' );
				?>
				<li class="season-series-chip <?php echo esc_attr( $bb_chip ); ?>">
					<?php echo esc_html( $bb_label ); ?>
					<?php if ( 'is-won' === $bb_chip ) : ?>
						<span class="screen-reader-text"><?php esc_html_e( '(won)', 'basebelles' ); ?></span><span aria-hidden="true">✓</span>
					<?php elseif ( 'is-now' === $bb_chip ) : ?>
						<span class="screen-reader-text"><?php esc_html_e( '(current)', 'basebelles' ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</div>
