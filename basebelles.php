<?php
/**
 * Plugin Name: Base*Belles
 * Plugin URI:  https://github.com/Ipstenu/basebelles
 * Description: All the base code for Base*Belles - This controls all the amazing features.
 * Version: 1.3.0
 * Author: Ipstenu
 *
 * @package Base*Belles
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Basebelles {

	public static $version;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		self::$version = '1.3.0';

		// ACF
		require_once 'blocks/class-acf-json.php';
		new Basebelles_ACF_JSON();

		// Quality of Life
		add_action( 'pre_ping', array( $this, 'no_self_ping' ) );
		add_filter( 'upload_mimes', array( $this, 'custom_upload_mimes' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'register_styles' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_init', array( $this, 'admin_color_scheme' ) );

		add_action( 'wp_head', array( $this, 'wp_head' ), 20 );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 10 );

		add_action( 'admin_menu', array( $this, 'set_guardians_menu_icon' ), 99 );
	}

	/**
	 * Register custom query vars (avoid `year` in URLs — it is a core date-archive var and triggers redirects to /YYYY/).
	 *
	 * @param string[] $vars Public query variables.
	 * @return string[]
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'season_year';
		return $vars;
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init() {
		// Belle Features
		require_once 'features/class-comment-probation.php';
		require_once 'features/class-embeds.php';
		require_once 'features/class-patterns.php';
		require_once 'features/class-series-generator.php';

		// General Helpers
		require_once 'helpers/class-api.php';
		require_once 'helpers/class-impostercide.php';
		require_once 'helpers/class-in-progress.php';
		require_once 'helpers/class-no-tracking.php';
		require_once 'helpers/class-upgrades.php';

		// Blocks
		require_once 'blocks/class-blocks.php';
	}

	/**
	 * Register the main stylesheet so other handles can depend on it (e.g. block CSS in the editor).
	 *
	 * @return void
	 */
	public function register_styles() {
		wp_register_style( 'basebelles-style', plugin_dir_url( __FILE__ ) . 'basebelles.css', array(), self::$version );
	}

	/**
	 * Enqueue the styles for the plugin.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'basebelles-style' );
	}

	/**
	 * Enqueue the admin color scheme stylesheet.
	 *
	 * @return void
	 */
	public function admin_color_scheme() {
		wp_admin_css_color(
			'basebelles',
			__( 'BaseBelles' ),
			plugin_dir_url( __FILE__ ) . '/admin-color-scheme.css',
			array( '#002d62', '#f3efe0', '#84172c', '#009ddc', '#f3efe0' ),
		);
	}

	/*
	 * Prevent self-pings
	 *
	 * @access public
	 * @param array $links
	 * @return void
	 */
	public function no_self_ping( &$links ) {
		$home = get_option( 'home' );
		foreach ( $links as $l => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $l ] );
			}
		}
	}

	/*
	 * Custom upload mimes
	 *
	 * @access public
	 * @param array $existing_mimes
	 * @return array
	 */
	public function custom_upload_mimes( $existing_mimes ) {
		$existing_mimes['epub'] = 'application/epub+zip'; //allow epub files
		$existing_mimes['webm'] = 'video/webm'; //allow epub file
		return $existing_mimes;
	}

	/**
	 * Custom Header
	 *
	 * @return void
	 */
	public function wp_head() {
		echo '<link rel="shortcut icon" href="' . esc_url( home_url( '/favicon.ico?v=' . self::$version ) ) . '" type="image/x-icon" />';
		echo '<link rel="icon" href="' . esc_url( home_url( '/favicon.ico?v=' . self::$version ) ) . '" type="image/x-icon" />';
	}

	/**
	 * Pre Get Posts
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only modify the main query on the frontend for your specific taxonomy archive
		if ( is_tax( 'season-type' ) ) {
			$year = get_query_var( 'season_year' );
			if ( ! $year ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$year = isset( $_GET['season_year'] ) ? (int) wp_unslash( $_GET['season_year'] ) : false;
			}

			if ( $year && is_numeric( $year ) ) {
				$query->set( 'year', (int) $year );
			}
		}
	}

	/**
	 * Replace the Guardians Settings menu icon with the custom baseball SVG.
	 *
	 * ACFE registers the page via add_menu_page() using a dashicon; we swap in
	 * a base64 data URI after registration so WordPress's SVG painter can tint
	 * it correctly for the active admin color scheme.
	 *
	 * @return void
	 */
	public function set_guardians_menu_icon() {
		global $menu;
		$icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NDAgNjQwIj48cGF0aCBmaWxsPSIjMDAwMDAwIiBkPSJNNDk2LjEgNjA4QzQ1MS45IDYwOCA0MTYuMSA1NzIuMiA0MTYuMSA1MjhDNDE2LjEgNDgzLjggNDUxLjkgNDQ4IDQ5Ni4xIDQ0OEM1NDAuMyA0NDggNTc2LjEgNDgzLjggNTc2LjEgNTI4QzU3Ni4xIDU3Mi4yIDU0MC4zIDYwOCA0OTYuMSA2MDh6TTUwNC41IDMyQzUxOS42IDMyIDUzNC4yIDM3LjggNTQ1LjIgNDguMkw1OTIuNCA5M0M2MDUgMTA1IDYxMS41IDEyMiA2MDkuOSAxMzkuM0M2MDguNyAxNTIuNiA2MDIuOSAxNjUuMSA1OTMuNCAxNzQuNUwzOTUuMSAzNzNDMzg3LjggMzgwLjMgMzc5LjQgMzg2LjQgMzcwLjEgMzkxTDIzOCA0NTdDMjI4LjggNDYxLjYgMjIwLjMgNDY3LjcgMjEzIDQ3NUwxMjEgNTY3TDEyMi43IDU2OC44QzEzMC40IDU3OC4yIDEyOS44IDU5Mi4xIDEyMSA2MDAuOUMxMTIuMiA2MDkuNyA5OC4zIDYxMC4yIDg4LjkgNjAyLjZMODcuMSA2MDAuOUwzOS4xIDU1Mi45TDM3LjQgNTUxLjFDMjkuNyA1NDEuNyAzMC4zIDUyNy44IDM5LjEgNTE5QzQ3LjkgNTEwLjIgNjEuOCA1MDkuNyA3MS4yIDUxNy4zTDczIDUxOUwxNjUgNDI3QzE3Mi4zIDQxOS43IDE3OC40IDQxMS4zIDE4MyA0MDJMMjQ5LjEgMjY5LjlDMjUzLjcgMjYwLjcgMjU5LjggMjUyLjIgMjY3LjEgMjQ0LjlMNDYyLjggNDkuM0M0NzMuOSAzOC4yIDQ4OC45IDMyIDUwNC41IDMyeiIvPjwvc3ZnPg==';

		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && 'guardians-settings' === $item[2] ) {
				$menu[ $key ][6] = $icon; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				break;
			}
		}
	}
}

new Basebelles();
