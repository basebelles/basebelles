<?php
/**
 * Block Patterns for Basebelles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Patterns {
	public function __construct() {
		$this->register_pattern_categories();
		$this->register_patterns();
	}

	/**
	 * Register the block pattern categories for the plugin.
	 *
	 * @return void
	 */
	public function register_pattern_categories() {
		register_block_pattern_category(
			'basebelles',
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
			'game-series'          => array(
				'title'       => __( 'Game Series Template', 'basebelles' ),
				'slug'        => 'basebelles/game-series',
				'description' => __( 'Standard layout for basebelles game recaps.', 'basebelles' ),
				'keywords'    => array( 'basebelles', 'game', 'series', 'recap', 'template' ),
				'file'        => 'patterns/game-series.php',
			),
			'game-series-one-game' => array(
				'title'       => __( 'Game Series: One Game', 'basebelles' ),
				'slug'        => 'basebelles/game-series-one-game',
				'description' => __( 'Layout for a game series recap when only one game has been played.', 'basebelles' ),
				'keywords'    => array( 'basebelles', 'game', 'series', 'recap', 'one game' ),
				'file'        => 'patterns/game-series-one-game.php',
			),
			'game-series-header'   => array(
				'title'       => __( 'Game Series Header', 'basebelles' ),
				'slug'        => 'basebelles/game-series-header',
				'description' => __( 'Header for game series recaps.', 'basebelles' ),
				'keywords'    => array( 'basebelles', 'game', 'series', 'header' ),
				'file'        => 'patterns/game-series-header.php',
			),
			'game-series-end'      => array(
				'title'       => __( 'Game Series End', 'basebelles' ),
				'slug'        => 'basebelles/game-series-end',
				'description' => __( 'End section for game series recaps.', 'basebelles' ),
				'keywords'    => array( 'basebelles', 'game', 'series', 'end' ),
				'file'        => 'patterns/game-series-end.php',
			),
		);

		foreach ( $patterns as $slug => $data ) {
			$filepath = plugin_dir_path( dirname( __FILE__ ) ) . $data['file'];

			if ( file_exists( $filepath ) ) {
				ob_start();
				include $filepath;

				$data['content'] = trim( ob_get_clean() );
				unset( $data['file'] );

				// For simplicity, all patterns are registered to post editor and categorized
				// under 'featured', 'text', and 'basebelles'.
				$data['categories'] = array( 'featured', 'text', 'basebelles' );

				register_block_pattern( $slug, $data );
			}
		}
	}
}

new Basebelles_Patterns();
