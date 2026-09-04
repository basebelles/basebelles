<?php
/**
 * ACF Block: the Belle directory.
 *
 * Renders every published `belle` post as a card. Pending Belles are invisible here, which is
 * the whole moderation story: publish to approve, trash to spam.
 *
 * @package Base*Belles
 *
 * @var array $block      Block settings and attributes.
 * @var bool  $is_preview Whether the block is rendering inside the editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ACF passes $is_preview for editor renders; is_admin() is a weaker proxy that misses the
// REST block-renderer route, so prefer the flag and fall back to it.
$in_editor = ( isset( $is_preview ) && $is_preview ) || is_admin();

if ( ! class_exists( 'Basebelles_Belles' ) ) {
	if ( $in_editor ) {
		echo '<div class="basebelles-belles placeholder"><p>The Belle directory is unavailable.</p></div>';
	}

	return;
}

$belles = Basebelles_Belles::get_published_belles( 'title' );

if ( empty( $belles ) ) {
	if ( $in_editor ) {
		echo '<div class="basebelles-belles placeholder">';
		echo '<h3>Belle Directory</h3>';
		echo '<p>No Belles have been approved yet. Approved submissions will appear here as cards.</p>';
		echo '</div>';
	}

	return;
}

$anchor  = ! empty( $block['anchor'] ) ? (string) $block['anchor'] : '';
$classes = 'basebelles-belles';

if ( ! empty( $block['align'] ) ) {
	$classes .= ' align' . sanitize_html_class( $block['align'] );
}

if ( '' !== $anchor ) {
	printf( '<ul class="%s" id="%s">', esc_attr( $classes ), esc_attr( $anchor ) );
} else {
	printf( '<ul class="%s">', esc_attr( $classes ) );
}

foreach ( $belles as $belle ) {
	$initials = Basebelles_Belles::get_initials( $belle['name'] );
	$color    = Basebelles_Belles::get_monogram_color( $belle['name'] );
	$current  = array_filter( (array) wp_list_pluck( $belle['current'], 'name' ) );

	echo '<li class="basebelles-belle">';

	printf(
		'<div class="basebelles-belle-avatar" style="--bb-belle-monogram-bg: %s;">',
		esc_attr( $color )
	);

	// The monogram always renders underneath. A Gravatar, when one exists, sits on top of it.
	printf(
		'<span class="basebelles-belle-monogram" aria-hidden="true">%s</span>',
		esc_html( $initials )
	);

	// Decorative: the name is already the heading immediately below.
	if ( '' !== $belle['gravatar'] ) {
		printf(
			'<img class="basebelles-belle-photo" src="%s" width="80" height="80" loading="lazy" decoding="async" alt="" />',
			esc_url( $belle['gravatar'] )
		);
	}

	echo '</div>';

	echo '<div class="basebelles-belle-body">';
	printf( '<h3 class="basebelles-belle-name">%s</h3>', esc_html( $belle['name'] ) );

	if ( '' !== $belle['location'] ) {
		printf( '<p class="basebelles-belle-location">%s</p>', esc_html( $belle['location'] ) );
	}

	if ( ! empty( $current ) || '' !== $belle['historical'] ) {
		echo '<dl class="basebelles-belle-faves">';

		if ( ! empty( $current ) ) {
			echo '<dt>Current favorite' . ( count( $current ) > 1 ? 's' : '' ) . '</dt>';
			printf( '<dd>%s</dd>', esc_html( implode( ', ', $current ) ) );
		}

		if ( '' !== $belle['historical'] ) {
			echo '<dt>All-time favorite</dt>';
			printf( '<dd>%s</dd>', esc_html( $belle['historical'] ) );
		}

		echo '</dl>';
	}

	echo '</div>';
	echo '</li>';
}

echo '</ul>';
