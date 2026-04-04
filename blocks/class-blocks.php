<?php
/**
 * Blocks used in Base*Belles
 *
 * @package Base*Belles
 * @since   1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'ABSPATH' ) || exit;

class Basebelles_Blocks {
	private static $version;

	/**
	 * Constructor
	 *
	 * Registers the blocks if ACF is active.
	 *
	 * @return void
	 */
	public function __construct() {
		self::$version = Basebelles::$version;
		add_action( 'init', array( $this, 'register_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_styles' ) );
		
		self::register_blocks();
		add_filter( 'block_categories_all', array( $this, 'block_categories' ), 10, 2 );
	}

	/**
	 * Register the blocks
	 *
	 * @return void
	 */
	public static function register_blocks() {
		register_block_type( __DIR__ . '/results' );
		register_block_type( __DIR__ . '/season-header' );
		register_block_type( __DIR__ . '/series' );
		register_block_type( __DIR__ . '/standings' );
		register_block_type( __DIR__ . '/today-game' );
	}

	/**
	 * Register shared stylesheet handles.
	 *
	 * @return void
	 */
	public function register_styles() {
		wp_register_style( 'basebelles-results-style', plugin_dir_url( __FILE__ ) . 'results/block.css', array(), self::$version );
		wp_register_style( 'basebelles-series-style', plugin_dir_url( __FILE__ ) . 'series/block.css', array(), self::$version );
		wp_register_style( 'basebelles-season-header-style', plugin_dir_url( __FILE__ ) . 'season-header/block.css', array(), self::$version );
		wp_register_style( 'basebelles-standings-style', plugin_dir_url( __FILE__ ) . 'standings/block.css', array(), self::$version );
		wp_register_style( 'basebelles-today-game-style', plugin_dir_url( __FILE__ ) . 'today-game/block.css', array(), self::$version );
	}

	/**
	 * Add the Base*Belles category to the block categories.
	 *
	 * @param array $categories The block categories.
	 * @param object $post The post object.
	 * @return array The block categories.
	 */
	public function block_categories( $categories, $post ) {
		unset( $post );

		return array_merge(
			$categories,
			array(
				'basebelles' => array(
					'slug'  => 'basebelles',
					'title' => 'Base*Belles',
					'icon'  => 'bell',
				),
			)
		);
	}

	/**
	 * Enqueue the styles for the plugin.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'basebelles-results-style', plugin_dir_url( __FILE__ ) . 'results/block.css', array(), self::$version );
		wp_enqueue_style( 'basebelles-series-style', plugin_dir_url( __FILE__ ) . 'series/block.css', array(), self::$version );
		wp_enqueue_style( 'basebelles-season-header-style', plugin_dir_url( __FILE__ ) . 'season-header/block.css', array(), self::$version );
		wp_enqueue_style( 'basebelles-standings-style', plugin_dir_url( __FILE__ ) . 'standings/block.css', array(), self::$version );
		wp_enqueue_style( 'basebelles-today-game-style', plugin_dir_url( __FILE__ ) . 'today-game/block.css', array(), self::$version );
	}
}

new Basebelles_Blocks();
