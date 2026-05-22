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

		if ( empty( $date ) || false === strtotime( $date ) ) {
			$this->set_notice( 'error', 'Please set a valid series start date before generating.' );
			return;
		}

		// Fall back to start date if end date is blank or unparseable.
		if ( empty( $end_date ) || false === strtotime( $end_date ) ) {
			$end_date = $date;
		}

		$field_obj             = get_field_object( 'field_sgen_opponent', self::OPTIONS_POST_ID );
		$choices               = ! empty( $field_obj['choices'] ) ? $field_obj['choices'] : array();
		$opponent_display_name = isset( $choices[ $opponent ] )
			? $choices[ $opponent ]
			: strtoupper( str_replace( '-', ' ', $opponent ) );

		$post_date = wp_date( 'F j, Y', strtotime( $date ) );

		$post_content  = $this->paragraph_block( 'Intro' ) . "\n\n";
		$post_content .= $this->heading_block( 'Series Results' ) . "\n\n";
		$post_content .= $this->series_block( $opponent, $date, $end_date, $is_home ) . "\n\n";
		$post_content .= $this->paragraph_block( 'Series Notes' ) . "\n\n";
		$post_content .= $this->heading_block( 'Game Results' ) . "\n\n";

		for ( $i = 1; $i <= $game_count; $i++ ) {
			$season_game   = $game_number + ( $i - 1 );
			$post_content .= $this->game_section( $i, $opponent, $is_home, $opponent_display_name, $season_game );
		}

		$post_content .= $this->separator_block() . "\n\n";
		$post_content .= $this->paragraph_block( 'Who are we playing next?' );

		$new_post_id = wp_insert_post(
			array(
				'post_title'   => sprintf( 'Series vs %s - %s', $opponent_display_name, $post_date ),
				'post_content' => $post_content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
			)
		);

		if ( ! is_wp_error( $new_post_id ) ) {
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

	// --- Block markup builders ---------------------------------------------------

	protected function paragraph_block( $text ) {
		return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
	}

	protected function heading_block( $text, $level = 2 ) {
		$tag  = 'h' . $level;
		$attr = 2 === $level ? '' : ' {"level":' . $level . '}';
		return "<!-- wp:heading{$attr} -->\n<{$tag} class=\"wp-block-heading\">" . esc_html( $text ) . "</{$tag}>\n<!-- /wp:heading -->";
	}

	protected function series_block( $opponent, $date, $end_date, $is_home ) {
		$data = array(
			'name' => 'acf/basebelles-series',
			'data' => array(
				'field_69c9b65a0d4e8' => $is_home,
				'field_69caeaeb128f1' => array(
					'field_69caebbe128f2' => $date,
					'field_69caebf0128f3' => $end_date,
				),
				'field_69c9b6120d4e7' => $opponent,
				'field_69c9b67b0d4e9' => '',
				'field_69c9b6880d4ea' => '',
			),
			'mode' => 'preview',
		);
		return '<!-- wp:acf/basebelles-series ' . wp_json_encode( $data ) . ' /-->';
	}

	protected function results_block( $opponent, $is_home ) {
		$blank_innings = array_fill(
			0,
			9,
			array(
				'field_69c70d3fcd56c' => '',
				'field_69c70d4acd56d' => '',
			)
		);
		$data          = array(
			'name' => 'acf/basebelles-results',
			'data' => array(
				'field_69c707ee1ddb3' => array( 'field_69c7084e1ddb6' => '1 - 0' ),
				'field_69c708d15fdff' => array(
					'field_69c708d15fe00' => $opponent,
					'field_69c708d15fe04' => '0 - 1',
				),
				'field_69c7087fc99cf' => array(
					'field_69c706cbee27f' => $is_home,
					'field_69c706bdee27e' => '9',
					'field_69c707c05892a' => array(
						'field_69c707c05892b' => '0',
						'field_69c707c05892c' => '0',
					),
					'field_69c707cedc16b' => array(
						'field_69c707cedc16c' => '0',
						'field_69c707cedc16d' => '0',
					),
					'field_69c70d26cd56b' => $blank_innings,
				),
			),
			'mode' => 'preview',
		);
		return '<!-- wp:acf/basebelles-results ' . wp_json_encode( $data ) . ' /-->';
	}

	protected function streamable_block() {
		$data = array(
			'name' => 'acf/basebelles-streamable',
			'data' => array( 'field_69d69d4716ab7' => '' ),
			'mode' => 'preview',
		);
		return '<!-- wp:acf/basebelles-streamable ' . wp_json_encode( $data ) . ' /-->';
	}

	protected function game_section( $num, $opponent, $is_home, $opponent_display_name, $season_game = 0 ) {
		$label        = strtoupper( $opponent_display_name );
		$game_display = $season_game > 0 ? $season_game : '??';

		$out  = $this->heading_block( "Game {$num} ({$game_display}) - WONLOST", 3 ) . "\n\n";
		$out .= $this->results_block( $opponent, $is_home ) . "\n\n";
		$out .= "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>TBD</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n";
		$out .= $this->streamable_block() . "\n\n";
		return $out;
	}

	protected function separator_block() {
		return '<!-- wp:separator {"backgroundColor":"custom-wine","className":"is-style-dots"} -->' . "\n" .
			'<hr class="wp-block-separator has-text-color has-custom-wine-color has-alpha-channel-opacity has-custom-wine-background-color has-background is-style-dots"/>' . "\n" .
			'<!-- /wp:separator -->';
	}
}

new Basebelles_Series_Generator();
