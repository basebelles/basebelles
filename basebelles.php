<?php
/**
 * Plugin Name: Base*Belles
 * Plugin URI:  https://github.com/Ipstenu/basebelles
 * Description: All the base code for Base*Belles - This controls all the blocks.
 * Version: 1.0.2
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
		self::$version = '1.0.2';

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init() {
		require_once 'class-embeds.php';
		require_once 'class-schedule.php';
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
}

new Basebelles();
