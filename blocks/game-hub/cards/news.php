<?php
/**
 * Game Hub: Guardians headlines from MLB.com. Included by ../render.php, which has set
 * $bb_hub_api. Headlines only, linking out to MLB.com; see
 * Basebelles_API::get_guardians_news() for the caching.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_news_items = (array) $bb_hub_api->get_guardians_news( 5 );
?>

<section class="bb-hub-card bb-hub-news">
	<h3 class="bb-hub-eyebrow">Guardians news <span class="bb-hub-source">· MLB.com</span></h3>

	<?php if ( ! empty( $bb_news_items ) ) : ?>
		<ul class="bb-hub-news-list">
			<?php foreach ( $bb_news_items as $bb_news_item ) : ?>
				<li class="bb-hub-news-item">
					<a href="<?php echo esc_url( $bb_news_item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $bb_news_item['title'] ); ?></a>
					<?php if ( ! empty( $bb_news_item['timestamp'] ) ) : ?>
						<time datetime="<?php echo esc_attr( gmdate( 'c', (int) $bb_news_item['timestamp'] ) ); ?>"><?php echo esc_html( wp_date( 'M j', (int) $bb_news_item['timestamp'] ) ); ?></time>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="bb-hub-empty">No headlines right now.</p>
	<?php endif; ?>

	<a class="bb-hub-link" href="https://www.mlb.com/guardians/news" target="_blank" rel="noopener noreferrer">All Guardians news <span aria-hidden="true">→</span></a>
</section>
