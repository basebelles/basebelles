<?php
/**
 * Belle directory: WPForms intake, moderation queue, and stored member data.
 *
 * There are no user accounts here. A submission becomes a `belle` post in `pending` status;
 * an admin publishes it (approve) or trashes it (spam). Only published Belles render on the
 * front end.
 *
 * Email addresses live in protected meta and the raw address is never output. What does reach
 * the page is a SHA-256 hash of it, inside a Gravatar URL, and only for Belles who actually
 * have a Gravatar. That hash is not anonymization — like any email hash it can be confirmed by
 * guessing an address and hashing it — so treat it as the same exposure WordPress core accepts
 * when it renders commenter avatars, not as a privacy guarantee.
 *
 * @package Base*Belles
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Belles {

	/** Post type holding one approved-or-pending Belle each. */
	const POST_TYPE = 'belle';

	/** Protected meta keys. Underscore-prefixed so core treats them as protected. */
	const META_EMAIL      = '_bb_belle_email';
	const META_EMAIL_HASH = '_bb_belle_email_hash';
	const META_LOCATION   = '_bb_belle_location';
	const META_CURRENT    = '_bb_belle_current_players';
	const META_HISTORICAL = '_bb_belle_historical_player';
	const META_GRAVATAR   = '_bb_belle_has_gravatar';
	const META_USER_ID    = '_bb_belle_user_id';
	const META_ENTRY_ID   = '_bb_belle_entry_id';
	const META_DUPLICATE  = '_bb_belle_duplicate';

	/** Options set on the Belles → Form Settings screen. */
	const OPTION_FORM_ID      = 'bb_belles_form_id';
	const OPTION_ROSTER_FIELD = 'bb_belles_roster_field_id';
	const OPTION_ROSTER_MAP   = 'bb_belles_roster_map';
	const OPTION_SYNC_STATUS  = 'bb_belles_sync_status';

	/** Daily roster → form choices sync. */
	const CRON_HOOK = 'bb_belles_sync_roster';

	/** Deferred, single-event Gravatar lookup so publishing never waits on gravatar.com. */
	const CRON_GRAVATAR = 'bb_belles_check_gravatar';

	/** How many current players a Belle may pick. Mirrored into the form's choice_limit. */
	const CURRENT_PLAYER_LIMIT = 3;

	/**
	 * Singleton instance.
	 *
	 * @var Basebelles_Belles|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Basebelles_Belles
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->register_post_type();

		// Intake.
		add_action( 'wpforms_process_complete', array( $this, 'create_belle_from_entry' ), 10, 4 );

		// Admin.
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_setup_notice' ) );

		// Avatar presence is resolved once, at approval, rather than on every page view.
		add_action( 'transition_post_status', array( $this, 'maybe_refresh_gravatar_flag' ), 10, 3 );

		// Roster sync.
		add_action( self::CRON_HOOK, array( $this, 'sync_roster_choices' ) );
		add_action( self::CRON_GRAVATAR, array( $this, 'refresh_gravatar_flag' ) );
		$this->maybe_schedule_cron();
	}

	/**
	 * Register the Belle post type.
	 *
	 * Deliberately not publicly queryable: Belles are cards in a directory block, not pages.
	 * `show_in_rest` stays false so stored email addresses cannot leak through the REST API.
	 *
	 * @return void
	 */
	private function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => 'Belles',
					'singular_name'      => 'Belle',
					'add_new_item'       => 'Add New Belle',
					'edit_item'          => 'Edit Belle',
					'new_item'           => 'New Belle',
					'view_item'          => 'View Belle',
					'search_items'       => 'Search Belles',
					'not_found'          => 'No Belles found',
					'not_found_in_trash' => 'No Belles found in Trash',
					'all_items'          => 'All Belles',
					'menu_name'          => 'Belles',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'menu_icon'           => 'dashicons-groups',
				'menu_position'       => 26,
				'supports'            => array( 'title' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Intake
	 * ------------------------------------------------------------------ */

	/**
	 * Turn a completed WPForms entry into a pending Belle.
	 *
	 * @param array $fields    Sanitized field values keyed by field ID.
	 * @param array $entry     Raw entry payload.
	 * @param array $form_data Processed form settings.
	 * @param int   $entry_id  Stored entry ID, or 0 when entry storage is off.
	 * @return void
	 */
	public function create_belle_from_entry( $fields, $entry, $form_data, $entry_id = 0 ) {
		unset( $entry );

		$form_id = (int) get_option( self::OPTION_FORM_ID, 0 );
		$this_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;

		if ( ! $form_id || $form_id !== $this_id ) {
			return;
		}

		$values = $this->map_entry_fields( $fields );

		// An email address is the one thing the directory cannot work without: it is both the
		// avatar source and the deduplication key.
		if ( empty( $values['email'] ) || ! is_email( $values['email'] ) ) {
			return;
		}

		$email = sanitize_email( $values['email'] );
		$name  = '' !== $values['name'] ? $values['name'] : ucfirst( strstr( $email, '@', true ) );
		$hash  = hash( 'sha256', strtolower( trim( $email ) ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $name,
				'post_status' => 'pending',
				'post_author' => 0,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		update_post_meta( $post_id, self::META_EMAIL, $email );
		update_post_meta( $post_id, self::META_EMAIL_HASH, $hash );
		update_post_meta( $post_id, self::META_LOCATION, $values['location'] );
		update_post_meta( $post_id, self::META_CURRENT, $this->resolve_player_ids( $values['current_players'] ) );
		update_post_meta( $post_id, self::META_HISTORICAL, $values['historical_player'] );
		update_post_meta( $post_id, self::META_ENTRY_ID, (int) $entry_id );
		update_post_meta( $post_id, self::META_USER_ID, 0 );

		// Flagged rather than dropped: a repeat submission is usually someone correcting a typo,
		// and that is a judgement call for the moderation queue, not for this function.
		if ( $this->find_existing_belle( $hash, $post_id ) ) {
			update_post_meta( $post_id, self::META_DUPLICATE, 1 );
		}

		/**
		 * Fires after a form submission has been stored as a pending Belle.
		 *
		 * @param int   $post_id The new Belle post ID.
		 * @param array $values  Normalized submission values.
		 */
		do_action( 'basebelles_belle_created', $post_id, $values );
	}

	/**
	 * Normalize a WPForms entry into the values the directory stores.
	 *
	 * Fields are matched on their labels so the form stays editable in the WPForms builder
	 * without touching code. Sites that would rather pin exact field IDs can return a map of
	 * `key => field_id` from the `basebelles_belles_field_map` filter.
	 *
	 * @param array $fields Sanitized field values keyed by field ID.
	 * @return array
	 */
	private function map_entry_fields( $fields ) {
		$values = array(
			'name'              => '',
			'email'             => '',
			'location'          => '',
			'current_players'   => array(),
			'historical_player' => '',
		);

		/**
		 * Filter the explicit field-ID map, bypassing label matching.
		 *
		 * @param array $map    Map of value key => WPForms field ID.
		 * @param array $fields The submitted fields.
		 */
		$map = (array) apply_filters( 'basebelles_belles_field_map', array(), $fields );

		foreach ( $fields as $field_id => $field ) {
			$key = $this->match_field_key( $field_id, $field, $map );

			if ( ! $key || ! isset( $values[ $key ] ) ) {
				continue;
			}

			// Most WPForms field types hand back a string; a few (uploads, some layouts) hand
			// back an array, and casting that would store the literal word "Array".
			$value = isset( $field['value'] ) ? $field['value'] : '';
			$raw   = is_array( $value ) ? implode( "\n", array_filter( $value, 'is_scalar' ) ) : (string) $value;

			if ( 'current_players' === $key ) {
				// Checkbox and multi-select values arrive newline-joined.
				$choices        = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
				$values[ $key ] = array_slice( array_values( $choices ), 0, self::CURRENT_PLAYER_LIMIT );
				continue;
			}

			$values[ $key ] = sanitize_text_field( $raw );
		}

		return $values;
	}

	/**
	 * Work out which stored value a submitted field corresponds to.
	 *
	 * @param int|string $field_id The WPForms field ID.
	 * @param array      $field    The submitted field.
	 * @param array      $map      Explicit key => field ID overrides.
	 * @return string Value key, or an empty string when the field is not one we store.
	 */
	private function match_field_key( $field_id, $field, $map ) {
		foreach ( $map as $key => $mapped_id ) {
			if ( (string) $mapped_id === (string) $field_id ) {
				return (string) $key;
			}
		}

		$label = isset( $field['name'] ) ? strtolower( (string) $field['name'] ) : '';
		$label = preg_replace( '/[^a-z0-9]/', '', $label );

		if ( '' === $label ) {
			return '';
		}

		// Order matters. Location is tested before the player fields so a label like "Current
		// City" is not claimed by the "current" test, and the name field is tested last so it
		// does not swallow "Favorite Player".
		if ( false !== strpos( $label, 'email' ) ) {
			return 'email';
		}

		$location_words = array( 'location', 'whereareyou', 'city', 'hometown', 'wherefrom' );

		foreach ( $location_words as $word ) {
			if ( false !== strpos( $label, $word ) ) {
				return 'location';
			}
		}

		if ( false !== strpos( $label, 'current' ) ) {
			return 'current_players';
		}

		if ( false !== strpos( $label, 'historical' ) || false !== strpos( $label, 'alltime' ) || false !== strpos( $label, 'retired' ) ) {
			return 'historical_player';
		}

		if ( false !== strpos( $label, 'name' ) && false === strpos( $label, 'player' ) ) {
			return 'name';
		}

		return '';
	}

	/**
	 * Attach MLB player IDs to submitted player names using the cached roster map.
	 *
	 * @param string[] $names Submitted player names.
	 * @return array[] List of arrays with name and id keys.
	 */
	private function resolve_player_ids( $names ) {
		$roster_map = (array) get_option( self::OPTION_ROSTER_MAP, array() );
		$players    = array();

		foreach ( $names as $name ) {
			$name = sanitize_text_field( $name );

			if ( '' === $name ) {
				continue;
			}

			$lookup    = strtolower( $name );
			$players[] = array(
				'name' => $name,
				'id'   => isset( $roster_map[ $lookup ] ) ? (int) $roster_map[ $lookup ] : 0,
			);
		}

		return $players;
	}

	/**
	 * Find another Belle already using the same email hash.
	 *
	 * @param string $hash    SHA-256 email hash.
	 * @param int    $exclude Post ID to ignore.
	 * @return int Matching post ID, or 0.
	 */
	private function find_existing_belle( $hash, $exclude = 0 ) {
		$found = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'pending', 'draft' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'exclude'          => array( (int) $exclude ),
				'no_found_rows'    => true,
				'suppress_filters' => false,
				// phpcs:disable WordPress.DB.SlowDBQuery
				'meta_key'         => self::META_EMAIL_HASH,
				'meta_value'       => $hash,
				// phpcs:enable WordPress.DB.SlowDBQuery
			)
		);

		return empty( $found ) ? 0 : (int) $found[0];
	}

	/* ---------------------------------------------------------------------
	 * Avatars
	 * ------------------------------------------------------------------ */

	/**
	 * Build the Gravatar URL for a hashed email.
	 *
	 * @param string $hash SHA-256 email hash.
	 * @param int    $size Requested pixel size.
	 * @param string $default_image Gravatar `d` parameter.
	 * @return string
	 */
	public static function gravatar_url( $hash, $size = 160, $default_image = 'blank' ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $hash ) ) {
			return '';
		}

		return add_query_arg(
			array(
				's' => (int) $size,
				'd' => $default_image,
			),
			'https://gravatar.com/avatar/' . $hash
		);
	}

	/**
	 * Refresh the cached "does this person have a Gravatar" flag when a Belle is approved.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       The post.
	 * @return void
	 */
	public function maybe_refresh_gravatar_flag( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status || $new_status === $old_status ) {
			return;
		}

		$this->queue_gravatar_check( $post->ID );
	}

	/**
	 * Queue the Gravatar lookup for a Belle rather than blocking the save.
	 *
	 * @param int $post_id Belle post ID.
	 * @return void
	 */
	private function queue_gravatar_check( $post_id ) {
		$post_id = (int) $post_id;

		if ( wp_next_scheduled( self::CRON_GRAVATAR, array( $post_id ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 10, self::CRON_GRAVATAR, array( $post_id ) );
	}

	/**
	 * Ask Gravatar once whether an avatar exists, and cache the answer in post meta.
	 *
	 * `d=404` makes Gravatar return a 404 instead of a generated placeholder, which is the only
	 * reliable way to tell "has an avatar" from "has a fallback".
	 *
	 * @param int $post_id Belle post ID.
	 * @return bool
	 */
	public function refresh_gravatar_flag( $post_id ) {
		$hash = (string) get_post_meta( $post_id, self::META_EMAIL_HASH, true );

		if ( '' === $hash ) {
			update_post_meta( $post_id, self::META_GRAVATAR, 0 );
			return false;
		}

		$response = wp_remote_head(
			self::gravatar_url( $hash, 160, '404' ),
			array(
				'timeout'    => 5,
				'user-agent' => 'Basebelles/' . Basebelles::$version . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Leave whatever we already knew rather than downgrading on a transient network error.
			return (bool) get_post_meta( $post_id, self::META_GRAVATAR, true );
		}

		$has_avatar = 200 === (int) wp_remote_retrieve_response_code( $response );
		update_post_meta( $post_id, self::META_GRAVATAR, $has_avatar ? 1 : 0 );

		return $has_avatar;
	}

	/**
	 * Deterministic initials for the monogram fallback.
	 *
	 * @param string $name Display name.
	 * @return string One or two uppercase characters.
	 */
	public static function get_initials( $name ) {
		$name  = trim( wp_strip_all_tags( (string) $name ) );
		$parts = preg_split( '/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $parts ) ) {
			return '?';
		}

		$first    = mb_substr( $parts[0], 0, 1 );
		$last     = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		$initials = $first . $last;

		// WordPress polyfills mb_substr but not mb_strtoupper, so guard the uppercase step.
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials ) : strtoupper( $initials );
	}

	/**
	 * Pick a stable monogram background from the team palette.
	 *
	 * @param string $seed Any stable string, normally the Belle's name.
	 * @return string CSS custom property value.
	 */
	public static function get_monogram_color( $seed ) {
		$palette = array(
			'var(--bb-deep-navy)',
			'var(--bb-wine-red)',
			'var(--bb-slate-blue)',
			'var(--bb-accent-slate)',
		);

		$index = abs( crc32( (string) $seed ) ) % count( $palette );

		return $palette[ $index ];
	}

	/**
	 * Get published Belles, shaped for the directory block.
	 *
	 * @param string $orderby Either `title` or `date`.
	 * @return array[]
	 */
	public static function get_published_belles( $orderby = 'title' ) {
		$orderby = in_array( $orderby, array( 'title', 'date' ), true ) ? $orderby : 'title';

		/**
		 * Filter how many Belles the directory will render at once.
		 *
		 * @param int $limit Maximum number of published Belles to list.
		 */
		$limit = (int) apply_filters( 'basebelles_belles_display_limit', 500 );

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => $orderby,
				'order'          => 'title' === $orderby ? 'ASC' : 'DESC',
				'no_found_rows'  => true,
			)
		);

		$belles = array();

		foreach ( $posts as $post ) {
			$current = get_post_meta( $post->ID, self::META_CURRENT, true );
			$hash    = (string) get_post_meta( $post->ID, self::META_EMAIL_HASH, true );

			$belles[] = array(
				'id'         => $post->ID,
				'name'       => $post->post_title,
				'location'   => (string) get_post_meta( $post->ID, self::META_LOCATION, true ),
				'current'    => is_array( $current ) ? $current : array(),
				'historical' => (string) get_post_meta( $post->ID, self::META_HISTORICAL, true ),
				'gravatar'   => get_post_meta( $post->ID, self::META_GRAVATAR, true ) ? self::gravatar_url( $hash, 160, 'blank' ) : '',
			);
		}

		return $belles;
	}

	/* ---------------------------------------------------------------------
	 * Roster sync
	 * ------------------------------------------------------------------ */

	/**
	 * Make sure the daily roster sync is scheduled.
	 *
	 * @return void
	 */
	private function maybe_schedule_cron() {
		// No need to spend this lookup on every front-end request.
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Run the roster sync and record the outcome.
	 *
	 * A daily sync that has been quietly failing looks exactly like one that is working, so the
	 * result is stored where the settings screen and the Belles list can surface it.
	 *
	 * @return true|WP_Error
	 */
	public function sync_roster_choices() {
		$result = $this->run_roster_sync();

		update_option(
			self::OPTION_SYNC_STATUS,
			array(
				'time'    => time(),
				'ok'      => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : '',
			),
			false
		);

		return $result;
	}

	/**
	 * Write the current Guardians roster into the form's checkbox choices.
	 *
	 * Choices have to live in the saved form, not be filtered in at render time: WPForms
	 * validates submitted choices against an allowlist read straight from the stored form, so
	 * render-only choices would display fine and then fail on submit.
	 *
	 * @return true|WP_Error
	 */
	private function run_roster_sync() {
		$form_id  = (int) get_option( self::OPTION_FORM_ID, 0 );
		$field_id = (string) get_option( self::OPTION_ROSTER_FIELD, '' );

		if ( ! $form_id || '' === $field_id ) {
			return new WP_Error( 'basebelles_belles_unconfigured', 'Pick a form and a roster field on the Belles → Form Settings screen first.' );
		}

		$handler = $this->get_form_handler();

		if ( ! $handler ) {
			return new WP_Error( 'basebelles_belles_no_wpforms', 'WPForms is not available.' );
		}

		$roster = Basebelles_API::get_instance()->get_guardians_roster();

		if ( is_wp_error( $roster ) ) {
			return $roster;
		}

		$form_data = $handler->get( $form_id, array(
				'content_only' => true,
				'cap'          => false,
			) );

		if ( empty( $form_data['fields'][ $field_id ] ) ) {
			return new WP_Error( 'basebelles_belles_no_field', 'The configured roster field no longer exists on that form.' );
		}

		// Choice keys are 1-indexed in WPForms' own defaults; matching that avoids surprises in
		// the form builder. With `show_values` off, the label is also the submitted value.
		$choices    = array();
		$roster_map = array();
		$index      = 1;

		foreach ( $roster as $player ) {
			$choices[ $index ] = array(
				'label' => $player['name'],
				'value' => '',
				'image' => '',
			);

			$roster_map[ strtolower( $player['name'] ) ] = (int) $player['id'];
			++$index;
		}

		$form_data['fields'][ $field_id ]['choices'] = $choices;

		if ( empty( $form_data['fields'][ $field_id ]['choice_limit'] ) ) {
			$form_data['fields'][ $field_id ]['choice_limit'] = self::CURRENT_PLAYER_LIMIT;
		}

		// update() replaces the whole form, which is why the array above is a full read-modify-write.
		$updated = $handler->update( $form_id, $form_data, array( 'cap' => false ) );

		if ( ! $updated ) {
			return new WP_Error( 'basebelles_belles_update_failed', 'WPForms refused to save the updated form.' );
		}

		update_option( self::OPTION_ROSTER_MAP, $roster_map, false );

		return true;
	}

	/**
	 * Get the WPForms form handler, preferring the current accessor.
	 *
	 * @return object|null
	 */
	private function get_form_handler() {
		if ( ! function_exists( 'wpforms' ) ) {
			return null;
		}

		$wpforms = wpforms();

		if ( is_object( $wpforms ) && method_exists( $wpforms, 'obj' ) ) {
			$handler = $wpforms->obj( 'form' );

			if ( is_object( $handler ) ) {
				return $handler;
			}
		}

		return isset( $wpforms->form ) && is_object( $wpforms->form ) ? $wpforms->form : null;
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	/**
	 * Add the Form Settings submenu under Belles.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			'Belle Form Settings',
			'Form Settings',
			'manage_options',
			'bb-belles-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Nudge an admin who has not connected a form yet.
	 *
	 * @return void
	 */
	public function admin_setup_notice() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings_url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=bb-belles-settings' );

		if ( ! (int) get_option( self::OPTION_FORM_ID, 0 ) ) {
			printf(
				'<div class="notice notice-warning"><p>No WPForms form is connected yet, so submissions will not become Belles. <a href="%s">Choose a form</a></p></div>',
				esc_url( $settings_url )
			);

			return;
		}

		$status = $this->get_sync_status();

		if ( ! $status['ok'] && '' !== $status['message'] ) {
			printf(
				'<div class="notice notice-warning"><p>The Belle roster sync is failing: %s <a href="%s">Check settings</a></p></div>',
				esc_html( $status['message'] ),
				esc_url( $settings_url )
			);
		}
	}

	/**
	 * Get the stored outcome of the last roster sync.
	 *
	 * @return array Array with time, ok and message keys.
	 */
	private function get_sync_status() {
		$status = get_option( self::OPTION_SYNC_STATUS, array() );

		return array(
			'time'    => isset( $status['time'] ) ? (int) $status['time'] : 0,
			'ok'      => isset( $status['ok'] ) ? (bool) $status['ok'] : true,
			'message' => isset( $status['message'] ) ? (string) $status['message'] : '',
		);
	}

	/**
	 * Save the settings screen, and run an on-demand roster sync when asked.
	 *
	 * @return void
	 */
	public function handle_settings_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is read and verified immediately below.
		if ( empty( $_POST['bb_belles_settings_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bb_belles_settings_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'bb_belles_settings' ) ) {
			return;
		}

		$form_id  = isset( $_POST['bb_belles_form_id'] ) ? (int) $_POST['bb_belles_form_id'] : 0;
		$field_id = isset( $_POST['bb_belles_roster_field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['bb_belles_roster_field_id'] ) ) : '';

		update_option( self::OPTION_FORM_ID, $form_id, false );
		update_option( self::OPTION_ROSTER_FIELD, $field_id, false );

		$redirect = add_query_arg(
			'bb-belles-updated',
			'1',
			admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=bb-belles-settings' )
		);

		// Only a fixed flag travels in the URL. The message itself is read back from the stored
		// sync status, so an arbitrary URL cannot put text into an admin notice.
		if ( ! empty( $_POST['bb_belles_sync_now'] ) ) {
			$result   = $this->sync_roster_choices();
			$redirect = add_query_arg( 'bb-belles-sync', is_wp_error( $result ) ? 'error' : 'ok', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$form_id  = (int) get_option( self::OPTION_FORM_ID, 0 );
		$field_id = (string) get_option( self::OPTION_ROSTER_FIELD, '' );
		$forms    = get_posts(
			array(
				'post_type'      => 'wpforms',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sync = isset( $_GET['bb-belles-sync'] ) ? sanitize_text_field( wp_unslash( $_GET['bb-belles-sync'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved  = ! empty( $_GET['bb-belles-updated'] );
		$status = $this->get_sync_status();

		echo '<div class="wrap">';
		echo '<h1>Belle Form Settings</h1>';

		if ( 'ok' === $sync ) {
			echo '<div class="notice notice-success"><p>Roster synced into the form.</p></div>';
		} elseif ( 'error' === $sync ) {
			echo '<div class="notice notice-error"><p>Roster sync failed: ' . esc_html( $status['message'] ) . '</p></div>';
		} elseif ( $saved ) {
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		if ( '' === $sync && ! $status['ok'] && '' !== $status['message'] ) {
			echo '<div class="notice notice-warning"><p>The last roster sync failed: ' . esc_html( $status['message'] ) . '</p></div>';
		}

		if ( empty( $forms ) ) {
			echo '<p>No WPForms forms were found. Build the sign-up form first.</p></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=bb-belles-settings' ) ) . '">';
		wp_nonce_field( 'bb_belles_settings', 'bb_belles_settings_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="bb_belles_form_id">Sign-up form</label></th><td>';
		echo '<select name="bb_belles_form_id" id="bb_belles_form_id">';
		echo '<option value="0">— None —</option>';
		foreach ( $forms as $form ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $form->ID,
				selected( $form_id, (int) $form->ID, false ),
				esc_html( $form->post_title )
			);
		}
		echo '</select>';
		echo '<p class="description">Submissions to this form become Belles awaiting review.</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="bb_belles_roster_field_id">Current-player field</label></th><td>';
		echo '<select name="bb_belles_roster_field_id" id="bb_belles_roster_field_id">';
		echo '<option value="">— None —</option>';
		foreach ( $this->get_choice_fields( $form_id ) as $id => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $id ),
				selected( $field_id, (string) $id, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">The Checkboxes field whose choices get replaced with the active roster each day. Save the form choice first, then reload this screen to pick a field.</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( 'Save Settings', 'primary', 'submit', false );
		echo ' ';
		submit_button( 'Save and Sync Roster Now', 'secondary', 'bb_belles_sync_now', false );
		echo '</form></div>';
	}

	/**
	 * List the checkbox fields on a form, for the roster-field picker.
	 *
	 * @param int $form_id WPForms form ID.
	 * @return array Map of field ID => label.
	 */
	private function get_choice_fields( $form_id ) {
		$handler = $this->get_form_handler();

		if ( ! $form_id || ! $handler ) {
			return array();
		}

		$form_data = $handler->get( (int) $form_id, array(
				'content_only' => true,
				'cap'          => false,
			) );

		if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return array();
		}

		$fields = array();

		foreach ( $form_data['fields'] as $id => $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';

			if ( ! in_array( $type, array( 'checkbox', 'select', 'radio' ), true ) ) {
				continue;
			}

			$label         = isset( $field['label'] ) && '' !== $field['label'] ? (string) $field['label'] : 'Field ' . $id;
			$fields[ $id ] = $label . ' (' . $type . ')';
		}

		return $fields;
	}

	/**
	 * Add the Belle detail meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'bb_belle_details',
			'Belle Details',
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Belle detail meta box.
	 *
	 * @param WP_Post $post The post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'bb_belle_details', 'bb_belle_details_nonce' );

		$email      = (string) get_post_meta( $post->ID, self::META_EMAIL, true );
		$location   = (string) get_post_meta( $post->ID, self::META_LOCATION, true );
		$historical = (string) get_post_meta( $post->ID, self::META_HISTORICAL, true );
		$current    = get_post_meta( $post->ID, self::META_CURRENT, true );
		$current    = is_array( $current ) ? $current : array();
		$names      = wp_list_pluck( $current, 'name' );

		if ( get_post_meta( $post->ID, self::META_DUPLICATE, true ) ) {
			echo '<div class="notice notice-warning inline"><p><strong>Possible duplicate:</strong> another Belle already uses this email address.</p></div>';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="bb_belle_email">Email</label></th><td>';
		printf( '<input type="email" class="regular-text" id="bb_belle_email" name="bb_belle_email" value="%s" />', esc_attr( $email ) );
		echo '<p class="description">Stored privately and used only to look up a Gravatar. Never shown on the site.</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="bb_belle_location">Location</label></th><td>';
		printf( '<input type="text" class="regular-text" id="bb_belle_location" name="bb_belle_location" value="%s" />', esc_attr( $location ) );
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="bb_belle_current">Favorite current players</label></th><td>';
		printf(
			'<input type="text" class="large-text" id="bb_belle_current" name="bb_belle_current" value="%s" />',
			esc_attr( implode( ', ', array_filter( (array) $names ) ) )
		);
		echo '<p class="description">Comma separated. Editing here clears the stored MLB player IDs for any name that is not on the current roster.</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="bb_belle_historical">Favorite historical player</label></th><td>';
		printf( '<input type="text" class="regular-text" id="bb_belle_historical" name="bb_belle_historical" value="%s" />', esc_attr( $historical ) );
		echo '</td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Save the Belle detail meta box.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post.
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ) {
		unset( $post );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is read and verified immediately below.
		if ( empty( $_POST['bb_belle_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bb_belle_details_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'bb_belle_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$email = isset( $_POST['bb_belle_email'] ) ? sanitize_email( wp_unslash( $_POST['bb_belle_email'] ) ) : '';
		$old   = (string) get_post_meta( $post_id, self::META_EMAIL, true );

		if ( '' !== $email && is_email( $email ) ) {
			$hash = hash( 'sha256', strtolower( trim( $email ) ) );

			update_post_meta( $post_id, self::META_EMAIL, $email );
			update_post_meta( $post_id, self::META_EMAIL_HASH, $hash );

			// Re-check the duplicate flag: correcting a typo'd address is exactly why a
			// moderator would edit this field, and the warning has to be able to go away.
			if ( $this->find_existing_belle( $hash, $post_id ) ) {
				update_post_meta( $post_id, self::META_DUPLICATE, 1 );
			} else {
				delete_post_meta( $post_id, self::META_DUPLICATE );
			}

			if ( $email !== $old ) {
				$this->queue_gravatar_check( $post_id );
			}
		}

		if ( isset( $_POST['bb_belle_location'] ) ) {
			update_post_meta( $post_id, self::META_LOCATION, sanitize_text_field( wp_unslash( $_POST['bb_belle_location'] ) ) );
		}

		if ( isset( $_POST['bb_belle_historical'] ) ) {
			update_post_meta( $post_id, self::META_HISTORICAL, sanitize_text_field( wp_unslash( $_POST['bb_belle_historical'] ) ) );
		}

		if ( isset( $_POST['bb_belle_current'] ) ) {
			$names = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['bb_belle_current'] ) ) ) ) );
			update_post_meta( $post_id, self::META_CURRENT, $this->resolve_player_ids( array_slice( array_values( $names ), 0, self::CURRENT_PLAYER_LIMIT ) ) );
		}
	}

	/**
	 * Define the Belles list table columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['bb_avatar'] = 'Avatar';
				$new[ $key ]      = 'Belle';
				continue;
			}

			if ( 'date' === $key ) {
				$new['bb_location'] = 'Location';
				$new['bb_faves']    = 'Favorites';
				$new[ $key ]        = 'Submitted';
				continue;
			}

			$new[ $key ] = $label;
		}

		return $new;
	}

	/**
	 * Render the custom Belles list table columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'bb_avatar':
				$hash = (string) get_post_meta( $post_id, self::META_EMAIL_HASH, true );
				$url  = self::gravatar_url( $hash, 48, 'mp' );

				if ( '' === $url ) {
					echo '—';
					break;
				}

				printf(
					'<img src="%s" width="40" height="40" alt="" style="border-radius:50%%;" />',
					esc_url( $url )
				);
				break;

			case 'bb_location':
				$location = (string) get_post_meta( $post_id, self::META_LOCATION, true );
				echo '' === $location ? '—' : esc_html( $location );
				break;

			case 'bb_faves':
				$current    = get_post_meta( $post_id, self::META_CURRENT, true );
				$current    = is_array( $current ) ? array_filter( (array) wp_list_pluck( $current, 'name' ) ) : array();
				$historical = (string) get_post_meta( $post_id, self::META_HISTORICAL, true );

				if ( empty( $current ) && '' === $historical ) {
					echo '—';
					break;
				}

				if ( ! empty( $current ) ) {
					echo esc_html( implode( ', ', $current ) );
				}

				if ( '' !== $historical ) {
					echo '<br /><em>' . esc_html( $historical ) . '</em>';
				}
				break;
		}
	}
}

Basebelles_Belles::get_instance();
