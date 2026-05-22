<?php
/**
 * Streamable Embed Integration for Basebelles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 1 ) . '/helpers/class-streamable-oembed.php';

class Basebelles_Embeds {
	public function __construct() {
		// Register Streamable as an oEmbed provider
		// Pattern: https://streamable.com/*
		wp_oembed_add_provider( '#https?://streamable\.com/.*#i', 'https://api.streamable.com/oembed.json', true );
		// Pattern: https://www.mlb.com/video/*
		wp_oembed_add_provider( '#https?://www\.mlb\.com/video/.*#i', 'https://api.streamable.com/oembed.json', true );

		// Bare /m/… URLs: oEmbed API often fails; use iframe fallback (see basebelles_get_streamable_embed_or_fallback()).
		wp_embed_register_handler(
			'basebelles_streamable_m',
			'~https?://(?:www\.)?streamable\.com/m/[^#<\s]+~i',
			array( $this, 'embed_handler_streamable_m' )
		);

		// www.mlb.com/video/… uses the same Streamable /m/… player; map in helpers.
		wp_embed_register_handler(
			'basebelles_mlb_video',
			'~https?://(?:[\w-]+\.)?mlb\.com/video/[^#<\s]+~i',
			array( $this, 'embed_handler_streamable_m' )
		);

		// Wrap Streamable oEmbed HTML in the responsive container (shortcode, pasted URLs, Embed block, ACF block).
		add_filter( 'oembed_result', array( $this, 'wrap_streamable_oembed' ), 10, 3 );

		// Shortcode for manual embeds
		add_shortcode( 'streamable', array( $this, 'streamable_shortcode' ) );
	}

	/**
	 * Embed handler for Streamable /m/… URLs when oEmbed returns nothing.
	 *
	 * @param array  $matches Regex matches.
	 * @param array  $attr    Embed attributes.
	 * @param string $url     Discovered URL.
	 * @param array  $rawattr Raw attributes.
	 * @return string
	 */
	public function embed_handler_streamable_m( $matches, $attr, $url, $rawattr ) {
		unset( $matches, $attr, $rawattr );

		return basebelles_get_streamable_embed_or_fallback( $url );
	}

	/**
	 * Wrap Streamable oEmbed HTML in the responsive container (shortcode, pasted URLs, Embed block, ACF block).
	 *
	 * @param string $html  Provider HTML.
	 * @param string $url   Original URL.
	 * @param mixed  $args  oEmbed request args (array or context).
	 * @return string
	 */
	public function wrap_streamable_oembed( $html, $url, $args ) {
		unset( $args );

		if ( empty( $html ) || empty( $url ) ) {
			return $html;
		}

		if ( false === stripos( $url, 'streamable.com' ) && false === stripos( $url, 'mlb.com' ) ) {
			return $html;
		}

		if ( false !== strpos( $html, 'streamable-video-container' ) ) {
			return $html;
		}

		return '<div class="streamable-video-container">' . $html . '</div>';
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

		$embed_html = basebelles_get_streamable_embed_or_fallback( $url );

		if ( ! $embed_html ) {
			return sprintf( '<!-- Streamable embed failed for: %s -->', esc_url( $url ) );
		}

		return $embed_html;
	}
}

new Basebelles_Embeds();
