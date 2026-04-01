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
		if ( class_exists( 'ACF' ) ) {
			if ( did_action( 'acf/init' ) ) {
				$this->register_fields();
			} else {
				add_action( 'acf/init', array( $this, 'register_fields' ) );
			}
		}
	}

	/**
	 * Register the blocks
	 *
	 * @return void
	 */
	public static function register_blocks() {
		register_block_type( __DIR__ . '/schedule' );
		register_block_type( __DIR__ . '/results' );
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
		wp_register_style( 'basebelles-schedule-style', plugin_dir_url( __FILE__ ) . 'schedule/block.css', array(), self::$version );
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
		wp_enqueue_style( 'basebelles-schedule-style', plugin_dir_url( __FILE__ ) . 'schedule/block.css', array(), self::$version );
		wp_enqueue_style( 'basebelles-standings-style', plugin_dir_url( __FILE__ ) . 'standings/block.css', array(), self::$version );
		wp_enqueue_style( 'basebelles-today-game-style', plugin_dir_url( __FILE__ ) . 'today-game/block.css', array(), self::$version );
	}

	/**
	 * Register local ACF fields for plugin blocks.
	 *
	 * @return void
	 */
	public function register_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		call_user_func(
			'acf_add_local_field_group',
			array(
				'key'      => 'group_basebelles_standings',
				'title'    => 'Guardians Standings',
				'fields'   => array(
					array(
						'key'           => 'field_basebelles_standings_display_mode',
						'label'         => 'Display Mode',
						'name'          => 'display_mode',
						'type'          => 'select',
						'choices'       => array(
							'standard' => 'Standard',
							'expanded' => 'Expanded',
						),
						'default_value' => 'standard',
						'return_format' => 'value',
						'allow_null'    => 0,
						'multiple'      => 0,
						'ui'            => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/basebelles-standings',
						),
					),
				),
			)
		);

		call_user_func(
			'acf_add_local_field_group',
			array(
				'key'      => 'group_basebelles_today_game',
				'title'    => 'Guardians Today\'s Game',
				'fields'   => array(
					array(
						'key'                     => 'field_basebelles_today_game_game_date',
						'label'                   => 'Game Date',
						'name'                    => 'game_date',
						'type'                    => 'date_picker',
						'display_format'          => 'd/m/Y',
						'return_format'           => 'Y-m-d',
						'first_day'               => 0,
						'default_to_current_date' => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/basebelles-today-game',
						),
					),
				),
			)
		);
	}
}

new Basebelles_Blocks();
