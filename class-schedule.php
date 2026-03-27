<?php
/**
 * Cleveland Guardians Next Game Display
 *
 * Description: Displays the next scheduled Cleveland Guardians game(s) from a local CSV file.
 *
 * @package Base*Belles
 * @author Ipstenu
 * @link https://github.com/Ipstenu/basebelles
 * @license GPL-2.0+
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BASEBELLES_SCHEDULE_GAME_OVER', 4 );

class Basebelles_Schedule {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		if ( ! did_action( 'init' ) ) {
			add_action( 'init', array( $this, 'register_shortcode' ) );
		} else {
			$this->register_shortcode();
		}
	}

	public function register_shortcode() {
		add_shortcode( 'guardians_next_game', array( $this, 'shortcode_handler' ) );
	}

	public function shortcode_handler( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 0,
			),
			$atts,
			'guardians_next_game'
		);

		return $this->get_guardians_next_game( (int) $atts['limit'] );
	}

	public function get_guardians_next_game( $limit = 0, $show_logo = false ) {
		global $wp_filesystem;

		// Initialize the WP_Filesystem
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$year             = gmdate( 'Y' );
		$filename         = "guardians-{$year}.csv";
		$filepath         = ABSPATH . 'wp-content/plugins/basebelles/schedules/' . $filename;
		$no_games_message = self::generate_no_games_message( $year );

		if ( ! $wp_filesystem->exists( $filepath ) ) {
			return $no_games_message;
		}

		$file_content = $wp_filesystem->get_contents( $filepath ) ?? false;

		if ( ! $file_content ) {
			return $no_games_message;
		}

		$all_future_games = self::get_all_future_games( $file_content );
		if ( empty( $all_future_games ) ) {
			error_log( 'No future games found: ' . $filepath );
			return $no_games_message;
		}

		$upcoming_games = self::get_upcoming_games( $all_future_games, $limit );
		if ( empty( $upcoming_games ) ) {
			error_log( 'No upcoming games found: ' . $filepath );
			return $no_games_message;
		}

		$output = self::generate_output( $upcoming_games, $show_logo );
		return $output;
	}

	/**
	 * Generate the no games message
	 *
	 * @param string $year The year
	 * @return string The no games message
	 */
	private static function generate_no_games_message( $year ) {
		$current_month = (int) gmdate( 'm' );
		$message       = '<p>There are no more upcoming games for the ' . $year . ' season.</p>';

		if ( $current_month >= 11 ) {
			// November, December - Between seasons
			$message = '<p>The ' . $year . ' season is over. Come back next year.</p>';
		} elseif ( $current_month >= 1 && $current_month <= 2 ) {
			// January, February - Pre-Spring Training
			$message = '<p>The ' . $year . ' schedule is coming soon...</p>';
		} elseif ( 9 === $current_month ) {
			// September
			$message = '<p>Post Season Schedule TBD</p>';
		}

		return $message;
	}

	/**
	 * Get all future games from the CSV file
	 *
	 * @param array $games The games
	 * @return array The all future games
	 */
	private static function get_all_future_games( $file_content ) {
		// Explode content into rows
		$rows    = explode( "\n", str_replace( "\r", '', $file_content ) );
		$headers = str_getcsv( array_shift( $rows ), escape: '\\' ); // Get first row as headers

		// Map headers to indices
		$idx_date     = array_search( 'START DATE', $headers, true );
		$idx_time     = array_search( 'START TIME ET', $headers, true );
		$idx_subject  = array_search( 'SUBJECT', $headers, true );
		$idx_location = array_search( 'LOCATION', $headers, true );
		$idx_desc     = array_search( 'DESCRIPTION', $headers, true );

		// Initialize the array of all future games
		$today            = new DateTime( 'today', new DateTimeZone( 'America/New_York' ) );
		$all_future_games = array();

		foreach ( $rows as $row_str ) {
			if ( empty( trim( $row_str ) ) ) {
				continue;
			}

			$data          = str_getcsv( $row_str, escape: '\\' );
			$game_date_str = $data[ $idx_date ];

			// Format MM/DD/YY
			$game_date = DateTime::createFromFormat( 'm/d/y', $game_date_str, new DateTimeZone( 'America/New_York' ) ) ?? false;
			//$game_date = DateTime::createFromFormat( 'm/d/y', $game_date_str ) ?? false;
			if ( ! $game_date ) {
				continue;
			}
			$game_date->setTime( 0, 0, 0 );

			if ( $game_date >= $today ) {
				$raw_game_description   = isset( $data[ $idx_desc ] ) ? $data[ $idx_desc ] : '';
				$csv_game_description   = str_replace( ' ----- ', ', ', $raw_game_description );
				$game_description_items = explode( ', ', $csv_game_description );

				$all_future_games[] = array(
					'date'     => $game_date_str,
					'time'     => $data[ $idx_time ],
					'subject'  => $data[ $idx_subject ],
					'location' => $data[ $idx_location ],
					'desc'     => $game_description_items,
				);
			}
		}

		return $all_future_games;
	}

	/**
	 * Get the upcoming games from the all future games
	 *
	 * @param array $all_future_games The all future games
	 * @param int   $limit            The number of games to show (0 for just next date)
	 * @return array The upcoming games
	 */
	private static function get_upcoming_games( $all_future_games, $limit = 0 ) {
		$limit = (int) $limit;
		$tz    = new DateTimeZone( 'America/New_York' );
		$now   = new DateTime( 'now', $tz );

		$valid_games = array();

		foreach ( $all_future_games as $game ) {
			// Create a full DateTime for the game start
			$game_start = DateTime::createFromFormat( 'm/d/y h:i A', $game['date'] . ' ' . $game['time'], $tz );
			if ( ! $game_start ) {
				continue;
			}

			// Calculate when the game is "over" (start + 4 hours)
			$game_end = clone $game_start;
			$game_end->modify( '+' . BASEBELLES_SCHEDULE_GAME_OVER . ' hours' );

			// If the game hasn't ended yet, it's a candidate
			if ( $game_end > $now ) {
				$valid_games[] = $game;
			}
		}

		// Case 1: If we have a specific limit, just take the first N valid games
		if ( $limit > 0 ) {
			return array_slice( $valid_games, 0, $limit );
		}

		// Case 2: No limit, show all games on the next available date
		if ( empty( $valid_games ) ) {
			return array();
		}

		$next_date      = $valid_games[0]['date'];
		$next_day_games = array();
		foreach ( $valid_games as $game ) {
			if ( $game['date'] === $next_date ) {
				$next_day_games[] = $game;
			} else {
				break; // Done with the first date
			}
		}

		return $next_day_games;
	}

	/**
	 * Generate the output for the next game
	 *
	 * @param array $upcoming_games The upcoming games
	 * @return string The output
	 */
	private static function generate_output( $upcoming_games, $show_logo = false ) {
		$output = '<div class="guardians-next-game">';
		foreach ( $upcoming_games as $game ) :
			if ( false === strpos( $game['location'], 'Progressive Field' ) ) :
				$location    = 'away';
				$versus_team = str_replace( 'Guardians at ', '', $game['subject'] );
			else :
				$location    = 'home';
				$versus_team = str_replace( ' at Guardians', '', $game['subject'] );
			endif;

			$versus_logo = ( $show_logo ) ? plugin_dir_url( __DIR__ ) . 'basebelles/team-icons/' . strtolower( $versus_team ) . '.png' : '';

			$output .= '<div class="game-entry">';
			if ( $show_logo && ! empty( $versus_logo ) ) :
				$output .= self::output_logo_output( $game, $versus_logo, $location );
			else :
				$output .= self::output_no_logo_output( $game );
			endif;
			$output .= '</div>';
		endforeach;
		$output .= '</div>';
		return $output;
	}

	private static function output_no_logo_output( $game ) {
		ob_start();
		echo '<div class="game-subject">';
		echo esc_html( $game['subject'] );
		echo '</div>';
		echo '<div class="game-date">📅 ' . esc_html( $game['date'] ) . ' at ' . esc_html( $game['time'] ) . ' Eastern</div>';
		echo '<div class="game-location">📍 ' . esc_html( $game['location'] ) . '</div>';
		echo '</div>';
		echo '<div class="game-description">';
		if ( count( $game['desc'] ) > 1 ) :
			echo '<ul>';
			foreach ( $game['desc'] as $desc ) :
				echo '<li>' . esc_html( $desc ) . '</li>';
			endforeach;
			echo '</ul>';
		else :
			echo '<p>' . esc_html( $game['desc'][0] ) . '</p>';
		endif;
		echo '</div>';
		return ob_get_clean();
	}

	private static function output_logo_output( $game, $versus_logo, $location ) {

		$versus_team = str_replace( ' at Guardians', '', $game['subject'] );
		$versus_team = str_replace( 'Guardians at ', '', $game['subject'] );

		// Use teams.json to get the team name
		$teams            = json_decode( file_get_contents( plugin_dir_path( __FILE__ ) . '/teams.json' ), true );
		$versus_team_abbr = $teams[ strtolower( $versus_team ) ]['abbreviation'];

		// Format date to MM/DD
		$game_date           = DateTime::createFromFormat( 'm/d/y', $game['date'], new DateTimeZone( 'America/New_York' ) );
		$game_date_formatted = $game_date->format( 'm/d' );

		// Format time to HH:MM AM/PM ET
		$game_time           = DateTime::createFromFormat( 'h:i A', $game['time'], new DateTimeZone( 'America/New_York' ) );
		$game_time_formatted = $game_time->format( 'h:i A' );

		ob_start();
		echo '<div class="game-logo-container">';
		echo '<div class="game-logo"><img src="' . esc_url( $versus_logo ) . '" alt="' . esc_attr( $versus_team ) . ' Logo" width="75" height="75" /></div>';
		// LOGO: DD/MM @/vs TEAM
		echo '<div class="game-details">';
		echo esc_html( $game_date_formatted ) . ' ';
		if ( 'away' === $location ) :
			echo '@ ' . esc_html( $versus_team_abbr ) . ' ';
		else :
			echo 'vs. ' . esc_html( $versus_team_abbr ) . ' ';
		endif;
		echo '<br />' . esc_html( $game_time_formatted ) . ' ET';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}
}

new Basebelles_Schedule();
