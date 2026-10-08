<?php
/**
 * Postseason status for the standings ticker.
 *
 * MLB has no postseason standings table, so this works the state out from two things the API
 * does have: the regular-season standings (for seeds, and so for the bye) and the team's own
 * postseason schedule (for the round, the opponent, the series score and each game's result).
 *
 * Pure PHP on purpose -- no HTTP, no WordPress -- so the branching (bye, between rounds,
 * eliminated, champion) is unit-testable against fixture payloads. Basebelles_API does the
 * fetching and hands the raw arrays in here.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Postseason {

	/** Postseason game types, in round order: Wild Card, Division Series, LCS, World Series. */
	const ROUND_ORDER = array( 'F', 'D', 'L', 'W' );

	/** Seeds that skip the Wild Card Series (the top two division winners in each league). */
	const BYE_SEEDS = 2;

	/** Short round names, for column labels ("ALDS: Won 3-1"). The site only follows the AL. */
	const ROUND_SHORT = array(
		'F' => 'Wild Card',
		'D' => 'ALDS',
		'L' => 'ALCS',
		'W' => 'World Series',
	);

	/** Full round names, used when MLB's own seriesDescription isn't available yet. */
	const ROUND_FULL = array(
		'F' => 'AL Wild Card Series',
		'D' => 'AL Division Series',
		'L' => 'AL Championship Series',
		'W' => 'World Series',
	);

	/** Game states that mean "not played on this date" -- a makeup entry replaces them. */
	const SKIP_STATES = array( 'Postponed', 'Cancelled' );

	/**
	 * Work out playoff seeds for one league from a regular-season standings payload.
	 *
	 * MLB doesn't publish seeds, but they follow directly from the standings: the three division
	 * winners are seeds 1-3 in league order, the three wild cards are 4-6 in wild card order.
	 * Ranking the division winners by MLB's own leagueRank, rather than re-sorting by winning
	 * percentage, means MLB's tiebreakers decide a tie and we don't have to.
	 *
	 * @param array $records The standings payload's `records` array.
	 * @return array<int, int> Team ID => seed.
	 */
	public static function compute_seeds( array $records ): array {
		$leaders = array();
		$wild    = array();

		foreach ( $records as $group ) {
			foreach ( $group['teamRecords'] ?? array() as $team_record ) {
				$team_id = (int) ( $team_record['team']['id'] ?? 0 );

				if ( $team_id <= 0 ) {
					continue;
				}

				if ( ! empty( $team_record['divisionChamp'] ) || ! empty( $team_record['divisionLeader'] ) ) {
					$leaders[] = array( $team_id, (int) ( $team_record['leagueRank'] ?? 99 ) );
					continue;
				}

				$wild_rank = (int) ( $team_record['wildCardRank'] ?? 0 );

				if ( $wild_rank >= 1 && $wild_rank <= 3 ) {
					$wild[] = array( $team_id, $wild_rank );
				}
			}
		}

		$by_rank = static function ( $left, $right ) {
			return $left[1] <=> $right[1];
		};

		usort( $leaders, $by_rank );
		usort( $wild, $by_rank );

		$seeds = array();
		$seed  = 1;

		foreach ( array_slice( $leaders, 0, 3 ) as $leader ) {
			$seeds[ $leader[0] ] = $seed++;
		}

		$seed = 4;

		foreach ( array_slice( $wild, 0, 3 ) as $wild_card ) {
			$seeds[ $wild_card[0] ] = $seed++;
		}

		return $seeds;
	}

	/**
	 * Build the postseason status from the team's postseason schedule.
	 *
	 * States:
	 * - not_qualified:  no seed and no postseason games. The ticker falls back to standings.
	 * - awaiting:       seeded, but no series scheduled yet (a bye team during the Wild Card).
	 * - in_series:      a series is under way, or scheduled and not yet decided.
	 * - between_rounds: won the last series, next opponent not set yet.
	 * - eliminated:     lost the last series.
	 * - champion:       won the World Series.
	 *
	 * @param array  $dates    The schedule payload's `dates` array (gameType F,D,L,W).
	 * @param array  $seeds    Team ID => seed, from compute_seeds() (both leagues, for the WS).
	 * @param string $today    Y-m-d in the game time zone.
	 * @param int    $team_id  Team to report on.
	 * @param string $timezone Time zone game times are shown in.
	 * @return array
	 */
	public static function build_status( array $dates, array $seeds, string $today, int $team_id, string $timezone = 'America/New_York' ): array {
		$series = array();

		foreach ( self::group_games_by_round( $dates ) as $game_type => $games ) {
			$series[ $game_type ] = self::build_series( $game_type, $games, $seeds, $today, $team_id, $timezone );
		}

		$seed          = isset( $seeds[ $team_id ] ) ? (int) $seeds[ $team_id ] : null;
		$played_wild   = isset( $series['F'] );
		$later_rounds  = array_diff_key( $series, array( 'F' => true ) );
		$seed_says_bye = null !== $seed && $seed <= self::BYE_SEEDS;

		// The seed is the primary signal for the bye: it's known as soon as the regular season
		// ends, before a single Wild Card game is on the schedule. The schedule is the check.
		// If they disagree, trust the games (they're what actually happened) and flag it.
		if ( $played_wild ) {
			$had_bye = false;
		} elseif ( null !== $seed ) {
			$had_bye = $seed_says_bye;
		} else {
			$had_bye = ! empty( $later_rounds );
		}

		$seed_mismatch = ( $played_wild && $seed_says_bye )
			|| ( ! $played_wild && null !== $seed && ! $seed_says_bye && ! empty( $later_rounds ) );

		$status = array(
			'state'             => 'not_qualified',
			'seed'              => $seed,
			'had_bye'           => $had_bye,
			'seed_mismatch'     => $seed_mismatch,
			'wild_card_label'   => self::wild_card_label( $had_bye, $series['F'] ?? null ),
			'current'           => null,
			'next_round'        => '',
			'postseason_record' => self::postseason_record( $series ),
			'series'            => $series,
		);

		if ( empty( $series ) ) {
			if ( null === $seed ) {
				return $status;
			}

			$status['state']      = 'awaiting';
			$status['next_round'] = $had_bye ? 'D' : 'F';

			return $status;
		}

		$current           = end( $series );
		$status['current'] = $current;

		if ( ! $current['complete'] ) {
			$status['state'] = 'in_series';
		} elseif ( ! $current['won'] ) {
			$status['state'] = 'eliminated';
		} elseif ( 'W' === $current['game_type'] ) {
			$status['state'] = 'champion';
		} else {
			$status['state']      = 'between_rounds';
			$status['next_round'] = self::next_round( $current['game_type'] );
		}

		return $status;
	}

	/**
	 * Flatten the schedule into game type => series game number => game, in round order.
	 *
	 * A postponed game stays on the schedule under its original date alongside the makeup game,
	 * both carrying the same seriesGameNumber, so the played (or still-to-be-played) entry wins.
	 *
	 * @param array $dates Schedule `dates` array.
	 * @return array<string, array<int, array>>
	 */
	private static function group_games_by_round( array $dates ): array {
		$rounds = array();

		foreach ( $dates as $day ) {
			foreach ( $day['games'] ?? array() as $game ) {
				$game_type = (string) ( $game['gameType'] ?? '' );

				if ( ! in_array( $game_type, self::ROUND_ORDER, true ) ) {
					continue;
				}

				$number   = (int) ( $game['seriesGameNumber'] ?? 0 );
				$skipped  = in_array( (string) ( $game['status']['detailedState'] ?? '' ), self::SKIP_STATES, true );
				$existing = $rounds[ $game_type ][ $number ] ?? null;

				if ( null !== $existing && $skipped ) {
					continue;
				}

				$rounds[ $game_type ][ $number ] = $game;
			}
		}

		$ordered = array();

		foreach ( self::ROUND_ORDER as $game_type ) {
			if ( ! empty( $rounds[ $game_type ] ) ) {
				ksort( $rounds[ $game_type ] );
				$ordered[ $game_type ] = $rounds[ $game_type ];
			}
		}

		return $ordered;
	}

	/**
	 * Summarise one series.
	 *
	 * @param string $game_type F, D, L or W.
	 * @param array  $games     Series game number => raw game.
	 * @param array  $seeds     Team ID => seed.
	 * @param string $today     Y-m-d.
	 * @param int    $team_id   Our team.
	 * @param string $timezone  Display time zone.
	 * @return array
	 */
	private static function build_series( string $game_type, array $games, array $seeds, string $today, int $team_id, string $timezone ): array {
		$first          = reset( $games );
		$total          = 0;
		$description    = '';
		$opponent       = array();
		$guardians      = array();
		$guardians_wins = 0;
		$opponent_wins  = 0;

		foreach ( $games as $game ) {
			$total = max( $total, (int) ( $game['gamesInSeries'] ?? 0 ) );

			if ( '' === $description && ! empty( $game['seriesDescription'] ) ) {
				$description = (string) $game['seriesDescription'];
			}

			if ( 'Final' !== ( $game['status']['abstractGameState'] ?? '' ) ) {
				continue;
			}

			$sides = self::sides( $game, $team_id );

			if ( ! empty( $game['teams'][ $sides['us'] ]['isWinner'] ) ) {
				++$guardians_wins;
			} elseif ( ! empty( $game['teams'][ $sides['them'] ]['isWinner'] ) ) {
				++$opponent_wins;
			}
		}

		$sides     = self::sides( $first, $team_id );
		$guardians = self::team( $first['teams'][ $sides['us'] ]['team'] ?? array(), $seeds );
		$opponent  = self::team( $first['teams'][ $sides['them'] ]['team'] ?? array(), $seeds );

		if ( $total < 1 ) {
			$total = count( $games );
		}

		$wins_needed = intdiv( $total, 2 ) + 1;
		$won         = $guardians_wins >= $wins_needed;
		$complete    = $won || $opponent_wins >= $wins_needed;

		return array(
			'game_type'       => $game_type,
			'round'           => '' !== $description ? $description : self::ROUND_FULL[ $game_type ],
			'round_short'     => self::ROUND_SHORT[ $game_type ],
			'games_in_series' => $total,
			'wins_needed'     => $wins_needed,
			'guardians'       => $guardians,
			'opponent'        => $opponent,
			'guardians_wins'  => $guardians_wins,
			'opponent_wins'   => $opponent_wins,
			'complete'        => $complete,
			'won'             => $won,
			'status_label'    => self::status_label( $guardians, $opponent, $guardians_wins, $opponent_wins, $complete ),
			'result_label'    => self::result_label( $opponent, $guardians_wins, $opponent_wins, $won ),
			'games'           => self::game_cells( $games, $total, $complete, $today, $team_id, $opponent['abbreviation'], $timezone ),
		);
	}

	/**
	 * Which side of a game is ours.
	 *
	 * @param array $game    Raw game.
	 * @param int   $team_id Our team.
	 * @return array{us: string, them: string}
	 */
	private static function sides( array $game, int $team_id ): array {
		$home = $team_id === (int) ( $game['teams']['home']['team']['id'] ?? 0 );

		return array(
			'us'   => $home ? 'home' : 'away',
			'them' => $home ? 'away' : 'home',
		);
	}

	/**
	 * Minimal team shape for display. The API decorates it with a logo URL.
	 *
	 * @param array $team  Raw `team` object.
	 * @param array $seeds Team ID => seed.
	 * @return array
	 */
	private static function team( array $team, array $seeds ): array {
		$id = (int) ( $team['id'] ?? 0 );

		return array(
			'id'           => $id,
			'name'         => (string) ( $team['name'] ?? '' ),
			'abbreviation' => (string) ( $team['abbreviation'] ?? '' ),
			'seed'         => isset( $seeds[ $id ] ) ? (int) $seeds[ $id ] : null,
			'logo_url'     => '',
		);
	}

	/**
	 * "CWS leads 2-1", "Tied 1-1", "CLE wins 3-1".
	 *
	 * @param array $guardians      Our team.
	 * @param array $opponent       Their team.
	 * @param int   $guardians_wins Our wins.
	 * @param int   $opponent_wins  Their wins.
	 * @param bool  $complete       Whether the series is decided.
	 * @return string
	 */
	private static function status_label( array $guardians, array $opponent, int $guardians_wins, int $opponent_wins, bool $complete ): string {
		if ( $guardians_wins === $opponent_wins ) {
			return 'Tied ' . $guardians_wins . '-' . $opponent_wins;
		}

		$verb = $complete ? 'wins' : 'leads';

		return $guardians_wins > $opponent_wins
			? $guardians['abbreviation'] . ' ' . $verb . ' ' . $guardians_wins . '-' . $opponent_wins
			: $opponent['abbreviation'] . ' ' . $verb . ' ' . $opponent_wins . '-' . $guardians_wins;
	}

	/**
	 * "Won 3-2 vs CWS" / "Lost 1-3 vs CWS", always from our side.
	 *
	 * @param array $opponent       Their team.
	 * @param int   $guardians_wins Our wins.
	 * @param int   $opponent_wins  Their wins.
	 * @param bool  $won            Whether we won the series.
	 * @return string
	 */
	private static function result_label( array $opponent, int $guardians_wins, int $opponent_wins, bool $won ): string {
		return ( $won ? 'Won ' : 'Lost ' ) . $guardians_wins . '-' . $opponent_wins . ' vs ' . $opponent['abbreviation'];
	}

	/**
	 * One cell per scheduled game slot (1..gamesInSeries), for the ticker's second row.
	 *
	 * @param array  $games      Series game number => raw game.
	 * @param int    $total      Games in the series.
	 * @param bool   $complete   Whether the series is decided.
	 * @param string $today      Y-m-d.
	 * @param int    $team_id    Our team.
	 * @param string $opp_abbr   Opponent abbreviation.
	 * @param string $timezone   Display time zone.
	 * @return array[]
	 */
	private static function game_cells( array $games, int $total, bool $complete, string $today, int $team_id, string $opp_abbr, string $timezone ): array {
		$cells    = array();
		$next_set = false;

		for ( $number = 1; $number <= $total; $number++ ) {
			$game = $games[ $number ] ?? null;
			$cell = array(
				'number' => $number,
				'label'  => 'G' . $number,
				'value'  => $complete ? 'Not needed' : 'TBD',
				'state'  => $complete ? 'not_needed' : 'scheduled',
				'next'   => false,
			);

			if ( null !== $game ) {
				$sides          = self::sides( $game, $team_id );
				$cell['label'] .= ' · ' . ( 'home' === $sides['us'] ? 'vs ' : '@' ) . $opp_abbr;
				$cell           = array_merge( $cell, self::game_result( $game, $sides, $complete, $today, $timezone ) );
			}

			if ( ! $complete && ! $next_set && in_array( $cell['state'], array( 'scheduled', 'live' ), true ) ) {
				$cell['next'] = true;
				$next_set     = true;
			}

			$cells[] = $cell;
		}

		return $cells;
	}

	/**
	 * Value and state for one game cell.
	 *
	 * @param array  $game     Raw game.
	 * @param array  $sides    From sides().
	 * @param bool   $complete Whether the series is decided.
	 * @param string $today    Y-m-d.
	 * @param string $timezone Display time zone.
	 * @return array{value: string, state: string}
	 */
	private static function game_result( array $game, array $sides, bool $complete, string $today, string $timezone ): array {
		$abstract = (string) ( $game['status']['abstractGameState'] ?? '' );
		$detailed = (string) ( $game['status']['detailedState'] ?? '' );
		$us       = (int) ( $game['teams'][ $sides['us'] ]['score'] ?? 0 );
		$them     = (int) ( $game['teams'][ $sides['them'] ]['score'] ?? 0 );

		if ( 'Final' === $abstract ) {
			$won = ! empty( $game['teams'][ $sides['us'] ]['isWinner'] );

			return array(
				'value' => ( $won ? 'W ' : 'L ' ) . $us . '-' . $them,
				'state' => $won ? 'win' : 'loss',
			);
		}

		if ( 'Live' === $abstract ) {
			$half   = (string) ( $game['linescore']['inningHalf'] ?? '' );
			$inning = (string) ( $game['linescore']['currentInningOrdinal'] ?? '' );
			$where  = trim( $half . ' ' . $inning );

			return array(
				'value' => $us . '-' . $them . ( '' !== $where ? ' · ' . $where : '' ),
				'state' => 'live',
			);
		}

		// Never played, and now never will be.
		if ( $complete ) {
			return array(
				'value' => 'Not needed',
				'state' => 'not_needed',
			);
		}

		if ( in_array( $detailed, self::SKIP_STATES, true ) ) {
			return array(
				'value' => $detailed,
				'state' => 'scheduled',
			);
		}

		$value = self::when( $game, $today, $timezone );

		if ( 'Y' === ( $game['ifNecessary'] ?? 'N' ) ) {
			$value .= ' · if nec.';
		}

		return array(
			'value' => $value,
			'state' => 'scheduled',
		);
	}

	/**
	 * "Today 8:00 PM" for today's game, "Sat 10/10" for later ones.
	 *
	 * @param array  $game     Raw game.
	 * @param string $today    Y-m-d.
	 * @param string $timezone Display time zone.
	 * @return string
	 */
	private static function when( array $game, string $today, string $timezone ): string {
		$official = (string) ( $game['officialDate'] ?? '' );
		$tbd      = ! empty( $game['status']['startTimeTBD'] );

		try {
			$tz    = new DateTimeZone( $timezone );
			$start = ! empty( $game['gameDate'] ) ? new DateTimeImmutable( (string) $game['gameDate'] ) : null;
			$start = $start ? $start->setTimezone( $tz ) : null;
			$day   = '' !== $official ? new DateTimeImmutable( $official . ' 12:00:00', $tz ) : $start;
		} catch ( Exception $e ) {
			return 'TBD';
		}

		if ( null === $day ) {
			return 'TBD';
		}

		if ( $official === $today ) {
			return ( $start && ! $tbd ) ? 'Today ' . $start->format( 'g:i A' ) : 'Today';
		}

		return $day->format( 'D n/j' );
	}

	/**
	 * Wild Card column: "Bye", or how the Wild Card Series went once it's over.
	 *
	 * @param bool       $had_bye   Whether we skipped the round.
	 * @param array|null $wild_card The Wild Card series, if we played one.
	 * @return string Empty while the Wild Card Series is the one being played.
	 */
	private static function wild_card_label( bool $had_bye, $wild_card ): string {
		if ( $had_bye ) {
			return 'Bye';
		}

		if ( is_array( $wild_card ) && $wild_card['complete'] ) {
			return $wild_card['result_label'];
		}

		return '';
	}

	/**
	 * Overall postseason W-L, across every round.
	 *
	 * @param array $series Game type => series.
	 * @return string
	 */
	private static function postseason_record( array $series ): string {
		$wins   = 0;
		$losses = 0;

		foreach ( $series as $one ) {
			$wins   += $one['guardians_wins'];
			$losses += $one['opponent_wins'];
		}

		return $wins . '-' . $losses;
	}

	/**
	 * The round after this one.
	 *
	 * @param string $game_type F, D or L.
	 * @return string
	 */
	private static function next_round( string $game_type ): string {
		$index = array_search( $game_type, self::ROUND_ORDER, true );

		return false === $index ? '' : ( self::ROUND_ORDER[ $index + 1 ] ?? '' );
	}
}
