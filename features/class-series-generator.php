<?php
/**
 * Series Generator options sub-page.
 *
 * Registers a "Series Generator" sub-page under Guardians Settings and hooks
 * into acf/save_post to create a draft series post when the trigger field is checked.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Series_Generator {

	const OPTIONS_POST_ID  = 'series_generator';
	const NOTICE_TRANSIENT = 'bb_series_gen_notice';

	public function __construct() {
		$this->register_options_page();
		add_action( 'acf/save_post', array( $this, 'maybe_generate_series_post' ), 20 );
		add_action( 'admin_notices', array( $this, 'maybe_show_notice' ) );
	}

	/**
	 * Register the Series Generator sub-page under Guardians Settings.
	 */
	public function register_options_page() {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		acf_add_options_sub_page(
			array(
				'page_title'  => 'Series Generator',
				'menu_title'  => 'Series Generator',
				'parent_slug' => 'guardians-settings',
				'menu_slug'   => 'series-generator',
				'post_id'     => self::OPTIONS_POST_ID,
				'capability'  => 'edit_posts',
			)
		);
	}

	/**
	 * Fire when the Series Generator options page is saved.
	 *
	 * If the generate trigger field is checked, create the draft post and reset the field.
	 *
	 * @param string|int $post_id ACF save context identifier.
	 */
	public function maybe_generate_series_post( $post_id ) {
		if ( self::OPTIONS_POST_ID !== $post_id ) {
			return;
		}

		if ( ! get_field( 'sgen_generate', self::OPTIONS_POST_ID ) ) {
			return;
		}

		// Reset the trigger before doing anything so a failed run can't re-fire on refresh.
		update_field( 'field_sgen_generate', 0, self::OPTIONS_POST_ID );

		$opponent    = get_field( 'sgen_opponent', self::OPTIONS_POST_ID ) ? get_field( 'sgen_opponent', self::OPTIONS_POST_ID ) : 'tbd';
		$date        = get_field( 'sgen_date', self::OPTIONS_POST_ID ) ? get_field( 'sgen_date', self::OPTIONS_POST_ID ) : '';
		$end_date    = get_field( 'sgen_end_date', self::OPTIONS_POST_ID ) ? get_field( 'sgen_end_date', self::OPTIONS_POST_ID ) : '';
		$game_count  = (int) ( get_field( 'sgen_game_count', self::OPTIONS_POST_ID ) ? get_field( 'sgen_game_count', self::OPTIONS_POST_ID ) : 3 );
		$game_number = (int) ( get_field( 'sgen_game_number', self::OPTIONS_POST_ID ) ? get_field( 'sgen_game_number', self::OPTIONS_POST_ID ) : 1 );
		$is_home     = get_field( 'sgen_home_away', self::OPTIONS_POST_ID ) ? '1' : '0';
		$season_type = get_field( 'sgen_season_type', self::OPTIONS_POST_ID ) ? get_field( 'sgen_season_type', self::OPTIONS_POST_ID ) : 'regular-season';

		// Look up the team taxonomy term slug via the API class.
		$team_term = '';
		if ( class_exists( 'Basebelles_API' ) ) {
			$team_data = Basebelles_API::get_instance()->get_team( $opponent );
			$team_term = $team_data['taxonomy_term'] ?? '';
		}

		if ( empty( $date ) || false === strtotime( $date ) ) {
			$this->set_notice( 'error', 'Please set a valid series start date before generating.' );
			return;
		}

		// Fall back to start date if end date is blank or un-parseable.
		if ( empty( $end_date ) || false === strtotime( $end_date ) ) {
			$end_date = $date;
		}

		$field_obj             = get_field_object( 'field_sgen_opponent', self::OPTIONS_POST_ID );
		$choices               = ! empty( $field_obj['choices'] ) ? $field_obj['choices'] : array();
		$opponent_display_name = isset( $choices[ $opponent ] )
			? $choices[ $opponent ]
			: strtoupper( str_replace( '-', ' ', $opponent ) );

		$post_date = wp_date( 'F j, Y', strtotime( $date ) );

		$post_content = $this->pattern_content(
			'game-series-header',
			array(
				'"field_69c9b65a0d4e8":"0"'        => '"field_69c9b65a0d4e8":"' . $is_home . '"',
				'"field_69caebbe128f2":"20260520"' => '"field_69caebbe128f2":"' . $date . '"',
				'"field_69caebf0128f3":"20260520"' => '"field_69caebf0128f3":"' . $end_date . '"',
				'"field_69c9b6120d4e7":"tbd"'      => '"field_69c9b6120d4e7":"' . $opponent . '"',
			)
		) . "\n\n";

		for ( $i = 1; $i <= $game_count; $i++ ) {
			$season_game   = $game_number + ( $i - 1 );
			$post_content .= $this->game_section( $i, $opponent, $is_home, $season_game );
		}

		$post_content .= $this->pattern_content( 'game-series-end' );

		$new_post_id = wp_insert_post(
			array(
				'post_title'   => sprintf( 'Series vs %s - %s', $opponent_display_name, $post_date ),
				'post_content' => $post_content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
				'tags_input'   => array( sprintf( '%d game series', $game_count ) ),
			)
		);

		if ( ! is_wp_error( $new_post_id ) ) {
			if ( $team_term ) {
				wp_set_object_terms( $new_post_id, $team_term, 'team' );
			}
			wp_set_object_terms( $new_post_id, $season_type, 'season-type' );
			wp_set_object_terms( $new_post_id, '1' === $is_home ? 'home' : 'away', 'venue-type' );
			wp_set_object_terms( $new_post_id, 'games', 'category' );

			$this->set_notice(
				'success',
				sprintf(
					'Draft created: <a href="%s" target="_blank">Series vs %s &mdash; %s</a>',
					esc_url( get_edit_post_link( $new_post_id ) ),
					esc_html( $opponent_display_name ),
					esc_html( $post_date )
				)
			);
		} else {
			$this->set_notice( 'error', 'Failed to create the series draft post: ' . esc_html( $new_post_id->get_error_message() ) );
		}
	}

	/**
	 * Display a stored notice on the Series Generator screen.
	 */
	public function maybe_show_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'series-generator' ) ) {
			return;
		}

		$transient_key = self::NOTICE_TRANSIENT . '_' . get_current_user_id();
		$notice        = get_transient( $transient_key );
		if ( ! $notice ) {
			return;
		}

		delete_transient( $transient_key );
		list( $type, $message ) = $notice;
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Store a notice for display on the next page load.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice text (may contain safe HTML).
	 */
	protected function set_notice( $type, $message ) {
		set_transient( self::NOTICE_TRANSIENT . '_' . get_current_user_id(), array( $type, $message ), 60 );
	}

	/**
	 * Build one game section from the pattern file, substituting dynamic values.
	 *
	 * @param int    $num         Game number in the series (1, 2, 3…).
	 * @param string $opponent    Opponent team slug.
	 * @param string $is_home     '1' for home, '0' for away.
	 * @param int    $season_game Season game number for the heading (0 → '??').
	 * @return string
	 */
	protected function game_section( $num, $opponent, $is_home, $season_game = 0 ) {
		$game_display = $season_game > 0 ? $season_game : '??';

		return $this->pattern_content(
			'game-series-one-game',
			array(
				'Game 1 (00) - WONLOST'            => "Game {$num} ({$game_display}) - WONLOST",
				'"field_69c708d15fe00": "tbd"'      => '"field_69c708d15fe00": "' . $opponent . '"',
				'"field_69c706cbee27f": "1"'        => '"field_69c706cbee27f": "' . $is_home . '"',
			)
		) . "\n\n";
	}

	/**
	 * Load a pattern file, optionally replacing placeholder values, and return
	 * the trimmed content.
	 *
	 * @param string               $slug         Pattern slug (filename without .php).
	 * @param array<string,string> $replacements Search => replacement pairs applied
	 *                                           with str_replace after the file loads.
	 * @return string
	 */
	protected function pattern_content( $slug, $replacements = array() ) {
		$file = plugin_dir_path( __DIR__ ) . 'patterns/' . $slug . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		ob_start();
		include $file;
		$content = trim( ob_get_clean() );

		if ( ! empty( $replacements ) ) {
			$content = str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
		}

		return $content;
	}
}

new Basebelles_Series_Generator();
