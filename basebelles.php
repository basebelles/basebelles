<?php
/**
 * Plugin Name: Base*Belles
 * Plugin URI:  https://github.com/Ipstenu/basebelles
 * Description: All the base code for Base*Belles - This controls all the blocks.
 * Version: 1.1.0
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
		self::$version = '1.1.0';
		
		// Quality of Life
		add_action( 'pre_ping', array( $this, 'no_self_ping' ) );
		add_filter( 'upload_mimes', array( $this, 'custom_upload_mimes' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
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
	 * Enqueue the styles for the plugin.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'basebelles-style', plugin_dir_url( __FILE__ ) . 'basebelles.css', array(), self::$version );
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

}

new Basebelles();
