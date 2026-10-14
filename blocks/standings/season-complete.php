<?php
/**
 * "Season complete" variant of the standings ticker, for once the Guardians are done: eliminated,
 * World Series champions, or never in the postseason at all. Included by render.php, which has
 * already fetched $postseason (see Basebelles_Postseason::build_status()) and defined $api,
 * $season_year and $basebelles_render_ticker_row.
 *
 * Sets $bb_sc_rendered to true once it has output anything. When there's no postseason and no
 * regular season to report on either (Current Season already bumped to a year with no games), it
 * renders nothing so render.php can fall back to the plain standings.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_sc_state    = (string) ( $postseason['state'] ?? 'not_qualified' );
$bb_sc_champion = 'champion' === $bb_sc_state;
$bb_sc_rounds   = 'not_qualified' === $bb_sc_state ? array() : (array) ( $postseason['rounds'] ?? array() );

// Regular season finish, from the final standings: "1st in the AL Central".
$bb_sc_standings = $api->fetch_standings( 'regularSeason', (int) $season_year );
$bb_sc_summary   = '';

if ( ! is_wp_error( $bb_sc_standings ) && is_array( $bb_sc_standings ) ) {
	$bb_sc_played = (int) ( $bb_sc_standings['wins'] ?? 0 ) + (int) ( $bb_sc_standings['losses'] ?? 0 );

	if ( $bb_sc_played > 0 ) {
		$bb_sc_summary = (string) ( $bb_sc_standings['summary'] ?? '' );
	}
}

if ( '' === $bb_sc_summary && empty( $bb_sc_rounds ) ) {
	return;
}

$bb_sc_rendered = true;

$bb_sc_heading = $bb_sc_champion
	? sprintf( 'Your World Champions for %d — The Cleveland Guardians', (int) $season_year )
	: 'Season Complete';

$primary = array();

if ( '' !== $bb_sc_summary ) {
	$primary[] = array(
		'label' => 'Regular Season',
		'value' => $bb_sc_summary,
		'class' => 'is-summary',
	);
}

// One cell per round reached, oldest first, ending on the one that finished the season.
$secondary = array();

foreach ( $bb_sc_rounds as $bb_sc_round ) {
	$bb_sc_result = (string) ( $bb_sc_round['result'] ?? '' );
	$bb_sc_class  = '';

	if ( 0 === strpos( $bb_sc_result, 'Won ' ) ) {
		$bb_sc_class = 'is-positive';
	} elseif ( 0 === strpos( $bb_sc_result, 'Lost ' ) ) {
		$bb_sc_class = 'is-negative';
	}

	$secondary[] = array(
		'label' => (string) ( $bb_sc_round['round'] ?? '' ),
		'value' => $bb_sc_result,
		'class' => $bb_sc_class,
	);
}

// The year of the last title. Not shown in a championship year: the banner already says it.
$bb_sc_last_ws = $bb_sc_champion ? '' : Basebelles_Season::last_world_series_year();
?>

<div class="basebelles-standings is-postseason is-season-complete<?php echo $bb_sc_champion ? ' is-champion' : ''; ?>">
	<hr class="bb-ticker-rule" />
	<h4 class="bb-ticker-heading"><?php echo esc_html( $bb_sc_heading ); ?></h4>
	<div class="bb-ticker">
		<?php $basebelles_render_ticker_row( $primary, 'is-primary' ); ?>
		<?php if ( ! empty( $secondary ) ) : ?>
			<?php $basebelles_render_ticker_row( $secondary, 'is-secondary is-rounds' ); ?>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $bb_sc_last_ws ) : ?>
		<div class="bb-ticker-foot">
			<p class="bb-ticker-road">
				<span class="bb-ticker-road-label">The last World Series Win</span>
				<?php echo esc_html( $bb_sc_last_ws ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>
