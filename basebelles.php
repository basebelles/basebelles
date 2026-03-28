<?php
/**
 * Plugin Name: Base*Belles
 * Plugin URI:  https://github.com/Ipstenu/basebelles
 * Description: All the base code for Base*Belles - This controls all the blocks.
 * Version: 1.0
 * Author: Ipstenu
 *
 * @package Base*Belles
*/

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Basebelles {

	protected static $version;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		self::$version = '1.0.0';

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'basebelles_enqueue_styles' ) );
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init() {
		require_once 'class-embeds.php';
		require_once 'class-schedule.php';

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type( __DIR__ . '/blocks/schedule' );
			register_block_type( __DIR__ . '/blocks/results' );
		}
	}

	public function basebelles_enqueue_styles() {
		wp_enqueue_style( 'basebelles-style', get_stylesheet_uri(), array(), self::$version );
	}
}

new Basebelles();
