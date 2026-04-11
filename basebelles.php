<?php
/**
 * Plugin Name: Base*Belles
 * Plugin URI:  https://github.com/Ipstenu/basebelles
 * Description: All the base code for Base*Belles - This controls all the blocks.
 * Version: 1.2.2
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
		self::$version = '1.2.1';

		// Quality of Life
		add_action( 'pre_ping', array( $this, 'no_self_ping' ) );
		add_filter( 'upload_mimes', array( $this, 'custom_upload_mimes' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'register_styles' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		add_action( 'wp_head', array( $this, 'wp_head' ), 20 );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 10 );
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
		require_once 'class-api.php';
		require_once 'class-embeds.php';

		// Generic Features
		require_once 'features/class-comment-probation.php';
		require_once 'features/class-impostercide.php';
		require_once 'features/class-in-progress.php';
		require_once 'features/class-no-tracking.php';
		require_once 'features/class-upgrades.php';

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
}

new Basebelles();
