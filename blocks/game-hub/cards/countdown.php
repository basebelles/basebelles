<?php
/**
 * Game Hub: the off-season Spring Training countdown. Included by ../render.php, which has set
 * $bb_hub_api, $bb_hub_season and (once the postseason has been looked at) $bb_hub_postseason.
 *
 * The number only shows when there's a known future start date; otherwise the card keeps its
 * "See you in spring" line and the season recap link. See
 * Basebelles_API::get_spring_training_start() for where the date comes from.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_cd_start = (string) $bb_hub_api->get_spring_training_start();
$bb_cd_days  = '' !== $bb_cd_start ? Basebelles_Game_Hub::days_until( $bb_cd_start, wp_date( 'Y-m-d' ) ) : null;
$bb_cd_label = '';

if ( null !== $bb_cd_days ) {
	$bb_cd_date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $bb_cd_start, new DateTimeZone( 'UTC' ) );
	$bb_cd_label = $bb_cd_date ? $bb_cd_date->format( 'M j' ) : '';
}

// The recap is the season-type archive: the postseason one if the Guardians got there.
if ( null === $bb_hub_postseason ) {
	$bb_hub_postseason = $bb_hub_api->get_postseason_status( $bb_hub_season );
}

$bb_cd_qualified = is_array( $bb_hub_postseason ) && 'not_qualified' !== (string) ( $bb_hub_postseason['state'] ?? 'not_qualified' );
$bb_cd_recap     = ( $bb_cd_qualified ? '/season-type/post-season/' : '/season-type/regular-season/' ) . '?season_year=' . (int) $bb_hub_season;
?>

<section class="bb-hub-card bb-hub-countdown">
	<h3 class="bb-hub-eyebrow is-accent">See you in spring</h3>

	<?php if ( 0 === $bb_cd_days ) : ?>
		<p class="bb-hub-count-today">Spring Training begins today</p>
	<?php elseif ( null !== $bb_cd_days ) : ?>
		<p class="bb-hub-count">
			<span class="bb-hub-count-number"><?php echo (int) $bb_cd_days; ?></span>
			<span class="bb-hub-count-unit"><?php echo 1 === $bb_cd_days ? 'day' : 'days'; ?></span>
		</p>
		<p class="bb-hub-count-caption">
			until Spring Training begins
			<?php if ( '' !== $bb_cd_label ) : ?>
				· <time datetime="<?php echo esc_attr( $bb_cd_start ); ?>"><?php echo esc_html( $bb_cd_label ); ?></time>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<a class="bb-hub-button" href="<?php echo esc_url( home_url( $bb_cd_recap ) ); ?>"><?php echo (int) $bb_hub_season; ?> season recap</a>
</section>
