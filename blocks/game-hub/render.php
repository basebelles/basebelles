<?php
/**
 * ACF Block Render Template for the Game Hub.
 *
 * One front-page section that changes with the season:
 *
 * - Regular season:  Today's Game  |  division card (last game, division table, wild card, next games)
 * - Postseason:      "ALCS · Game 2" heading, Today's Game  |  series card
 * - Spring Training: Today's Game  |  Guardians news
 * - Off season:      Spring Training countdown  |  Guardians news
 *
 * Once the Guardians are out of the postseason (or never made it) the off-season layout takes
 * over, even while Season Type still says Post Season.
 *
 * The cards live in cards/*.php, included from here and sharing its variables.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_field' ) ) {
	return;
}

if ( ! class_exists( 'Basebelles_API' ) || ! class_exists( 'Basebelles_Season' ) || ! class_exists( 'Basebelles_Game_Hub' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-game-hub placeholder"><p>Game Hub data is unavailable.</p></div>';
	}

	return;
}

$bb_hub_api      = Basebelles_API::get_instance();
$bb_hub_settings = get_field( 'season_settings', 'option' );
$bb_hub_settings = is_array( $bb_hub_settings ) ? $bb_hub_settings : array();
$bb_hub_season   = (int) ( $bb_hub_settings['current_season'] ?? 0 );
$bb_hub_season   = $bb_hub_season >= 1900 ? $bb_hub_season : (int) wp_date( 'Y' );
$bb_hub_type     = Basebelles_Season::current_type();

// Which layout, and for the postseason, the status that drives it.
$bb_hub_layout     = 'regular';
$bb_hub_postseason = null;

if ( 'offSeason' === $bb_hub_type ) {
	$bb_hub_layout = 'offseason';
} elseif ( 'springTraining' === $bb_hub_type ) {
	$bb_hub_layout = 'spring';
} elseif ( 'postSeason' === $bb_hub_type ) {
	$bb_hub_postseason = $bb_hub_api->get_postseason_status( $bb_hub_season );

	if ( is_wp_error( $bb_hub_postseason ) || ! is_array( $bb_hub_postseason ) ) {
		// No series data to pair it with; Today's Game on its own still says what's on.
		$bb_hub_postseason = null;
		$bb_hub_layout     = 'solo';
	} elseif ( in_array( (string) ( $bb_hub_postseason['state'] ?? '' ), array( 'in_series', 'between_rounds', 'awaiting' ), true ) ) {
		$bb_hub_layout = 'postseason';
	} else {
		$bb_hub_layout = 'offseason';
	}
}

/**
 * Today's Game, rendered by its own template. A closure so the template's variables stay out of
 * this scope (and ours out of its).
 *
 * @return string
 */
$bb_hub_render_today = static function () {
	ob_start();
	require dirname( __DIR__ ) . '/today-game/render.php';

	return trim( (string) ob_get_clean() );
};

$bb_hub_heading = '';
$bb_hub_kicker  = '';
$bb_hub_main    = '';
$bb_hub_side    = '';

switch ( $bb_hub_layout ) {
	case 'postseason':
		$bb_hub_state   = (string) $bb_hub_postseason['state'];
		$bb_hub_current = is_array( $bb_hub_postseason['current'] ?? null ) ? $bb_hub_postseason['current'] : null;

		if ( 'in_series' === $bb_hub_state && $bb_hub_current ) {
			$bb_hub_heading = (string) $bb_hub_current['round_short'];

			// "· Game 2" when the game Today's Game is showing belongs to this series.
			$bb_hub_schedule = $bb_hub_api->get_guardians_today_game( '' );
			$bb_hub_first    = ( ! is_wp_error( $bb_hub_schedule ) && ! empty( $bb_hub_schedule['games'][0] ) ) ? $bb_hub_schedule['games'][0] : array();

			if ( ( $bb_hub_first['game_type'] ?? '' ) === $bb_hub_current['game_type'] && ! empty( $bb_hub_first['series']['game_number'] ) ) {
				$bb_hub_heading .= ' · Game ' . (int) $bb_hub_first['series']['game_number'];
			}
		} else {
			$bb_hub_heading = Basebelles_Postseason::ROUND_SHORT[ (string) ( $bb_hub_postseason['next_round'] ?? 'D' ) ] ?? '';
		}

		$bb_hub_kicker = $bb_hub_season . ' Postseason';
		$bb_hub_main   = $bb_hub_render_today();

		ob_start();
		require __DIR__ . '/cards/series.php';
		$bb_hub_side = trim( (string) ob_get_clean() );
		break;

	case 'regular':
		$bb_hub_main = $bb_hub_render_today();

		ob_start();
		require __DIR__ . '/cards/division.php';
		$bb_hub_side = trim( (string) ob_get_clean() );
		break;

	case 'spring':
		$bb_hub_main = $bb_hub_render_today();

		ob_start();
		require __DIR__ . '/cards/news.php';
		$bb_hub_side = trim( (string) ob_get_clean() );
		break;

	case 'offseason':
		ob_start();
		require __DIR__ . '/cards/countdown.php';
		$bb_hub_main = trim( (string) ob_get_clean() );

		ob_start();
		require __DIR__ . '/cards/news.php';
		$bb_hub_side = trim( (string) ob_get_clean() );
		break;

	case 'solo':
	default:
		$bb_hub_main = $bb_hub_render_today();
		break;
}

if ( '' === $bb_hub_main && '' === $bb_hub_side ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-game-hub placeholder"><p>Nothing to show in the Game Hub right now.</p></div>';
	}

	return;
}

$bb_hub_single = ( '' === $bb_hub_main ) !== ( '' === $bb_hub_side );

// ACF doesn't put the editor's alignment, anchor or extra classes on the markup; the template
// has to. Without the align class a Full width block stays at the content width.
$bb_hub_block   = isset( $block ) && is_array( $block ) ? $block : array();
$bb_hub_classes = array( 'basebelles-game-hub', 'is-' . $bb_hub_layout );

if ( ! empty( $bb_hub_block['align'] ) ) {
	$bb_hub_classes[] = 'align' . sanitize_html_class( (string) $bb_hub_block['align'] );
}

foreach ( preg_split( '/\s+/', (string) ( $bb_hub_block['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ) as $bb_hub_class ) {
	$bb_hub_classes[] = sanitize_html_class( $bb_hub_class );
}

$bb_hub_anchor = (string) ( $bb_hub_block['anchor'] ?? '' );
?>

<div class="<?php echo esc_attr( implode( ' ', array_filter( $bb_hub_classes ) ) ); ?>"<?php echo '' !== $bb_hub_anchor ? ' id="' . esc_attr( $bb_hub_anchor ) . '"' : ''; ?>>
	<?php if ( '' !== $bb_hub_heading ) : ?>
		<header class="bb-hub-head">
			<h3 class="bb-hub-title"><?php echo esc_html( $bb_hub_heading ); ?></h3>
			<?php if ( '' !== $bb_hub_kicker ) : ?>
				<p class="bb-hub-kicker"><?php echo esc_html( $bb_hub_kicker ); ?></p>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<div class="bb-hub-grid<?php echo $bb_hub_single ? ' is-single' : ''; ?>">
		<?php if ( '' !== $bb_hub_main ) : ?>
			<div class="bb-hub-main"><?php echo $bb_hub_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the card templates. ?></div>
		<?php endif; ?>
		<?php if ( '' !== $bb_hub_side ) : ?>
			<div class="bb-hub-side"><?php echo $bb_hub_side; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the card templates. ?></div>
		<?php endif; ?>
	</div>
</div>
