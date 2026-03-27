<?php
/**
 * ACF Block Render Template for Guardians Schedule
 *
 * @var array $block The block settings and attributes.
 */

if ( class_exists( 'Basebelles_Schedule' ) ) {
	$schedule  = Basebelles_Schedule::get_instance();
	$limit     = (int) ( get_field( 'games_displayed' ) ?? 0 );
	$show_logo = get_field( 'show_logo' ) ?? false;
	echo wp_kses_post( $schedule->get_guardians_next_game( $limit, $show_logo ) );
} else {
	echo '<p>Schedule logic missing.</p>';
}
