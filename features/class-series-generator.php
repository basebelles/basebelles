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
		$game_number = (int) get_field( 'sgen_game_number', self::OPTIONS_POST_ID );
		$is_home     = get_field( 'sgen_home_away', self::OPTIONS_POST_ID ) ? '1' : '0';
		$season_type = get_field( 'sgen_season_type', self::OPTIONS_POST_ID ) ? get_field( 'sgen_season_type', self::OPTIONS_POST_ID ) : 'regular-season';
		$postseason  = 'post-season' === $season_type;

		// Look up team data and pre-fill current W/L records from the API.
		$team_term = '';
		$guards_wl = '1 - 0';
		$oppo_wl   = '0 - 1';
		$ps_dates  = array();

		if ( class_exists( 'Basebelles_API' ) ) {
			$api       = Basebelles_API::get_instance();
			$team_data = $api->get_team( $opponent );
			$team_term = $team_data['taxonomy_term'] ?? '';

			$api_season_types = array(
				'regular-season'  => 'regularSeason',
				'post-season'     => 'postseason',
				'spring-training' => 'springTraining',
			);
			$api_season_type = $api_season_types[ $season_type ] ?? 'regularSeason';

			$guards_data = $api->fetch_standings( $api_season_type );
			if ( ! is_wp_error( $guards_data ) && isset( $guards_data['wins'], $guards_data['losses'] ) ) {
				$guards_wl = $guards_data['wins'] . ' - ' . $guards_data['losses'];
			}

			$oppo_team_id = ! empty( $team_data['mlb_team_id'] ) ? (int) $team_data['mlb_team_id'] : 0;
			if ( $oppo_team_id ) {
				$oppo_data = $api->fetch_standings( $api_season_type, null, $oppo_team_id );
				if ( ! is_wp_error( $oppo_data ) && isset( $oppo_data['wins'], $oppo_data['losses'] ) ) {
					$oppo_wl = $oppo_data['wins'] . ' - ' . $oppo_data['losses'];
				}
			}

			if ( $postseason ) {
				// Real dates and venues for each game. An error just means the fallback pattern.
				$fetched  = $date ? $api->get_postseason_schedule( (int) substr( $date, 0, 4 ) ) : array();
				$ps_dates = is_wp_error( $fetched ) ? array() : $fetched;
			} elseif ( ! $game_number ) {
				// Auto-detect season game number from schedule unless manually overridden.
				$detected = $api->get_season_game_number_for_date( $date, $api_season_type );
				if ( ! is_wp_error( $detected ) ) {
					$game_number = $detected;
				}
			}
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

		$post_date = wp_date( 'F j, Y', strtotime( $date . ' 12:00:00' ) );

		// One entry per game: heading text in the brackets, venue, and an "if necessary" marker.
		$games   = array();
		$warning = '';

		if ( $postseason && class_exists( 'Basebelles_Postseason' ) && class_exists( 'Basebelles_API' ) ) {
			$plan       = Basebelles_Postseason::plan_series( $ps_dates, $date, $end_date, Basebelles_API::GUARDIANS_TEAM_ID, $game_count, '1' === $is_home );
			$game_count = $plan['games_in_series'];

			foreach ( $plan['games'] as $game ) {
				$games[] = array(
					'num'          => $game['number'],
					'label'        => '' !== $game['date'] ? wp_date( 'j M', strtotime( $game['date'] . ' 12:00:00' ) ) : '??',
					'is_home'      => $game['is_home'] ? '1' : '0',
					'if_necessary' => $game['if_necessary'],
				);
			}

			// The series header shows where Game 1 is.
			$is_home = $games[0]['is_home'];

			if ( ! $plan['from_schedule'] ) {
				$warning = 'MLB hasn&rsquo;t published this series yet, so venues follow the usual pattern and game dates are left as ??. Double-check them before publishing.';
			}
		} else {
			for ( $i = 1; $i <= $game_count; $i++ ) {
				$season_game = $game_number + ( $i - 1 );
				$games[]     = array(
					'num'          => $i,
					'label'        => $season_game > 0 ? (string) $season_game : '??',
					'is_home'      => $is_home,
					'if_necessary' => false,
				);
			}
		}

		$post_content = $this->pattern_content(
			'game-series-header',
			array(
				'"field_69c9b65a0d4e8":"0"'        => '"field_69c9b65a0d4e8":"' . $is_home . '"',
				'"field_69caebbe128f2":"20260520"' => '"field_69caebbe128f2":"' . $date . '"',
				'"field_69caebf0128f3":"20260520"' => '"field_69caebf0128f3":"' . $end_date . '"',
				'"field_69c9b6120d4e7":"tbd"'      => '"field_69c9b6120d4e7":"' . $opponent . '"',
			)
		) . "\n\n";

		foreach ( $games as $game ) {
			$post_content .= $this->game_section( $game, $opponent, $guards_wl, $oppo_wl );
		}

		$venues = array_unique( wp_list_pluck( $games, 'is_home' ) );

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
			// A split post-season series gets both terms.
			$venue_terms = array_map(
				static function ( $home ) {
					return '1' === $home ? 'home' : 'away';
				},
				array_values( $venues )
			);
			wp_set_object_terms( $new_post_id, $venue_terms, 'venue-type' );
			wp_set_object_terms( $new_post_id, 'games', 'category' );

			$this->set_notice(
				$warning ? 'warning' : 'success',
				sprintf(
					'Draft created: <a href="%s" target="_blank">Series vs %s &mdash; %s</a>%s',
					esc_url( get_edit_post_link( $new_post_id ) ),
					esc_html( $opponent_display_name ),
					esc_html( $post_date ),
					$warning ? '<br>' . $warning : ''
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
	 * @param array  $game      {
	 *     @type int    $num          Game number in the series (1, 2, 3…).
	 *     @type string $label        Bracketed heading text: season game number in the regular
	 *                                season, '13 Oct' in the post-season, '??' if unknown.
	 *     @type string $is_home      '1' for home, '0' for away.
	 *     @type bool   $if_necessary Adds "if necessary" to the heading.
	 * }
	 * @param string $opponent  Opponent team slug.
	 * @param string $guards_wl Guardians current W/L record (e.g. '34 - 20').
	 * @param string $oppo_wl   Opponent current W/L record (e.g. '28 - 26').
	 * @return string
	 */
	protected function game_section( $game, $opponent, $guards_wl = '1 - 0', $oppo_wl = '0 - 1' ) {
		$heading = sprintf(
			'Game %d (%s)%s - WONLOST',
			$game['num'],
			esc_html( $game['label'] ),
			$game['if_necessary'] ? ' - if necessary' : ''
		);

		return $this->pattern_content(
			'game-series-one-game',
			array(
				'Game 1 (00) - WONLOST'           => $heading,
				'"field_69c708d15fe00": "tbd"'     => '"field_69c708d15fe00": "' . $opponent . '"',
				'"field_69c706cbee27f": "1"'       => '"field_69c706cbee27f": "' . $game['is_home'] . '"',
				'"field_69c7084e1ddb6": "1 - 0"'   => '"field_69c7084e1ddb6": "' . $guards_wl . '"',
				'"field_69c708d15fe04": "0 - 1"'   => '"field_69c708d15fe04": "' . $oppo_wl . '"',
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
