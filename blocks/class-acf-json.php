<?php
/**
 * ACF Local JSON: load and save field definitions under the plugin's acf-json directory.
 *
 * @package Base*Belles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers ACF JSON paths for Base*Belles.
 */
class Basebelles_ACF_JSON {

	/**
	 * Absolute path to the acf-json directory (trailing slash).
	 *
	 * @return string
	 */
	public static function json_dir() {
		return trailingslashit( dirname( __DIR__ ) . '/acf-json' );
	}

	/**
	 * Register filters with ACF.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'acf/settings/save_json', array( __CLASS__, 'save_json_path' ) );
		add_filter( 'acf/settings/load_json', array( __CLASS__, 'load_json_paths' ) );
	}

	/**
	 * Where ACF writes JSON when field groups are saved in the admin.
	 *
	 * @param string $path Default save path.
	 * @return string
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- ACF filter signature.
	public static function save_json_path( $path ) {
		return self::json_dir();
	}

	/**
	 * Additional load paths for Local JSON (theme paths remain; plugin JSON is appended).
	 *
	 * @param string[] $paths Load paths.
	 * @return string[]
	 */
	public static function load_json_paths( $paths ) {
		$paths[] = self::json_dir();
		return $paths;
	}
}
