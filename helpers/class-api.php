<?php
/**
 * Shared API integration helpers for Base*Belles.
 *
 * Doc Source: https://github.com/pseudo-r/Public-MLB-API/blob/main/docs/README.md
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_API {

	const API_BASE_URL      = 'https://statsapi.mlb.com/api/v1/';
	const API_CACHE_TTL     = 900;
	const API_TIMEOUT       = 10;
	const AL_LEAGUE_ID      = 103; // American League
	const CL_LEAGUE_ID      = 114; // Cactus League (spring training)
	const GUARDIANS_TEAM_ID = 114; // Cleveland Guardians
	/** Calendar days included in Guardians transactions API query (inclusive). */
	const TRANSACTIONS_LOOKBACK_DAYS = 30;
	/** Upper bound for how many transactions the block may list. */
	const TRANSACTIONS_DISPLAY_MAX = 50;
	/** Cache TTL for season archive snapshots (past years are immutable). */
	const API_ARCHIVE_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var Basebelles_API|null
	 */
	private static $instance = null;

	/**
	 * Get the shared API instance.
	 *
	 * @return Basebelles_API
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get settings used to build MLB API requests.
	 *
	 * @param string $season_type The season type.
	 *
	 * @return array
	 */
	public function get_season_settings( $season_type = 'regularSeason' ) {
		$allowed_types = array(
			'springTraining',
			'regularSeason',
			'wildCard',
			'postseason',
		);

		$season_type = ( ! in_array( $season_type, $allowed_types, true ) ) ? 'regularSeason' : (string) $season_type;
		$season      = (int) $this->get_option_value( 'season', (int) gmdate( 'Y' ) );

		// If the season is before 1900 or not a number, set the season to the current year.
		if ( $season < 1900 || ! is_numeric( $season ) ) {
			$season = (int) gmdate( 'Y' );
		}

		return array(
			'season'      => $season,
			'season_type' => $season_type,
		);
	}

	/**
	 * Fetch and normalize standings data.
	 *
	 * @param string   $season_type  The season type.
	 * @param int|null $season_year  Optional season year; defaults to ACF/options season.
	 * @param int      $team_id      Optional team ID for validation; defaults to Guardians.
	 *
	 * @return array|WP_Error
	 */
	public function fetch_standings( $season_type = 'regularSeason', $season_year = null, $team_id = self::GUARDIANS_TEAM_ID ) {
		$settings = $this->get_season_settings( $season_type );
		$year     = ( null !== $season_year && is_numeric( $season_year ) ) ? (int) $season_year : (int) $settings['season'];
		if ( $year < 1900 ) {
			$year = (int) gmdate( 'Y' );
		}

		return $this->fetch_standings_normalized( $year, $settings['season_type'], $team_id, null, self::API_CACHE_TTL );
	}

	/**
	 * Standings snapshot for taxonomy archives (cached 24h; optional date for Spring Training freeze).
	 *
	 * @param int    $year        Season year (e.g. 2024).
	 * @param string $season_type springTraining|regularSeason|wildCard|postseason.
	 * @param int    $team_id     Optional team ID for validation; defaults to Guardians.
	 * @return array|WP_Error Normalized stats array, or error if unavailable.
	 */
	public function get_season_archive_stats( $year, $season_type, $team_id = self::GUARDIANS_TEAM_ID ) {
		$year = (int) $year;
		if ( $year < 1900 ) {
			return new WP_Error( 'basebelles_api_bad_year', 'Invalid season year.' );
		}

		$current_year = (int) gmdate( 'Y' );
		if ( $year > $current_year ) {
			return new WP_Error( 'basebelles_api_future', 'Season data is not available yet.' );
		}

		$allowed_types = array(
			'springTraining',
			'regularSeason',
			'wildCard',
			'postseason',
		);
		if ( ! in_array( $season_type, $allowed_types, true ) ) {
			$season_type = 'regularSeason';
		}

		$date = null;
		if ( 'springTraining' === $season_type ) {
			$date = $this->get_spring_training_freeze_date( $year, $team_id );
		}

		if ( 'postseason' === $season_type ) {
			$full = $this->fetch_standings_normalized( $year, 'postseason', $team_id, null, self::API_ARCHIVE_CACHE_TTL );
			if ( is_wp_error( $full ) || ( is_array( $full ) && ! empty( $full['_empty_records'] ) ) ) {
				$fallback = $this->get_postseason_record_from_schedule( $year, $team_id );
				if ( is_wp_error( $fallback ) ) {
					return is_wp_error( $full ) ? $full : $fallback;
				}
				return $this->map_team_record_to_archive_stats( $fallback['standings_row'], $year, 'postseason', $fallback['division_name'] );
			}
			unset( $full['_empty_records'], $full['_team_record_raw'] );
			return $this->map_full_standings_to_archive_stats( $full, $year, 'postseason' );
		}

		$full = $this->fetch_standings_normalized( $year, $season_type, $team_id, $date, self::API_ARCHIVE_CACHE_TTL );

		if ( is_wp_error( $full ) ) {
			return $full;
		}
		if ( ! empty( $full['_empty_records'] ) ) {
			return new WP_Error( 'basebelles_api_empty', 'Standings data was empty.' );
		}
		unset( $full['_empty_records'], $full['_team_record_raw'] );

		return $this->map_full_standings_to_archive_stats( $full, $year, $season_type );
	}

	/**
	 * Load standings from the API and return the same shape as the legacy fetch_standings array.
	 *
	 * @param int         $year         Season year.
	 * @param string      $season_type  Standings type.
	 * @param int         $team_id      Optional team ID for validation; defaults to Guardians.
	 * @param string|null $date         Optional YYYY-MM-DD snapshot (Spring Training).
	 * @param int         $cache_ttl    Transient TTL in seconds.
	 * @return array|WP_Error
	 */
	private function fetch_standings_normalized( $year, $season_type, $team_id = self::GUARDIANS_TEAM_ID, $date = null, $cache_ttl = self::API_CACHE_TTL ) {
		$allowed_types = array(
			'springTraining',
			'regularSeason',
			'wildCard',
			'postseason',
		);

		if ( ! in_array( $season_type, $allowed_types, true ) ) {
			$season_type = 'regularSeason';
		}

		$league_id = $this->get_league_id_by_season_type( $season_type, $team_id );
		$query     = array(
			'leagueId'       => $league_id,
			'season'         => (int) $year,
			'standingsTypes' => $season_type,
			'hydrate'        => 'division',
		);

		if ( null !== $date && '' !== $date ) {
			$query['date'] = $date;
		}

		$data = $this->request_json( 'standings', $query, $cache_ttl );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['records'] ) || ! is_array( $data['records'] ) ) {
			return array(
				'_empty_records' => true,
			);
		}

		$team_record = $this->find_team_record( $data['records'], $team_id );

		if ( empty( $team_record ) ) {
			return new WP_Error( 'basebelles_api_team_missing', 'Could not find the specified team in the standings response.' );
		}

		$division_name = $team_record['division']['nameShort'] ?? 'AL Central';
		$split_records = $team_record['records']['splitRecords'] ?? array();

		return array(
			'summary'              => $this->format_summary( $team_record, $division_name ),
			'division_name'        => $division_name,
			'division_rank'        => $team_record['divisionRank'] ?? '',
			'wins'                 => (int) ( $team_record['wins'] ?? 0 ),
			'losses'               => (int) ( $team_record['losses'] ?? 0 ),
			'winning_percentage'   => (string) ( $team_record['winningPercentage'] ?? '.000' ),
			'games_back'           => $this->normalize_games_back( $team_record['divisionGamesBack'] ?? '-' ),
			'wild_card_games_back' => $this->normalize_games_back( $team_record['wildCardGamesBack'] ?? '-' ),
			'last_ten'             => $this->format_split_record( $split_records, 'lastTen' ),
			'streak'               => strtoupper( (string) ( $team_record['streak']['streakCode'] ?? '-' ) ),
			'runs_scored'          => (int) ( $team_record['runsScored'] ?? 0 ),
			'runs_allowed'         => (int) ( $team_record['runsAllowed'] ?? 0 ),
			'run_differential'     => $this->format_run_differential( $team_record['runDifferential'] ?? 0 ),
			'home'                 => $this->format_split_record( $split_records, 'home' ),
			'away'                 => $this->format_split_record( $split_records, 'away' ),
			'over_500'             => $this->format_split_record( $split_records, 'winners' ),
			'season'               => (int) $year,
			'season_type'          => $season_type,
			'last_updated'         => $team_record['lastUpdated'] ?? '',
			'_team_record_raw'     => $team_record,
		);
	}

	/**
	 * Get the league ID to query for a given season type (e.g. AL for regular season, CL for spring training).
	 *
	 * @param string $season_type The season type.
	 * @param int    $team_id    Optional team ID for validation; defaults to Guardians.
	 * @return int League ID to use in API queries.
	 */
	private function get_league_id_by_season_type( $season_type, $team_id = self::GUARDIANS_TEAM_ID ) {
		// If it's the Guards, it's easier.
		if ( self::GUARDIANS_TEAM_ID === $team_id ) {
			if ( 'springTraining' === $season_type ) {
				return self::CL_LEAGUE_ID;
			}

			return self::AL_LEAGUE_ID;
		}

		// Else we use the team ID.
		// https://statsapi.mlb.com/api/v1/teams/$team_id
		$team_data = $this->request_json( 'teams/' . $team_id, array(), self::API_CACHE_TTL );

		if ( is_wp_error( $team_data ) || empty( $team_data['teams'] ) || ! is_array( $team_data['teams'] ) ) {
			return self::AL_LEAGUE_ID;
		}

		// Different IDs for spring training vs regular season, so we have to look it up from the team data.
		switch ( $season_type ) {
			case 'springTraining':
				return (int) ( $team_data['teams'][0]['springLeague']['id'] ?? self::CL_LEAGUE_ID );
			default:
				return (int) ( $team_data['teams'][0]['league']['id'] ?? self::AL_LEAGUE_ID );
		}
	}

	/**
	 * Last calendar day of Guardians spring training for a season (for standings snapshot).
	 *
	 * @param int $year Season year.
	 * @return string YYYY-MM-DD.
	 */
	private function get_spring_training_freeze_date( $year, $team_id = self::GUARDIANS_TEAM_ID ) {
		$year = (int) $year;
		$data = $this->request_json(
			'schedule',
			array(
				'sportId'  => 1,
				'teamId'   => $team_id,
				'season'   => $year,
				'gameType' => 'S',
			),
			self::API_ARCHIVE_CACHE_TTL
		);

		$max_date = '';
		if ( ! is_wp_error( $data ) && ! empty( $data['dates'] ) && is_array( $data['dates'] ) ) {
			foreach ( $data['dates'] as $row ) {
				$d = (string) ( $row['date'] ?? '' );
				if ( '' !== $d && $d > $max_date ) {
					$max_date = $d;
				}
			}
		}

		if ( '' !== $max_date ) {
			return $max_date;
		}

		$options_year = (int) $this->get_option_value( 'season', (int) gmdate( 'Y' ) );
		if ( $year === $options_year && function_exists( 'get_field' ) ) {
			$st = get_field( 'spring_training', 'option' );
			if ( is_array( $st ) && ! empty( $st['end'] ) && 'TBD' !== $st['end'] ) {
				$parsed = $this->normalize_game_date( (string) $st['end'] );
				if ( (int) substr( $parsed, 0, 4 ) === $year ) {
					return $parsed;
				}
			}
		}

		// Conservative default when schedule is not yet published.
		return $year . '-03-31';
	}

	/**
	 * When postseason standings are empty, derive W–L from schedule (non-R/S games).
	 *
	 * @param int $year Season year.
	 * @return array|WP_Error team_record-shaped array plus division_name, or error.
	 */
	private function get_postseason_record_from_schedule( $year, $team_id = self::GUARDIANS_TEAM_ID ) {
		$data = $this->request_json(
			'schedule',
			array(
				'sportId'   => 1,
				'teamId'    => $team_id,
				'season'    => (int) $year,
				'startDate' => $year . '-04-01',
				'endDate'   => $year . '-11-30',
			),
			self::API_ARCHIVE_CACHE_TTL
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$wins             = 0;
		$losses           = 0;
		$runs_scored      = 0;
		$runs_allowed     = 0;
		$postseason_games = 0;

		foreach ( $data['dates'] ?? array() as $day ) {
			foreach ( $day['games'] ?? array() as $game ) {
				if ( ! is_array( $game ) ) {
					continue;
				}
				$gtype = (string) ( $game['gameType'] ?? '' );
				if ( 'R' === $gtype || 'S' === $gtype ) {
					continue;
				}
				$status = (string) ( $game['status']['abstractGameState'] ?? '' );
				if ( 'Final' !== $status ) {
					continue;
				}

				$is_home   = self::GUARDIANS_TEAM_ID === (int) ( $game['teams']['home']['team']['id'] ?? 0 );
				$team_side = $is_home ? 'home' : 'away';
				$opp_side  = $is_home ? 'away' : 'home';
				$team_r    = (int) ( $game['teams'][ $team_side ]['score'] ?? 0 );
				$opp_r     = (int) ( $game['teams'][ $opp_side ]['score'] ?? 0 );
				$is_winner = ! empty( $game['teams'][ $team_side ]['isWinner'] );

				++$postseason_games;
				if ( $is_winner ) {
					++$wins;
				} else {
					++$losses;
				}
				$runs_scored  += $team_r;
				$runs_allowed += $opp_r;
			}
		}

		if ( $postseason_games < 1 ) {
			return new WP_Error( 'basebelles_api_postseason_empty', 'No postseason games found for this season.' );
		}

		$rd      = $runs_scored - $runs_allowed;
		$pct_val = ( $wins + $losses ) > 0 ? $wins / ( $wins + $losses ) : 0;
		$pct_str = number_format( $pct_val, 3, '.', '' );
		if ( $pct_val < 1.0 ) {
			$pct_str = substr( $pct_str, 1 );
		}

		$standings_row = array(
			'wins'               => $wins,
			'losses'             => $losses,
			'winning_percentage' => $pct_str,
			'run_differential'   => $this->format_run_differential( $rd ),
			'games_back'         => '—',
			'last_ten'           => '—',
			'streak'             => '—',
			'division_rank'      => '',
			'division_name'      => 'Postseason',
			'summary'            => 'Postseason',
		);

		return array(
			'standings_row' => $standings_row,
			'division_name' => 'Postseason',
		);
	}

	/**
	 * @param array  $full Full normalized standings from fetch_standings_normalized.
	 * @param int    $year Season year.
	 * @param string $season_type API season type.
	 * @return array
	 */
	private function map_full_standings_to_archive_stats( $full, $year, $season_type ) {

		return $this->map_team_record_to_archive_stats(
			$full,
			$year,
			$season_type,
			$full['division_name'] ?? 'AL Central'
		);
	}

	/**
	 * Build archive stat keys for the snapshot block.
	 *
	 * @param array  $full Full standings row or compatible array.
	 * @param int    $year Season year.
	 * @param string $season_type API season type.
	 * @param string $division_override Division label from standings (or Postseason for schedule fallback).
	 * @return array
	 */
	private function map_team_record_to_archive_stats( $full, $year, $season_type, $division_override ) {
		$w   = (int) ( $full['wins'] ?? 0 );
		$l   = (int) ( $full['losses'] ?? 0 );
		$pct = (string) ( $full['winning_percentage'] ?? '.000' );
		$rd  = (string) ( $full['run_differential'] ?? '0' );
		$gb  = (string) ( $full['games_back'] ?? '-' );
		$lt  = (string) ( $full['last_ten'] ?? '—' );
		$stk = (string) ( $full['streak'] ?? '-' );

		if ( 'springTraining' === $season_type ) {
			$division = 'Cactus League';
			$rank_raw = (string) ( $full['division_rank'] ?? '' );
			$rank     = ctype_digit( $rank_raw )
				? $this->format_ordinal( (int) $rank_raw ) . ' (Cactus)'
				: ( $rank_raw ? $rank_raw . ' (Cactus)' : '—' );
		} elseif ( 'postseason' === $season_type ) {
			$division = $division_override ? $division_override : 'Postseason';
			$rank_raw = (string) ( $full['division_rank'] ?? '' );
			$rank     = ctype_digit( $rank_raw ) ? $this->format_ordinal( (int) $rank_raw ) . ' (' . $division . ')' : 'Postseason';
		} else {
			$division = (string) ( $full['division_name'] ?? 'AL Central' );
			$rank_raw = (string) ( $full['division_rank'] ?? '' );
			$rank     = ctype_digit( $rank_raw )
				? $this->format_ordinal( (int) $rank_raw ) . ' (' . $division . ')'
				: ( $full['summary'] ?? '—' );
		}

		return array(
			'wins'        => $w,
			'losses'      => $l,
			'pct'         => $pct,
			'run_diff'    => $rd,
			'rank'        => $rank,
			'division'    => $division,
			'games_back'  => $gb,
			'last_ten'    => $lt,
			'streak'      => $stk,
			'season'      => (int) $year,
			'season_type' => $season_type,
		);
	}

	/**
	 * Return the sequential season game number for the first game of a series.
	 *
	 * Counts all Guardians games of the appropriate type that appear in the
	 * schedule from the start of the season through the day before $date.
	 * Returns that count + 1, i.e. "the next game number."
	 *
	 * Only supported for regularSeason ('R') and springTraining ('S').
	 * Returns WP_Error for other season types or on API failure.
	 *
	 * @param string $date        Series start date in Ymd or Y-m-d format.
	 * @param string $season_type API season type ('regularSeason', 'springTraining').
	 * @return int|WP_Error
	 */
	public function get_season_game_number_for_date( $date, $season_type = 'regularSeason' ) {
		$settings = $this->get_season_settings( $season_type );
		$year     = (int) $settings['season'];

		// Normalize Ymd → Y-m-d.
		if ( preg_match( '/^\d{8}$/', $date ) ) {
			$date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		}

		if ( ! $date || false === strtotime( $date ) ) {
			return new WP_Error( 'basebelles_api_bad_date', 'Invalid series start date.' );
		}

		$game_type_map = array(
			'regularSeason'  => 'R',
			'springTraining' => 'S',
		);

		if ( ! isset( $game_type_map[ $season_type ] ) ) {
			return new WP_Error( 'basebelles_api_unsupported', 'Auto-detect not supported for this season type.' );
		}

		$game_type  = $game_type_map[ $season_type ];
		$start_date = $year . ( 'springTraining' === $season_type ? '-02-01' : '-03-01' );
		$end_date   = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );

		if ( $end_date < $start_date ) {
			return 1;
		}

		$data = $this->request_json(
			'schedule',
			array(
				'sportId'   => 1,
				'teamId'    => self::GUARDIANS_TEAM_ID,
				'season'    => $year,
				'startDate' => $start_date,
				'endDate'   => $end_date,
				'gameType'  => $game_type,
			),
			self::API_CACHE_TTL
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Postponed and cancelled games are not played on that date: MLB returns the
		// postponed entry AND the makeup game (on its rescheduled date) as separate
		// objects, so counting the postponed entry would inflate the game number by one
		// per postponement. The makeup game is counted on the date it is actually played.
		$skip_states = array( 'Postponed', 'Cancelled' );

		$game_count = 0;
		if ( ! empty( $data['dates'] ) && is_array( $data['dates'] ) ) {
			foreach ( $data['dates'] as $day ) {
				foreach ( $day['games'] ?? array() as $game ) {
					$detailed_state = $game['status']['detailedState'] ?? '';
					if ( in_array( $detailed_state, $skip_states, true ) ) {
						continue;
					}
					++$game_count;
				}
			}
		}

		return $game_count + 1;
	}

	/**
	 * Fetch and normalize the Guardians schedule for a single day.
	 *
	 * @param string $date Optional YYYY-MM-DD date.
	 * @return array|WP_Error
	 */
	public function get_guardians_today_game( $date = '' ) {
		$date = $this->normalize_game_date( $date );

		// TEMPORARY: If the date is in the past, return today.
		// We may change this later, but for now we want to skip over
		// blocks of time like the ASG.
		if ( strtotime( $date ) < strtotime( wp_date( 'Y-m-d' ) ) ) {
			$date = wp_date( 'Y-m-d' );
		}

		$data = $this->request_json(
			'schedule',
			array(
				'sportId' => 1,
				'date'    => $date,
				'teamId'  => self::GUARDIANS_TEAM_ID,
				'hydrate' => 'team,linescore,probablePitcher,broadcasts',
			)
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$timezone      = wp_timezone();
		$day_timestamp = strtotime( $date . ' 12:00:00' );
		$games         = $data['dates'][0]['games'] ?? array();

		if ( empty( $games ) || ! is_array( $games ) ) {
			return array(
				'day_date' => $day_timestamp ? wp_date( 'D n/j', $day_timestamp, $timezone ) : '',
				'off_day'  => true,
				'games'    => array(),
			);
		}

		$settings         = $this->get_season_settings();
		$normalized_games = array();

		foreach ( $games as $game ) {
			if ( ! is_array( $game ) ) {
				continue;
			}

			$normalized_games[] = $this->normalize_scheduled_game( $game, $settings['season'] );
		}

		usort(
			$normalized_games,
			function ( $left, $right ) {
				return (int) ( $left['sort_time'] ?? 0 ) <=> (int) ( $right['sort_time'] ?? 0 );
			}
		);

		foreach ( $normalized_games as $index => $game ) {
			$normalized_games[ $index ]['show_label'] = count( $normalized_games ) > 1;
		}

		return array(
			'day_date' => $day_timestamp ? wp_date( 'D n/j', $day_timestamp, $timezone ) : '',
			'off_day'  => false,
			'games'    => $normalized_games,
		);
	}

	/**
	 * Normalize one scheduled game from the MLB schedule payload.
	 *
	 * @param array $game One game payload.
	 * @param int   $season Season year.
	 * @return array
	 */
	private function normalize_scheduled_game( $game, $season ) {
		$away_team         = $this->normalize_game_team( $game['teams']['away'] ?? array() );
		$home_team         = $this->normalize_game_team( $game['teams']['home'] ?? array() );
		$game_date         = $game['gameDate'] ?? '';
		$is_home           = self::GUARDIANS_TEAM_ID === (int) ( $game['teams']['home']['team']['id'] ?? 0 );
		$game_status       = $game['status']['abstractGameState'] ?? 'Live';
		$detailed_status   = array(
			'state'  => $game['status']['detailedState'] ?? '',
			'reason' => ( isset( $game['status']['reason'] ) ) ? $game['status']['reason'] : '',
		);
		$is_preview        = 'Preview' === $game_status;
		$has_scores        = in_array( $game_status, array( 'Live', 'Final' ), true );
		$timezone          = wp_timezone();
		$timestamp         = $game_date ? strtotime( $game_date ) : false;
		$is_traditional_dh = ( 'Y' === ( $game['doubleHeader'] ?? 'N' ) );
		$is_game_two       = ( 2 === (int) ( $game['gameNumber'] ?? 1 ) );

		$game_time = $timestamp ? wp_date( 'g:i A T', $timestamp, $timezone ) : '';
		if ( $is_preview && $is_traditional_dh && $is_game_two ) {
			$game_time = 'TBD (30m after G1)';
		}

		return array(
			'game_pk'        => (int) ( $game['gamePk'] ?? 0 ),
			'game_number'    => (int) ( $game['gameNumber'] ?? 1 ),
			'doubleheader'   => (string) ( $game['doubleHeader'] ?? 'N' ),
			'day_date'       => $timestamp ? wp_date( 'D n/j', $timestamp, $timezone ) : '',
			'game_time'      => $game_time,
			'status'         => $game_status,
			'detailed_state' => $detailed_status,
			'game_status'    => $game_status,
			'away_team'      => $away_team,
			'home_team'      => $home_team,
			'away_pitcher'   => $is_preview ? $this->get_pitcher_preview_data( $game['teams']['away']['probablePitcher']['id'] ?? 0, $season ) : array(),
			'home_pitcher'   => $is_preview ? $this->get_pitcher_preview_data( $game['teams']['home']['probablePitcher']['id'] ?? 0, $season ) : array(),
			'scores'         => $has_scores ? $this->get_game_scores( $game, $game_status, $detailed_status ) : array(),
			'broadcasts'     => $this->get_game_broadcasts( $is_home, $game['broadcasts'] ?? array() ),
			'sort_time'      => $timestamp ? $timestamp : 0,
			'show_label'     => false,
		);
	}

	/**
	 * Get the game broadcasts from the game payload.
	 *
	 * @param bool $is_home Whether the game is home or away.
	 * @param array $broadcasts The game broadcasts array.
	 * @return array
	 */
	private function get_game_broadcasts( $is_home, $broadcasts ) {
		$radio = array();
		$tv    = array();

		foreach ( $broadcasts as $broadcast ) {
			// If the game is home, only show the home broadcasts.
			if ( $is_home && 'home' !== $broadcast['homeAway'] ) {
				continue;
			}

			// If the game is away, only show the away broadcasts.
			if ( ! $is_home && 'away' !== $broadcast['homeAway'] ) {
				continue;
			}

			if ( in_array( $broadcast['type'], array( 'AM', 'FM' ), true ) ) {
				$radio[] = $broadcast['callSign'];
			}

			if ( 'TV' === $broadcast['type'] ) {
				$network = $broadcast['name'];

				if ( $broadcast['isNational'] && 'exclusive' === $broadcast['availability']['availabilityCode'] ) {
					$network .= ' (EXCLUSIVE)';
				}

				$tv[] = $network;
			}
		}

		return array(
			'radio' => $radio,
			'tv'    => $tv,
		);
	}

	/**
	 * Get the game scores from the game payload.
	 *
	 * @param array $game The game payload.
	 * @return array
	 */
	private function get_game_scores( $game, $game_status, $detailed_state = array() ) {
		$away_scores = (int) ( $game['teams']['away']['score'] ?? 0 );
		$home_scores = (int) ( $game['teams']['home']['score'] ?? 0 );
		$winner      = '';
		$final       = 'Final' === $game_status;

		if ( ! empty( $detailed_state ) && 'Postponed' === $detailed_state['state'] ) {
			$away_scores = '-';
			$home_scores = '-';
		}

		if ( $final ) {
			$away_status = $game['teams']['away']['isWinner'] ?? false;
			$home_status = $game['teams']['home']['isWinner'] ?? false;
			$inning      = (int) ( $game['linescore']['currentInning'] ?? 1 );
			$winner      = $away_status ? 'away' : ( $home_status ? 'home' : '' );
		} else {
			$inning_half = $game['linescore']['inningHalf'] ?? '';
			$inning_ord  = $game['linescore']['currentInningOrdinal'] ?? '';
			$inning      = $inning_half . ' of the ' . $inning_ord;
		}

		return array(
			'away'    => $away_scores,
			'home'    => $home_scores,
			'winner'  => $winner,
			'inning'  => $inning,
			'isFinal' => $final,
		);
	}

	/**
	 * Normalize a provided game date for the schedule API.
	 *
	 * @param string $date Input date.
	 * @return string
	 */
	private function normalize_game_date( $date ) {
		$date = trim( (string) $date );

		if ( '' === $date ) {
			return wp_date( 'Y-m-d' );
		}

		$ymd = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( $ymd instanceof DateTime ) {
			return $ymd->format( 'Y-m-d' );
		}

		$dmy = DateTime::createFromFormat( 'd/m/Y', $date );
		if ( $dmy instanceof DateTime ) {
			return $dmy->format( 'Y-m-d' );
		}

		$timestamp = strtotime( $date );

		return $timestamp ? wp_date( 'Y-m-d', $timestamp ) : wp_date( 'Y-m-d' );
	}

	/**
	 * Fetch Guardians transactions for the last TRANSACTIONS_LOOKBACK_DAYS calendar days, newest first.
	 *
	 * @param int $limit Max rows after sorting (clamped 1..TRANSACTIONS_DISPLAY_MAX).
	 * @return array<int, array<string, mixed>>|WP_Error Transaction rows from the MLB API.
	 */
	public function get_guardians_recent_transactions( $limit ) {
		$limit = (int) $limit;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > self::TRANSACTIONS_DISPLAY_MAX ) {
			$limit = self::TRANSACTIONS_DISPLAY_MAX;
		}

		$tz  = wp_timezone();
		$end = new DateTime( 'now', $tz );
		$end->setTime( 0, 0, 0 );
		$start = clone $end;
		$start->modify( '-' . ( self::TRANSACTIONS_LOOKBACK_DAYS - 1 ) . ' days' );

		$query = array(
			'teamId'    => self::GUARDIANS_TEAM_ID,
			'startDate' => $start->format( 'Y-m-d' ),
			'endDate'   => $end->format( 'Y-m-d' ),
		);

		$data = $this->request_json( 'transactions', $query, self::API_CACHE_TTL );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$transactions = isset( $data['transactions'] ) && is_array( $data['transactions'] ) ? $data['transactions'] : array();

		usort(
			$transactions,
			static function ( $a, $b ) {
				$da  = isset( $a['effectiveDate'] ) ? (string) $a['effectiveDate'] : ( isset( $a['date'] ) ? (string) $a['date'] : '' );
				$db  = isset( $b['effectiveDate'] ) ? (string) $b['effectiveDate'] : ( isset( $b['date'] ) ? (string) $b['date'] : '' );
				$cmp = strcmp( $db, $da );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return (int) ( $b['id'] ?? 0 ) <=> (int) ( $a['id'] ?? 0 );
			}
		);

		return array_slice( $transactions, 0, $limit );
	}

	/**
	 * Return true if any Guardians transaction carries today's date.
	 *
	 * Result is cached in a transient that expires at midnight so the API is
	 * hit at most once per calendar day.
	 *
	 * @return bool
	 */
	public function has_transactions_today() {
		$cached = get_transient( 'bb_transactions_today' );
		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$has_today    = false;
		$today        = wp_date( 'Y-m-d' );
		$transactions = $this->get_guardians_recent_transactions( self::TRANSACTIONS_DISPLAY_MAX );

		if ( ! is_wp_error( $transactions ) && ! empty( $transactions ) ) {
			foreach ( $transactions as $row ) {
				$date = (string) ( $row['effectiveDate'] ?? $row['date'] ?? '' );
				if ( str_starts_with( $date, $today ) ) {
					$has_today = true;
					break;
				}
			}
		}

		$now = current_datetime()->getTimestamp();
		$ttl = strtotime( 'tomorrow', $now ) - $now;
		set_transient( 'bb_transactions_today', $has_today ? '1' : '0', $ttl );

		return $has_today;
	}

	/**
	 * Request JSON data from the MLB API.
	 *
	 * @param string $endpoint The endpoint path.
	 * @param array  $query_args The query arguments.
	 * @param int    $expiration Cache TTL.
	 * @return array|WP_Error
	 */
	public function request_json( $endpoint, $query_args = array(), $expiration = self::API_CACHE_TTL ) {
		$url        = $this->build_url( $endpoint, $query_args );
		$expiration = max( 1, (int) $expiration );
		// Bucket by TTL window so data cannot stay fresh past the intended horizon if a persistent
		// object cache (e.g. Docket Cache) fails to enforce transient expiration.
		$cache_key = 'basebelles_api_' . md5( $url ) . '_' . (int) floor( time() / $expiration );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout'    => self::API_TIMEOUT,
				'user-agent' => 'Basebelles/' . ( Basebelles::$version ?? '1.0.2' ) . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $status_code || empty( $body ) ) {
			return new WP_Error( 'basebelles_api_http', 'The MLB API request failed.' );
		}

		$decoded = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'basebelles_api_decode', 'The MLB API response could not be decoded.' );
		}

		set_transient( $cache_key, $decoded, $expiration );

		return $decoded;
	}

	/**
	 * Build a URL for the MLB API.
	 *
	 * @param string $endpoint The endpoint path.
	 * @param array  $query_args The query arguments.
	 * @return string
	 */
	public function build_url( $endpoint, $query_args = array() ) {
		$endpoint = ltrim( $endpoint, '/' );
		$base_url = trailingslashit( self::API_BASE_URL ) . $endpoint;

		return add_query_arg( $query_args, $base_url );
	}

	/**
	 * Get a value from ACF options with a fallback.
	 *
	 * @param string $field_name The option field name.
	 * @param mixed  $fallback The fallback value.
	 * @return mixed
	 */
	private function get_option_value( $field_name, $fallback = '' ) {
		$value = get_option( 'options_' . $field_name, null );

		if ( null !== $value && '' !== $value ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Find one team inside the standings records payload.
	 *
	 * @param array $records The standings records array.
	 * @param int   $team_id The team ID.
	 * @return array
	 */
	private function find_team_record( $records, $team_id ) {
		foreach ( $records as $record_group ) {
			if ( empty( $record_group['teamRecords'] ) || ! is_array( $record_group['teamRecords'] ) ) {
				continue;
			}

			foreach ( $record_group['teamRecords'] as $team_record ) {
				if ( (int) ( $team_record['team']['id'] ?? 0 ) === (int) $team_id ) {
					if ( ! isset( $team_record['division'] ) && isset( $record_group['division'] ) ) {
						$team_record['division'] = $record_group['division'];
					}

					return $team_record;
				}
			}
		}

		return array();
	}

	/**
	 * Normalize one team from the schedule endpoint.
	 *
	 * @param array $team_data Team schedule payload.
	 * @return array
	 */
	private function normalize_game_team( $team_data ) {
		$team       = $team_data['team'] ?? array();
		$team_info  = $this->get_team_info_by_abbreviation( $team['abbreviation'] ?? '' );
		$league     = $team_data['leagueRecord'] ?? array();
		$short_name = $team_info['short_name'] ?? ( $team['teamName'] ?? $team['name'] ?? '' );
		$logo_url   = $this->get_team_logo_url( $team_info['slug'] ?? '' );

		return array(
			'name'         => $team['name'] ?? '',
			'short_name'   => $short_name,
			'abbreviation' => $team['abbreviation'] ?? '',
			'logo_url'     => $logo_url,
			'record'       => (int) ( $league['wins'] ?? 0 ) . '-' . (int) ( $league['losses'] ?? 0 ),
		);
	}

	/**
	 * Convert division rank into the display summary.
	 *
	 * @param array  $team_record The team record.
	 * @param string $division_name The division name.
	 * @return string
	 */
	private function format_summary( $team_record, $division_name ) {
		$rank = (string) ( $team_record['divisionRank'] ?? '' );

		if ( ctype_digit( $rank ) ) {
			return $this->format_ordinal( (int) $rank ) . ' in the ' . $division_name;
		}

		return 'In the ' . $division_name;
	}

	/**
	 * Convert a numeric value to an ordinal.
	 *
	 * @param int $number The number to convert.
	 * @return string
	 */
	private function format_ordinal( $number ) {
		$number = absint( $number );

		if ( 0 === $number ) {
			return '';
		}

		$mod_100 = $number % 100;

		if ( $mod_100 >= 11 && $mod_100 <= 13 ) {
			return $number . 'th';
		}

		switch ( $number % 10 ) {
			case 1:
				return $number . 'st';
			case 2:
				return $number . 'nd';
			case 3:
				return $number . 'rd';
			default:
				return $number . 'th';
		}
	}

	/**
	 * Get a split record in W-L format.
	 *
	 * @param array  $split_records The split records array.
	 * @param string $type The split type to find.
	 * @return string
	 */
	private function format_split_record( $split_records, $type ) {
		foreach ( $split_records as $record ) {
			if ( ( $record['type'] ?? '' ) !== $type ) {
				continue;
			}

			$wins   = (int) ( $record['wins'] ?? 0 );
			$losses = (int) ( $record['losses'] ?? 0 );

			if ( 0 === $wins && 0 === $losses ) {
				$split_result = '—';
			} else {
				$split_result = $wins . '-' . $losses;
			}

			return $split_result;
		}

		return '0-0';
	}

	/**
	 * Normalize the games-back display.
	 *
	 * @param string $value Games back value from the API.
	 * @return string
	 */
	private function normalize_games_back( $value ) {
		$value = (string) $value;

		return '' === $value ? '-' : $value;
	}

	/**
	 * Format run differential for display.
	 *
	 * @param int $value The run differential.
	 * @return string
	 */
	private function format_run_differential( $value ) {
		$value = (int) $value;

		if ( $value > 0 ) {
			return '+' . $value;
		}

		return (string) $value;
	}

	/**
	 * Get normalized probable pitcher data for previews.
	 *
	 * @param int $player_id MLB player ID.
	 * @param int $season Season year.
	 * @return array
	 */
	private function get_pitcher_preview_data( $player_id, $season ) {
		$player_id = (int) $player_id;
		$season    = (int) $season;

		if ( $player_id <= 0 ) {
			return array();
		}

		$player = $this->request_json(
			'people/' . $player_id,
			array(
				'hydrate' => sprintf( 'stats(group=[pitching],type=[season],season=%d)', $season ),
			)
		);

		if ( is_wp_error( $player ) || empty( $player['people'][0] ) ) {
			return array();
		}

		$person = $player['people'][0];
		$split  = $person['stats'][0]['splits'][0]['stat'] ?? array();
		$hand   = strtoupper( (string) ( $person['pitchHand']['code'] ?? '' ) );

		return array(
			'id'     => $player_id,
			'name'   => $person['fullName'] ?? '',
			'hand'   => $hand ? $hand . 'HP' : '',
			'record' => (int) ( $split['wins'] ?? 0 ) . '-' . (int) ( $split['losses'] ?? 0 ),
			'era'    => (string) ( $split['era'] ?? '--' ),
			'url'    => 'https://www.mlb.com/player/' . $player_id,
		);
	}

	/**
	 * Return team metadata for a given list.json key (e.g. 'yankees').
	 *
	 * @param string $key The team key as stored in list.json.
	 * @return array Team data array, or empty array if not found.
	 */
	public function get_team( $key ) {
		$teams = $this->get_team_info_list();
		return $teams[ $key ] ?? array();
	}

	/**
	 * Return team metadata by matching the taxonomy_term slug (e.g. 'new-york-yankees').
	 *
	 * @param string $taxonomy_slug The term slug used in the team taxonomy.
	 * @return array Team data array (with 'slug' key added), or empty array if not found.
	 */
	public function get_team_by_taxonomy_slug( $taxonomy_slug ) {
		foreach ( $this->get_team_info_list() as $key => $team ) {
			if ( ( $team['taxonomy_term'] ?? '' ) === $taxonomy_slug ) {
				$team['slug'] = $key;
				return $team;
			}
		}
		return array();
	}

	/**
	 * Get team info from the local metadata file.
	 *
	 * @param string $abbreviation Team abbreviation.
	 * @return array
	 */
	private function get_team_info_by_abbreviation( $abbreviation ) {
		$teams        = $this->get_team_info_list();
		$abbreviation = strtoupper( (string) $abbreviation );

		foreach ( $teams as $slug => $team ) {
			if ( strtoupper( (string) ( $team['abbreviation'] ?? '' ) ) === $abbreviation ) {
				$team['slug'] = $slug;
				return $team;
			}
		}

		return array();
	}

	/**
	 * Load team metadata from disk.
	 *
	 * @return array
	 */
	private function get_team_info_list() {
		static $teams = null;

		if ( null !== $teams ) {
			return $teams;
		}

		$file = dirname( __DIR__, 1 ) . '/team-info/list.json';

		if ( ! file_exists( $file ) ) {
			$teams = array();
			return $teams;
		}

		$data  = file_get_contents( $file );
		$list  = $data ? json_decode( $data, true ) : array();
		$teams = is_array( $list ) ? $list : array();

		return $teams;
	}

	/**
	 * Get a local team logo URL by slug.
	 *
	 * @param string $slug Team slug.
	 * @return string
	 */
	private function get_team_logo_url( $slug ) {
		if ( '' === $slug ) {
			return '';
		}

		$file = dirname( __DIR__, 1 ) . '/team-info/logos/' . $slug . '.png';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		return plugin_dir_url( __DIR__ ) . 'team-info/logos/' . $slug . '.png';
	}
}
