<?php
/**
 * PHPUnit bootstrap.
 *
 * These are unit tests, not WordPress integration tests: nothing here loads WordPress or touches
 * a database. The code under test is the plugin's pure rendering logic -- phase resolution, label
 * building, and block templates -- so the handful of WordPress functions it calls are stubbed
 * below and the MLB API is replaced with a fake whose payloads each test sets.
 *
 * That keeps the suite runnable with nothing but PHP and Composer. The trade is that it proves
 * markup and branching, not that WordPress hands these templates the data they expect.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

define( 'BASEBELLES_TESTS_DIR', __DIR__ );
define( 'BASEBELLES_PLUGIN_DIR', dirname( __DIR__ ) );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', BASEBELLES_PLUGIN_DIR . '/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

/*
 * ---------------------------------------------------------------------------
 * WordPress function stubs.
 *
 * Only the functions the code under test actually calls. Escaping stubs mirror WordPress'
 * real behaviour closely enough that the tests can assert on escaped output.
 * ---------------------------------------------------------------------------
 */

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}

function is_admin() {
	return false;
}

function get_query_var( $var, $default = null ) {
	return $default;
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/basebelles/';
}

function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
}

function wp_script_is( $handle, $status = 'enqueued' ) {
	return false;
}

function wp_enqueue_script( ...$args ) {
	Basebelles_Test_State::$enqueued_scripts[] = $args;
}

function wp_localize_script( ...$args ) {
	Basebelles_Test_State::$localized_scripts[] = $args;
}

/**
 * ACF's get_field(). Returns whatever the test put in Basebelles_Test_State::$fields.
 *
 * @param string $selector Field name.
 * @param mixed  $post_id Post ID or 'option'.
 * @return mixed
 */
function get_field( $selector, $post_id = false ) {
	return Basebelles_Test_State::$fields[ $selector ] ?? null;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** Minimal stand-in: the templates only ever ask "is this an error?". */
class WP_Error {

	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}
}

/**
 * Mutable state the stubs read and the fake API serves, reset between tests.
 */
class Basebelles_Test_State {

	/** @var array Field name => value, for get_field(). */
	public static $fields = array();

	/** @var array|WP_Error Payload for Basebelles_API::get_guardians_today_game(). */
	public static $schedule = array();

	/** @var array|WP_Error Payload for Basebelles_API::fetch_standings(). */
	public static $standings = array();

	/** @var array game_pk => payload, for Basebelles_API::get_live_feed(). */
	public static $live_feeds = array();

	/** @var array|WP_Error Returned by get_live_feed() for any pk without an entry above. */
	public static $live_feed_default = array();

	/** @var array Calls recorded by the wp_enqueue_script() stub. */
	public static $enqueued_scripts = array();

	/** @var array Calls recorded by the wp_localize_script() stub. */
	public static $localized_scripts = array();

	/** @var int[] game_pks passed to get_live_feed(), in order. */
	public static $live_feed_calls = array();

	public static function reset(): void {
		self::$fields            = array();
		self::$schedule          = array();
		self::$standings         = array();
		self::$live_feeds        = array();
		self::$live_feed_default = array();
		self::$enqueued_scripts  = array();
		self::$localized_scripts = array();
		self::$live_feed_calls   = array();
	}
}

/**
 * Fake API. Named to match the real class so the templates' class_exists() guard passes and
 * their Basebelles_API::get_instance() calls resolve here.
 */
class Basebelles_API {

	public static function get_instance() {
		return new self();
	}

	public function get_guardians_today_game( $date = '' ) {
		return Basebelles_Test_State::$schedule;
	}

	public function get_live_feed( $game_pk ) {
		Basebelles_Test_State::$live_feed_calls[] = (int) $game_pk;

		return Basebelles_Test_State::$live_feeds[ (int) $game_pk ] ?? Basebelles_Test_State::$live_feed_default;
	}

	public function fetch_standings( $season_type = 'regularSeason', $season_year = null, $team_id = 0 ) {
		return Basebelles_Test_State::$standings;
	}
}

/** The today-game template reads Basebelles::$version when enqueueing its script. */
class Basebelles {

	public static $version = '0.0.0-test';
}

require_once BASEBELLES_PLUGIN_DIR . '/blocks/today-game/class-panels.php';
require_once BASEBELLES_TESTS_DIR . '/Fixtures.php';
