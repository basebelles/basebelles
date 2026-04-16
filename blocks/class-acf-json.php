<?php
/**
 * ACF Local JSON: load and save field definitions under the plugin's acf-json directory.
 *
 * Save path uses acf/settings/save_json (universal save point). See ACF Local JSON:
 * https://www.advancedcustomfields.com/resources/local-json/
 * Load uses acf/settings/load_json and acf/json/load_paths (ACF 6.2+) so CLI and admin resolve the same directories.
 *
 * @package Base*Belles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers ACF JSON paths for Base*Belles.
 */
class Basebelles_ACF_JSON {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'acf/settings/save_json', array( __CLASS__, 'save_json_path' ) );
		add_filter( 'acf/settings/load_json', array( __CLASS__, 'load_json_paths' ) );
		add_filter( 'acf/json/load_paths', array( __CLASS__, 'load_json_paths' ) );

		// Only hide the UI on Production
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production' ) {
			add_filter( 'acf/settings/show_admin', '__return_false' );
		}
	}

	/**
	 * Absolute path to the acf-json directory (trailing slash).
	 *
	 * @return string
	 */
	public static function json_dir() {
		$json_dir = plugin_dir_path( __DIR__ ) . 'acf-json/';
		return $json_dir;
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
	 * ACF 6.2+: same load list as `acf/settings/load_json`, applied in `ACF_Local_JSON::get_load_paths()`.
	 * Ensures WP-CLI (`wp acf json sync`, `wp acf json status`) sees plugin JSON, not only the theme folder.
	 *
	 * @param string[] $paths Load paths.
	 * @return string[]
	 */
	public static function load_json_paths( $paths ) {
		// Append the new path and return it.
		$paths[] = self::json_dir();

		return $paths;
	}
}
