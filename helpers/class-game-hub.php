<?php
/**
 * Pure helpers behind the Game Hub block's cards: the division table, the wild card line, last
 * and next games, the Guardians news feed and the Spring Training countdown.
 *
 * Like Basebelles_Postseason, no HTTP and no WordPress -- Basebelles_API fetches the raw
 * payloads and hands them in here, so the shaping is unit-testable against fixtures.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Game_Hub {

	/** Only links under this path count as news; the feed also carries puzzles, podcasts and prospect pages. */
	const NEWS_PATH = '/guardians/news/';

	/** Hosts a news link may live on. */
	const NEWS_HOSTS = array( 'www.mlb.com', 'mlb.com' );

	/** Game states that mean "not played on this date". */
	const SKIP_STATES = array( 'Postponed', 'Cancelled' );

	/**
	 * The team's division, in standings order, from a standings payload hydrated with team.
	 *
	 * @param array $records The standings payload's `records` array.
	 * @param int   $team_id Our team.
	 * @return array{name: string, rows: array[], wild_card: string} Empty rows when the team isn't found.
	 */
	public static function division_table( array $records, int $team_id ): array {
		$table = array(
			'name'      => '',
			'rows'      => array(),
			'wild_card' => '',
		);

		foreach ( $records as $group ) {
			$team_records = is_array( $group['teamRecords'] ?? null ) ? $group['teamRecords'] : array();
			$ids          = array_map(
				static function ( $team_record ) {
					return (int) ( $team_record['team']['id'] ?? 0 );
				},
				$team_records
			);

			if ( ! in_array( $team_id, $ids, true ) ) {
				continue;
			}

			usort(
				$team_records,
				static function ( $left, $right ) {
					return (int) ( $left['divisionRank'] ?? 99 ) <=> (int) ( $right['divisionRank'] ?? 99 );
				}
			);

			$table['name'] = (string) ( $group['division']['nameShort'] ?? $group['division']['name'] ?? '' );

			foreach ( $team_records as $team_record ) {
				$is_us = (int) ( $team_record['team']['id'] ?? 0 ) === $team_id;
				$gb    = (string) ( $team_record['divisionGamesBack'] ?? '' );

				$table['rows'][] = array(
					'id'           => (int) ( $team_record['team']['id'] ?? 0 ),
					'abbreviation' => (string) ( $team_record['team']['abbreviation'] ?? '' ),
					'name'         => (string) ( $team_record['team']['teamName'] ?? $team_record['team']['name'] ?? '' ),
					'wins'         => (int) ( $team_record['wins'] ?? 0 ),
					'losses'       => (int) ( $team_record['losses'] ?? 0 ),
					'games_back'   => ( '' === $gb || '-' === $gb ) ? '—' : $gb,
					'last_ten'     => self::split_record( $team_record['records']['splitRecords'] ?? array(), 'lastTen' ),
					'streak'       => strtoupper( (string) ( $team_record['streak']['streakCode'] ?? '' ) ),
					'is_us'        => $is_us,
					'logo_url'     => '',
				);

				if ( $is_us ) {
					$table['wild_card'] = self::wild_card_line( $team_record );
				}
			}

			break;
		}

		return $table;
	}

	/**
	 * "AL Wild Card: 2nd · +1.5", "AL Wild Card: 3rd · last spot", "AL Wild Card: 2.5 GB".
	 * Empty for a division leader, who isn't in the wild card race.
	 *
	 * MLB reports the top two wild cards' lead over the cutoff as "+x", the third spot as "-",
	 * and everyone below by games back.
	 *
	 * @param array $team_record One `teamRecords` entry.
	 * @return string
	 */
	public static function wild_card_line( array $team_record ): string {
		if ( ! empty( $team_record['divisionLeader'] ) || ! empty( $team_record['divisionChamp'] ) ) {
			return '';
		}

		$rank = (int) ( $team_record['wildCardRank'] ?? 0 );
		$gb   = (string) ( $team_record['wildCardGamesBack'] ?? '' );

		if ( $rank <= 0 ) {
			return '';
		}

		if ( $rank <= 3 ) {
			$lead = ( '' === $gb || '-' === $gb ) ? 'last spot' : $gb;

			return 'AL Wild Card: ' . self::ordinal( $rank ) . ' · ' . $lead;
		}

		return 'AL Wild Card: ' . $gb . ' GB';
	}

	/**
	 * The most recent finished game before a date.
	 *
	 * @param array  $dates   Schedule `dates` array (hydrated with team, for abbreviations).
	 * @param int    $team_id Our team.
	 * @param string $before  Y-m-d; games on or after it are ignored.
	 * @return array{result: string, score: string, where: string, opponent: string, date: string, game_pk: int}|array Empty when there's none.
	 */
	public static function last_game( array $dates, int $team_id, string $before ): array {
		$latest = null;
		$key    = '';

		foreach ( $dates as $day ) {
			foreach ( $day['games'] ?? array() as $game ) {
				$official = (string) ( $game['officialDate'] ?? $day['date'] ?? '' );

				if ( '' === $official || $official >= $before || 'Final' !== ( $game['status']['abstractGameState'] ?? '' ) ) {
					continue;
				}

				if ( in_array( (string) ( $game['status']['detailedState'] ?? '' ), self::SKIP_STATES, true ) ) {
					continue;
				}

				// Date, then game number, so the nightcap of a doubleheader wins.
				$candidate = $official . '#' . (int) ( $game['gameNumber'] ?? 1 );

				if ( $candidate > $key ) {
					$key    = $candidate;
					$latest = $game;
				}
			}
		}

		if ( null === $latest ) {
			return array();
		}

		$home  = (int) ( $latest['teams']['home']['team']['id'] ?? 0 ) === $team_id;
		$us    = $latest['teams'][ $home ? 'home' : 'away' ] ?? array();
		$them  = $latest['teams'][ $home ? 'away' : 'home' ] ?? array();
		$runs  = (int) ( $us['score'] ?? 0 );
		$other = (int) ( $them['score'] ?? 0 );

		if ( ! empty( $us['isWinner'] ) ) {
			$result = 'W';
		} elseif ( ! empty( $them['isWinner'] ) ) {
			$result = 'L';
		} else {
			$result = 'T';
		}

		return array(
			'result'   => $result,
			'score'    => $runs . '-' . $other,
			'where'    => $home ? 'vs' : '@',
			'opponent' => (string) ( $them['team']['abbreviation'] ?? $them['team']['name'] ?? '' ),
			'date'     => (string) ( $latest['officialDate'] ?? '' ),
			'game_pk'  => (int) ( $latest['gamePk'] ?? 0 ),
		);
	}

	/**
	 * "Wed–Thu vs CWS · Fri @ DET" for the next few games after a date.
	 *
	 * Back-to-back games against the same opponent in the same park fold into one range. A game
	 * more than six days out gets its date as well as its weekday, so the All-Star break doesn't
	 * read as "Fri" meaning this Friday.
	 *
	 * @param array  $dates   Schedule `dates` array (hydrated with team).
	 * @param int    $team_id Our team.
	 * @param string $after   Y-m-d; only games after it count.
	 * @param int    $limit   How many games to cover.
	 * @return string Empty when nothing is scheduled.
	 */
	public static function next_games_line( array $dates, int $team_id, string $after, int $limit = 3 ): string {
		$games = array();

		foreach ( $dates as $day ) {
			foreach ( $day['games'] ?? array() as $game ) {
				$official = (string) ( $game['officialDate'] ?? $day['date'] ?? '' );

				if ( '' === $official || $official <= $after ) {
					continue;
				}

				if ( in_array( (string) ( $game['status']['detailedState'] ?? '' ), self::SKIP_STATES, true ) ) {
					continue;
				}

				$home    = (int) ( $game['teams']['home']['team']['id'] ?? 0 ) === $team_id;
				$them    = $game['teams'][ $home ? 'away' : 'home' ]['team'] ?? array();
				$games[] = array(
					'date'     => $official,
					'sort'     => $official . '#' . (int) ( $game['gameNumber'] ?? 1 ),
					'where'    => $home ? 'vs' : '@',
					'opponent' => (string) ( $them['abbreviation'] ?? $them['name'] ?? '' ),
				);
			}
		}

		usort(
			$games,
			static function ( $left, $right ) {
				return strcmp( $left['sort'], $right['sort'] );
			}
		);

		$games  = array_slice( $games, 0, max( 1, $limit ) );
		$groups = array();

		foreach ( $games as $game ) {
			$last = count( $groups ) - 1;

			if ( $last >= 0 && $groups[ $last ]['where'] === $game['where'] && $groups[ $last ]['opponent'] === $game['opponent'] ) {
				if ( ! in_array( $game['date'], $groups[ $last ]['dates'], true ) ) {
					$groups[ $last ]['dates'][] = $game['date'];
				}
				continue;
			}

			$groups[] = array(
				'where'    => $game['where'],
				'opponent' => $game['opponent'],
				'dates'    => array( $game['date'] ),
			);
		}

		$parts = array();

		foreach ( $groups as $group ) {
			$first = self::day_label( reset( $group['dates'] ), $after );
			$days  = 1 === count( $group['dates'] ) ? $first : $first . '–' . self::day_label( end( $group['dates'] ), $after );

			$parts[] = $days . ' ' . $group['where'] . ' ' . $group['opponent'];
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Headlines from the MLB.com Guardians RSS feed, newest first, news stories only.
	 *
	 * @param string $xml   Raw RSS.
	 * @param int    $limit Most items to return.
	 * @return array<int, array{title: string, url: string, timestamp: int}>
	 */
	public static function parse_news( string $xml, int $limit = 5 ): array {
		if ( '' === trim( $xml ) || ! function_exists( 'simplexml_load_string' ) ) {
			return array();
		}

		$previous = libxml_use_internal_errors( true );
		$feed     = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $feed || ! isset( $feed->channel->item ) ) {
			return array();
		}

		$items = array();

		foreach ( $feed->channel->item as $item ) {
			$url   = trim( (string) $item->link );
			$title = trim( html_entity_decode( (string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$host  = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
			$path  = (string) parse_url( $url, PHP_URL_PATH );

			if ( '' === $title || ! in_array( $host, self::NEWS_HOSTS, true ) || 0 !== strpos( $path, self::NEWS_PATH ) ) {
				continue;
			}

			if ( isset( $items[ $url ] ) ) {
				continue;
			}

			$timestamp = strtotime( (string) $item->pubDate );

			$items[ $url ] = array(
				'title'     => $title,
				'url'       => $url,
				'timestamp' => false === $timestamp ? 0 : (int) $timestamp,
			);
		}

		$items = array_values( $items );

		// The feed isn't in date order; pinned evergreen pages sit among the day's stories.
		usort(
			$items,
			static function ( $left, $right ) {
				return $right['timestamp'] <=> $left['timestamp'];
			}
		);

		return array_slice( $items, 0, max( 1, $limit ) );
	}

	/**
	 * Whole days from one Y-m-d to another, or null when the target is unset, unparseable or past.
	 *
	 * @param string $target Y-m-d.
	 * @param string $today  Y-m-d.
	 * @return int|null
	 */
	public static function days_until( string $target, string $today ): ?int {
		$utc  = new DateTimeZone( 'UTC' );
		$to   = DateTimeImmutable::createFromFormat( '!Y-m-d', $target, $utc );
		$from = DateTimeImmutable::createFromFormat( '!Y-m-d', $today, $utc );

		if ( false === $to || false === $from || $to < $from ) {
			return null;
		}

		return (int) $from->diff( $to )->days;
	}

	/**
	 * The first date in a schedule `dates` payload on or after today, or ''.
	 *
	 * @param array  $dates Schedule `dates` array.
	 * @param string $today Y-m-d.
	 * @return string
	 */
	public static function first_date_from( array $dates, string $today ): string {
		$first = '';

		foreach ( $dates as $day ) {
			$date = (string) ( $day['date'] ?? '' );

			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $date >= $today && ( '' === $first || $date < $first ) ) {
				$first = $date;
			}
		}

		return $first;
	}

	/**
	 * "Wed", or "Fri 7/17" when it's more than six days after $after.
	 *
	 * @param string $date  Y-m-d.
	 * @param string $after Y-m-d.
	 * @return string
	 */
	private static function day_label( string $date, string $after ): string {
		$utc = new DateTimeZone( 'UTC' );
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $utc );

		if ( false === $day ) {
			return $date;
		}

		$gap = self::days_until( $date, $after );

		return ( null !== $gap && $gap > 6 ) ? $day->format( 'D n/j' ) : $day->format( 'D' );
	}

	/**
	 * A split record as "W-L", or '—' when it's missing.
	 *
	 * @param array  $split_records `records.splitRecords`.
	 * @param string $type          Split type, e.g. lastTen.
	 * @return string
	 */
	private static function split_record( $split_records, string $type ): string {
		foreach ( (array) $split_records as $record ) {
			if ( ( $record['type'] ?? '' ) === $type ) {
				return (int) ( $record['wins'] ?? 0 ) . '-' . (int) ( $record['losses'] ?? 0 );
			}
		}

		return '—';
	}

	/**
	 * 1st, 2nd, 3rd, 4th...
	 *
	 * @param int $number Positive integer.
	 * @return string
	 */
	private static function ordinal( int $number ): string {
		$mod_100 = $number % 100;

		if ( $mod_100 >= 11 && $mod_100 <= 13 ) {
			return $number . 'th';
		}

		$suffix = array( 1 => 'st', 2 => 'nd', 3 => 'rd' );

		return $number . ( $suffix[ $number % 10 ] ?? 'th' );
	}
}
