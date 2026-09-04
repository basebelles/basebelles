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

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
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

/** Defaults to false, matching a front-end request; Belle tests flip it to reach admin paths. */
function is_admin() {
	return Basebelles_Test_State::$is_admin;
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

/*
 * ---------------------------------------------------------------------------
 * Stubs for the Belle directory: hooks, options, posts, meta, cron and HTTP.
 *
 * These are deliberately shallow. sanitize_text_field() and is_email() are close enough to
 * WordPress' behaviour for the assertions that depend on them (tag stripping, newline
 * flattening, rejecting malformed addresses); everything else just records what it was asked
 * to do so a test can look.
 * ---------------------------------------------------------------------------
 */

function add_action( $tag, $callback = null, $priority = 10, $accepted_args = 1 ) {
	Basebelles_Test_State::$hooks[ $tag ][] = array( $callback, $priority );

	return true;
}

function add_filter( $tag, $callback = null, $priority = 10, $accepted_args = 1 ) {
	Basebelles_Test_State::$hooks[ $tag ][] = array( $callback, $priority );

	return true;
}

/**
 * Returns the value untouched unless the test registered a callback for the tag.
 *
 * @param string $tag Filter name.
 * @param mixed  $value Value to filter.
 * @param mixed  ...$args Extra arguments passed to the callback.
 * @return mixed
 */
function apply_filters( $tag, $value = null, ...$args ) {
	if ( ! isset( Basebelles_Test_State::$filters[ $tag ] ) ) {
		return $value;
	}

	return call_user_func_array(
		Basebelles_Test_State::$filters[ $tag ],
		array_merge( array( $value ), $args )
	);
}

function do_action( $tag, ...$args ) {
	Basebelles_Test_State::$actions[] = array( $tag, $args );
}

function register_post_type( $post_type, $args = array() ) {
	Basebelles_Test_State::$post_types[ $post_type ] = $args;

	return (object) array( 'name' => $post_type );
}

function get_option( $name, $default_value = false ) {
	return Basebelles_Test_State::$options[ $name ] ?? $default_value;
}

function update_option( $name, $value, $autoload = null ) {
	Basebelles_Test_State::$options[ $name ] = $value;

	return true;
}

/**
 * wp_insert_post(), against the in-memory store.
 *
 * @param array $postarr Post fields.
 * @param bool  $wp_error Whether to return a WP_Error on failure.
 * @return int|WP_Error
 */
function wp_insert_post( $postarr = array(), $wp_error = false ) {
	if ( null !== Basebelles_Test_State::$insert_post_result ) {
		return Basebelles_Test_State::$insert_post_result;
	}

	return Basebelles_Test_State::add_post(
		array_merge( array( 'post_status' => 'draft' ), $postarr )
	);
}

/**
 * get_posts(), supporting only the arguments the plugin actually passes.
 *
 * @param array $args Query arguments.
 * @return array Post objects, or IDs when 'fields' is 'ids'.
 */
function get_posts( $args = array() ) {
	$args = array_merge(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => '',
			'exclude'        => array(),
			'meta_key'       => '',
			'meta_value'     => '',
		),
		$args
	);

	$statuses = (array) $args['post_status'];
	$exclude  = array_map( 'intval', (array) $args['exclude'] );
	$found    = array();

	foreach ( Basebelles_Test_State::$posts as $post ) {
		if ( $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( ! in_array( $post->post_status, $statuses, true ) ) {
			continue;
		}

		if ( in_array( (int) $post->ID, $exclude, true ) ) {
			continue;
		}

		if ( '' !== $args['meta_key'] ) {
			$stored = Basebelles_Test_State::$meta[ $post->ID ][ $args['meta_key'] ] ?? null;

			if ( null === $stored || (string) $stored !== (string) $args['meta_value'] ) {
				continue;
			}
		}

		$found[] = $post;
	}

	if ( 'title' === $args['orderby'] ) {
		usort(
			$found,
			static function ( $left, $right ) {
				return strcasecmp( (string) $left->post_title, (string) $right->post_title );
			}
		);
	}

	if ( 'DESC' === strtoupper( (string) $args['order'] ) ) {
		$found = array_reverse( $found );
	}

	$limit = (int) $args['posts_per_page'];

	if ( $limit > 0 ) {
		$found = array_slice( $found, 0, $limit );
	}

	if ( 'ids' === $args['fields'] ) {
		return array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			$found
		);
	}

	return $found;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	$value = Basebelles_Test_State::$meta[ (int) $post_id ][ $key ] ?? null;

	if ( null === $value ) {
		return $single ? '' : array();
	}

	return $single ? $value : array( $value );
}

function update_post_meta( $post_id, $key, $value, $prev = '' ) {
	Basebelles_Test_State::$meta[ (int) $post_id ][ $key ] = $value;

	return true;
}

function delete_post_meta( $post_id, $key, $value = '' ) {
	unset( Basebelles_Test_State::$meta[ (int) $post_id ][ $key ] );

	return true;
}

function wp_list_pluck( $list, $field, $index_key = null ) {
	$plucked = array();

	foreach ( (array) $list as $item ) {
		if ( is_object( $item ) ) {
			$plucked[] = $item->$field ?? null;
			continue;
		}

		$plucked[] = is_array( $item ) ? ( $item[ $field ] ?? null ) : null;
	}

	return $plucked;
}

function sanitize_text_field( $str ) {
	$str = strip_tags( (string) $str );
	$str = str_replace( array( "\r", "\n", "\t" ), ' ', $str );
	$str = preg_replace( '/ +/', ' ', $str );

	return trim( (string) $str );
}

function sanitize_email( $email ) {
	$email = trim( (string) $email );

	return is_email( $email ) ? $email : '';
}

function is_email( $email ) {
	return (bool) preg_match( '/^[^\s@]+@[^\s@.]+\.[^\s@]+$/', (string) $email );
}

function sanitize_html_class( $class, $fallback = '' ) {
	$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );

	return '' === $class ? $fallback : $class;
}

function wp_strip_all_tags( $text, $remove_breaks = false ) {
	return trim( strip_tags( (string) $text ) );
}

/** Passthrough: the strings run through this are assembled in-plugin and already escaped. */
function wp_kses_post( $content ) {
	return (string) $content;
}

function wp_unslash( $value ) {
	return $value;
}

/**
 * add_query_arg(), array form and key/value form.
 *
 * @param mixed ...$args Either ( array $params, string $url ) or ( string $key, mixed $value, string $url ).
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$params = $args[0];
		$url    = (string) ( $args[1] ?? '' );
	} else {
		$params = array( $args[0] => $args[1] ?? '' );
		$url    = (string) ( $args[2] ?? '' );
	}

	$separator = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $separator . http_build_query( $params );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function wp_next_scheduled( $hook, $args = array() ) {
	foreach ( Basebelles_Test_State::$scheduled as $event ) {
		if ( $event['hook'] === $hook && $event['args'] === $args ) {
			return $event['timestamp'];
		}
	}

	return false;
}

function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	Basebelles_Test_State::$scheduled[] = array(
		'hook'      => $hook,
		'args'      => $args,
		'timestamp' => (int) $timestamp,
		'recurring' => false,
	);

	return true;
}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
	Basebelles_Test_State::$scheduled[] = array(
		'hook'      => $hook,
		'args'      => $args,
		'timestamp' => (int) $timestamp,
		'recurring' => $recurrence,
	);

	return true;
}

function wp_doing_cron() {
	return Basebelles_Test_State::$doing_cron;
}

function wp_remote_head( $url, $args = array() ) {
	Basebelles_Test_State::$http_requests[] = (string) $url;

	return Basebelles_Test_State::$http_response;
}

function wp_remote_retrieve_response_code( $response ) {
	if ( is_wp_error( $response ) || ! is_array( $response ) ) {
		return '';
	}

	return $response['response']['code'] ?? 0;
}

function current_user_can( $capability, ...$args ) {
	return Basebelles_Test_State::$user_can;
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return Basebelles_Test_State::$nonce_valid;
}

function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	return '';
}

function wp_is_post_revision( $post ) {
	return false;
}

function get_current_screen() {
	return Basebelles_Test_State::$screen;
}

function add_meta_box( ...$args ) {
	Basebelles_Test_State::$hooks['meta_boxes'][] = $args;
}

function add_submenu_page( ...$args ) {
	Basebelles_Test_State::$hooks['submenu_pages'][] = $args;

	return 'belles-page';
}

function selected( $selected, $current = true, $display = true ) {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';

	if ( $display ) {
		echo $result;
	}

	return $result;
}

function submit_button( $text = 'Save Changes', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
	echo '<button type="submit" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

function wp_safe_redirect( $location, $status = 302 ) {
	Basebelles_Test_State::$actions[] = array( 'wp_safe_redirect', array( $location ) );

	return true;
}

/** The plugin guards on function_exists(), so this always exists; the object can still be null. */
function wpforms() {
	return Basebelles_Test_State::$wpforms;
}

/** Minimal stand-in: the templates ask "is this an error?", the Belle code also reads the message. */
class WP_Error {

	public $code;

	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

/**
 * Minimal stand-in for WP_Post.
 *
 * A real class rather than a stdClass because the plugin type-checks what it is handed with
 * `instanceof WP_Post` before acting on it.
 */
class WP_Post {

	public $ID = 0;

	public $post_type = 'post';

	public $post_status = 'publish';

	public $post_title = '';

	public $post_author = 0;

	public function __construct( array $fields = array() ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
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

	/** @var bool What is_admin() reports. */
	public static $is_admin = false;

	/*
	 * ---------------------------------------------------------------------
	 * Belle directory state.
	 *
	 * The Belle code reads and writes posts, meta and options, so rather than special-casing
	 * each call the stubs below share one tiny in-memory post store. wp_insert_post() puts a
	 * post in it and get_posts()/get_post_meta() read back out of it, which keeps intake tests
	 * and directory tests talking about the same data.
	 * ---------------------------------------------------------------------
	 */

	/** @var array<int, object> Post ID => post object. */
	public static $posts = array();

	/** @var array<int, array<string, mixed>> Post ID => meta key => value. */
	public static $meta = array();

	/** @var int Next ID wp_insert_post() will hand out. */
	public static $next_post_id = 101;

	/** @var WP_Error|int|null Forced wp_insert_post() return, or null to insert normally. */
	public static $insert_post_result = null;

	/** @var array<string, mixed> Option name => value. */
	public static $options = array();

	/** @var array<string, callable> Filter tag => callback, for apply_filters(). */
	public static $filters = array();

	/** @var array<string, array> Hook tag => list of [callback, priority]. */
	public static $hooks = array();

	/** @var array<int, array{0: string, 1: array}> do_action() calls, in order. */
	public static $actions = array();

	/** @var array<string, array> Post type => registration args. */
	public static $post_types = array();

	/** @var array<int, array> wp_schedule_single_event()/wp_schedule_event() calls. */
	public static $scheduled = array();

	/** @var bool What wp_doing_cron() reports. */
	public static $doing_cron = false;

	/** @var string[] URLs passed to wp_remote_head(), in order. */
	public static $http_requests = array();

	/** @var array|WP_Error Response wp_remote_head() returns. */
	public static $http_response = array(
		'response' => array( 'code' => 200 ),
	);

	/** @var object|null What wpforms() returns; null stands in for WPForms being absent. */
	public static $wpforms = null;

	/** @var array|WP_Error Payload for Basebelles_API::get_guardians_roster(). */
	public static $roster = array();

	/** @var array<int, array> Form ID => form data array, as WPForms stores it. */
	public static $forms = array();

	/** @var array<int, array> Calls recorded by the fake form handler's update(). */
	public static $form_updates = array();

	/** @var bool When true the fake form handler's update() refuses the save. */
	public static $form_update_fails = false;

	/** @var bool What current_user_can() reports. */
	public static $user_can = true;

	/** @var bool What wp_verify_nonce() reports. */
	public static $nonce_valid = true;

	/** @var object|null What get_current_screen() returns. */
	public static $screen = null;

	public static function reset(): void {
		self::$fields            = array();
		self::$schedule          = array();
		self::$standings         = array();
		self::$live_feeds        = array();
		self::$live_feed_default = array();
		self::$enqueued_scripts  = array();
		self::$localized_scripts = array();
		self::$live_feed_calls   = array();
		self::$is_admin          = false;

		self::$posts              = array();
		self::$meta               = array();
		self::$next_post_id       = 101;
		self::$insert_post_result = null;
		self::$options            = array();
		self::$filters            = array();
		self::$hooks              = array();
		self::$actions            = array();
		self::$post_types         = array();
		self::$scheduled          = array();
		self::$doing_cron         = false;
		self::$http_requests      = array();
		self::$http_response      = array( 'response' => array( 'code' => 200 ) );
		self::$forms              = array();
		self::$form_updates       = array();
		self::$form_update_fails  = false;
		self::$user_can           = true;
		self::$nonce_valid        = true;
		self::$screen             = null;
		self::$wpforms            = new Basebelles_Test_WPForms();
		self::$roster             = array();
	}

	/**
	 * Put a post straight into the store, bypassing wp_insert_post().
	 *
	 * @param array $post Post fields.
	 * @param array $meta Meta key => value.
	 * @return int The new post ID.
	 */
	public static function add_post( array $post = array(), array $meta = array() ): int {
		$id = self::$next_post_id++;

		self::$posts[ $id ] = new WP_Post(
			array_merge(
				array(
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => '',
					'post_author' => 0,
				),
				$post,
				array( 'ID' => $id )
			)
		);

		self::$meta[ $id ] = $meta;

		return $id;
	}
}

/**
 * Fake WPForms container. `wpforms()->obj( 'form' )` is the accessor the plugin prefers, and
 * `->form` is the legacy property it falls back to, so both resolve to the same handler.
 */
class Basebelles_Test_WPForms {

	public $form;

	public function __construct() {
		$this->form = new Basebelles_Test_WPForms_Forms();
	}

	public function obj( $name ) {
		return 'form' === $name ? $this->form : null;
	}
}

/**
 * Fake WPForms form handler.
 *
 * update() mirrors the real one in the way that matters to the roster sync: it replaces the
 * whole stored form rather than merging, so a sync that drops a key really does lose it here.
 */
class Basebelles_Test_WPForms_Forms {

	public function get( $id, $args = array() ) {
		$form = Basebelles_Test_State::$forms[ (int) $id ] ?? false;

		if ( false === $form ) {
			return false;
		}

		return empty( $args['content_only'] ) ? (object) array( 'ID' => (int) $id ) : $form;
	}

	public function update( $id, $data = array(), $args = array() ) {
		if ( Basebelles_Test_State::$form_update_fails ) {
			return false;
		}

		Basebelles_Test_State::$forms[ (int) $id ] = $data;

		Basebelles_Test_State::$form_updates[] = array(
			'id'   => (int) $id,
			'data' => $data,
			'args' => $args,
		);

		return (int) $id;
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

	public function get_guardians_roster( $roster_type = 'active', $season_year = null ) {
		return Basebelles_Test_State::$roster;
	}
}

/** The today-game template reads Basebelles::$version when enqueueing its script. */
class Basebelles {

	public static $version = '0.0.0-test';
}

require_once BASEBELLES_PLUGIN_DIR . '/blocks/today-game/class-panels.php';

// Requiring this constructs the singleton, which registers hooks and the post type against the
// stubs above. is_admin() is false at bootstrap, so nothing gets scheduled.
require_once BASEBELLES_PLUGIN_DIR . '/features/class-belles.php';

require_once BASEBELLES_TESTS_DIR . '/Fixtures.php';
