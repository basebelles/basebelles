<?php
/**
 * Block Patterns for Basebelles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Patterns {
	public function __construct() {
		add_action( 'init', array( $this, 'register_pattern_categories' ) );
		add_action( 'init', array( $this, 'register_patterns' ) );
	}

	/**
	 * Register the block pattern categories for the plugin.
	 *
	 * @return void
	 */
	public function register_pattern_categories() {
		register_block_pattern_category(
			'basebelles/custom',
			array(
				'label'       => __( 'Base*Belles: Custom', 'basebelles' ),
				'description' => __( 'Custom patterns for Base*Belles.', 'basebelles' ),
			),
		);
	}

	/**
	 * Register the patterns for the plugin.
	 *
	 * @return void
	 */
	public function register_patterns() {
		$patterns = array(
			'game-series' => array(
				'title'       => __( 'Game Series Template', 'basebelles' ),
				'slug'        => 'basebelles/game-series',
				'description' => __( 'Standard layout for basebelles game recaps.', 'basebelles' ),
				'keywords'    => array( 'basebelles', 'game', 'series', 'recap', 'template' ),
				'postTypes'   => array( 'post' ),
				'file'        => 'patterns/game-series.php',
				'categories'  => array( 'featured', 'text', 'basebelles/custom' ),
			),
			'one-game' => array(
				'title'       => __( 'One Game Template', 'basebelles' ),
				'slug'        => 'basebelles/one-game',
				'description' => __( 'Layout for a single game recap.', 'basebelles' ),
				'keywords'    => array( 'basebelles', 'game', 'recap', 'template' ),
				'postTypes'   => array( 'post' ),
				'file'        => 'patterns/one-game.php',
				'categories'  => array( 'featured', 'text', 'basebelles/custom' ),
			),
		);

		foreach ( $patterns as $slug => $data ) {
			$filepath = plugin_dir_path( __FILE__ ) . $data['file'];

			if ( file_exists( $filepath ) ) {
				ob_start();
				include $filepath;

				$data['content'] = trim( ob_get_clean() );
				unset( $data['file'] );

				register_block_pattern( 'basebelles/' . $slug, $data );
			}
		}
	}
}
