<?php
/**
 * Streamable oEmbed helpers (MLB /m/… URLs often lack API support).
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map www.mlb.com/video/{slug} to the Streamable iframe URL used by MLB (same clip as streamable.com/m/{slug}).
 *
 * @param string $url MLB or Streamable URL.
 * @return string Normalized Streamable URL, or empty string if not an MLB /video/ link.
 */
function basebelles_mlb_video_url_to_streamable( $url ) {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return '';
	}

	$parsed = wp_parse_url( $url );
	if ( empty( $parsed['host'] ) || false === stripos( $parsed['host'], 'mlb.com' ) ) {
		return '';
	}
	if ( empty( $parsed['path'] ) ) {
		return '';
	}

	$path = trim( $parsed['path'], '/' );
	if ( ! preg_match( '`^video/([^/]+)$`', $path, $matches ) ) {
		return '';
	}

	$slug = $matches[1];
	$out  = 'https://streamable.com/m/' . rawurlencode( $slug );
	if ( ! empty( $parsed['query'] ) ) {
		$out .= '?' . $parsed['query'];
	}

	return $out;
}

/**
 * Accept MLB /video/… or any streamable.com URL for embed resolution.
 *
 * @param string $url User-provided URL.
 * @return string Streamable URL suitable for oEmbed / iframe, or empty if unsupported.
 */
function basebelles_resolve_streamable_iframe_url( $url ) {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return '';
	}

	$from_mlb = basebelles_mlb_video_url_to_streamable( $url );
	if ( $from_mlb ) {
		return $from_mlb;
	}

	if ( false !== stripos( $url, 'streamable.com' ) ) {
		return $url;
	}

	return '';
}

/**
 * Streamable /m/… MLB partner URLs often play in the browser but return no data from the Streamable oEmbed API.
 *
 * @param string $url URL to test.
 * @return bool
 */
function basebelles_is_streamable_m_embed_url( $url ) {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return false;
	}

	return (bool) preg_match( '~^https?://(?:www\.)?streamable\.com/m/[^#]+~i', $url );
}

/**
 * Minimal iframe embed when oEmbed is unavailable (see basebelles_is_streamable_m_embed_url()).
 *
 * @param string $url Sanitized Streamable URL.
 * @return string
 */
function basebelles_streamable_iframe_embed_html( $url ) {
	return sprintf(
		'<iframe src="%s" width="560" height="315" style="border:0;" allowfullscreen loading="lazy" title="%s"></iframe>',
		esc_url( $url ),
		esc_attr__( 'Streamable video', 'basebelles' )
	);
}

/**
 * Prefer oEmbed HTML; if empty and URL is a Streamable /m/… page, use an iframe fallback.
 *
 * @param string $url Streamable URL.
 * @return string HTML or empty string.
 */
function basebelles_get_streamable_embed_or_fallback( $url ) {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return '';
	}

	$resolved = basebelles_resolve_streamable_iframe_url( $url );
	if ( empty( $resolved ) ) {
		return '';
	}

	$embed_html = wp_oembed_get( $resolved );
	if ( $embed_html ) {
		return $embed_html;
	}

	if ( basebelles_is_streamable_m_embed_url( $resolved ) ) {
		return '<div class="streamable-video-container">' . basebelles_streamable_iframe_embed_html( $resolved ) . '</div>';
	}

	return '';
}
