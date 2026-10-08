<?php
/**
 * ACF Block Render Template for Guardians Standings.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Basebelles_API' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-standings placeholder"><p>Standings API integration is unavailable.</p></div>';
	}

	return;
}

// Get the season type and year from ACF Options (Season Settings group) or query variables.
// Default to regular season and current year.
$season_settings = get_field( 'season_settings', 'option' );
$season_settings = is_array( $season_settings ) ? $season_settings : array();
$current_type    = (string) ( $season_settings['season_type'] ?? '' );

$season_year = (int) get_query_var( 'season_year' );
if ( $season_year < 1900 ) {
	$season_year = (int) ( $season_settings['current_season'] ?? 0 );
}
if ( $season_year < 1900 ) {
	$season_year = (int) gmdate( 'Y' );
}

$api = Basebelles_API::get_instance();

/**
 * Render one row of the ticker.
 *
 * A definition list rather than the div soup the prototype uses: each item is a label describing
 * a value, which is what dt/dd mean, so the pairing survives for anyone on a screen reader. The
 * div wrapper around each dt/dd pair is valid HTML5 inside a dl and is what the flex layout
 * hangs off.
 *
 * An item may carry 'html' instead of 'value' when the value is markup (the postseason matchup
 * with its logos). That markup is built from escaped parts by the caller.
 *
 * @param array  $items Item arrays with label, value (or html) and optional class.
 * @param string $row_class Row modifier class.
 * @return void
 */
$basebelles_render_ticker_row = static function ( array $items, $row_class ) {
	?>
	<dl class="bb-ticker-row <?php echo esc_attr( $row_class ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<div class="bb-ticker-item <?php echo esc_attr( $item['class'] ?? '' ); ?>">
				<dt class="bb-ticker-label"><?php echo esc_html( $item['label'] ); ?></dt>
				<?php if ( isset( $item['html'] ) ) : ?>
					<dd class="bb-ticker-value"><?php echo wp_kses_post( $item['html'] ); ?></dd>
				<?php else : ?>
					<dd class="bb-ticker-value"><?php echo esc_html( $item['value'] ); ?></dd>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</dl>
	<?php
};

// Postseason: once Season Type is Wild Card or Post Season, a Guardians team that made it gets
// the series ticker instead. One that didn't qualify (or an API failure) falls through to the
// final regular-season standings below.
if ( in_array( $current_type, array( 'wildCard', 'postseason' ), true ) && method_exists( $api, 'get_postseason_status' ) ) {
	$postseason = $api->get_postseason_status( (int) $season_year );

	if ( ! is_wp_error( $postseason ) && is_array( $postseason ) && 'not_qualified' !== ( $postseason['state'] ?? 'not_qualified' ) ) {
		require __DIR__ . '/postseason.php';
		return;
	}
}

// MLB has no postseason standings table, so outside Spring Training the ticker shows the
// regular season standings (the final ones once the regular season is over).
$season_type = ( 'springTraining' === $current_type ) ? 'springTraining' : 'regularSeason';

// Get the standings from the API.
$standings = $api->fetch_standings( (string) $season_type, (int) $season_year );

if ( is_wp_error( $standings ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-standings placeholder">';
		echo '<h3>Guardians Standings</h3>';
		echo '<p>Live standings are unavailable right now. Don\'t panic, we\'re working on it.</p>';
		echo '</div>';
	}

	return;
}

$streak = (string) ( $standings['streak'] ?? '-' );
$diff   = (string) ( $standings['run_differential'] ?? '0' );

// Already uppercased by the API, and pre-signed for the differential ("+11" / "-11"), so the
// prefix is enough to spot a losing streak or a negative run differential. The label keeps its
// L and its minus sign either way -- the colour only reinforces what the value already says,
// which is what keeps this out of "colour as the only signal" territory.
$streak_is_loss   = 'L' === substr( $streak, 0, 1 );
$diff_is_negative = '-' === substr( $diff, 0, 1 );

// The design combines the old separate W and L columns into one item.
$primary = array(
	array(
		'label' => 'Standing',
		'value' => (string) ( $standings['summary'] ?? '' ),
		'class' => 'is-standing',
	),
	array(
		'label' => 'W–L',
		'value' => (int) ( $standings['wins'] ?? 0 ) . '-' . (int) ( $standings['losses'] ?? 0 ),
	),
	array(
		'label' => 'PCT',
		'value' => (string) ( $standings['winning_percentage'] ?? '.000' ),
	),
	array(
		'label' => 'GB',
		'value' => (string) ( $standings['games_back'] ?? '-' ),
	),
	array(
		'label' => 'WCGB',
		'value' => (string) ( $standings['wild_card_games_back'] ?? '-' ),
	),
);

$secondary = array(
	array(
		'label' => 'L10',
		'value' => (string) ( $standings['last_ten'] ?? '-' ),
	),
	array(
		'label' => 'STK',
		'value' => $streak,
		'class' => $streak_is_loss ? 'is-negative' : '',
	),
	array(
		'label' => 'RS',
		'value' => (string) ( $standings['runs_scored'] ?? 0 ),
	),
	array(
		'label' => 'RA',
		'value' => (string) ( $standings['runs_allowed'] ?? 0 ),
	),
	array(
		'label' => 'DIFF',
		'value' => $diff,
		'class' => $diff_is_negative ? 'is-negative' : '',
	),
	array(
		'label' => 'Home',
		'value' => (string) ( $standings['home'] ?? '-' ),
	),
	array(
		'label' => 'Away',
		'value' => (string) ( $standings['away'] ?? '-' ),
	),
	array(
		'label' => '>.500',
		'value' => (string) ( $standings['over_500'] ?? '-' ),
	),
);
?>

<div class="basebelles-standings">
	<div class="bb-ticker">
		<?php $basebelles_render_ticker_row( $primary, 'is-primary' ); ?>
		<?php $basebelles_render_ticker_row( $secondary, 'is-secondary' ); ?>
	</div>
</div>
