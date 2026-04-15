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
	 * Absolute path to the acf-json directory (trailing slash).
	 *
	 * @return string
	 */
	public static function json_dir() {
		$json_dir = plugin_dir_path( __DIR__ ) . 'acf-json/';
		return $json_dir;
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

	/**
	 * ACF 6.2+: same load list as `acf/settings/load_json`, applied in `ACF_Local_JSON::get_load_paths()`.
	 * Ensures WP-CLI (`wp acf json sync`, `wp acf json status`) sees plugin JSON, not only the theme folder.
	 *
	 * @param string[] $paths Load paths.
	 * @return string[]
	 */
	public static function json_load_paths( $paths ) {
		// Remove the original path (optional).
		unset($paths[0]);

		// Append the new path and return it.
		$paths[] = self::json_dir();

		return $paths;
	}
}
