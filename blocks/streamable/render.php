<?php
/**
 * ACF Block Render Template: Streamable video
 *
 * @package Base*Belles
 *
 * @var array $block The block settings and attributes.
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

$raw_url = get_field( 'streamable_url' );

if ( empty( $raw_url ) ) {
	if ( is_admin() ) {
		echo '<p class="basebelles-streamable-placeholder">' . esc_html__( 'Add a Streamable URL in the block sidebar.', 'basebelles' ) . '</p>';
	}
	return;
}

$url    = esc_url_raw( $raw_url );
$parsed = wp_parse_url( $url );

if ( empty( $parsed['host'] ) || ( false === stripos( $parsed['host'], 'streamable.com' ) && false === stripos( $parsed['host'], 'mlb.com' ) ) ) {
	if ( is_admin() ) {
		echo '<p class="basebelles-streamable-placeholder">' . esc_html__( 'URL must be a streamable.com or mlb.com video link.', 'basebelles' ) . '</p>';
	}
	return;
}

$embed_html = basebelles_get_streamable_embed_or_fallback( $url );

if ( ! $embed_html ) {
	if ( is_admin() ) {
		echo '<p class="basebelles-streamable-placeholder">' . esc_html__( 'Could not load this Streamable embed.', 'basebelles' ) . '</p>';
	}
	return;
}

echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oEmbed HTML or Streamable iframe from trusted host.
