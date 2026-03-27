<?php
/**
 * Streamable Embed Integration for Basebelles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Embeds {
	public function __construct() {
		// Register Streamable as an oEmbed provider
		// Pattern: https://streamable.com/*
		wp_oembed_add_provider( '#https?://streamable\.com/.*#i', 'https://api.streamable.com/oembed.json', true );

		// Shortcode for manual embeds
		add_shortcode( 'streamable', array( $this, 'streamable_shortcode' ) );
	}

	/**
	 * Shortcode to display a Streamable video.
	 * 
	 * Usage: [streamable id="bo-naylor-s-rbi-single-x4139"]
	 * or     [streamable id="https://streamable.com/m/bo-naylor-s-rbi-single-x4139"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The video embed HTML.
	 */
	public function streamable_shortcode( $atts ) {
		$attributes = shortcode_atts(
			array(
				'id' => '',
			),
			$atts
		);

		if ( empty( $attributes['id'] ) ) {
			return '';
		}

		$url = $attributes['id'];

		// If it's just an ID (no 'http'), prepend the base URL.
		if ( false === strpos( $url, 'http' ) ) {
			$url = 'https://streamable.com/' . $url;
		}

		// Use WordPress's built-in oEmbed retrieval
		$embed_html = wp_oembed_get( $url );

		if ( ! $embed_html ) {
			return sprintf( '<!-- Streamable embed failed for: %s -->', esc_url( $url ) );
		}

		// Wrap in a responsive container
		return '<div class="streamable-video-container">' . $embed_html . '</div>';
	}
}

new Basebelles_Embeds();
