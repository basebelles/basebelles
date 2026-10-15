<?php
/**
 * The season header's timeline: spring training, regular season and postseason as one bar,
 * each part as wide as it is long on the calendar, with a pin for today.
 *
 * Pure logic, no fetching and no markup: blocks/season-header/render.php gathers the dates,
 * records and postseason status and draws what this returns.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Season_Timeline {

	/**
	 * Phase key => [name, label under the bar, season type that means "this phase is on",
	 * name mid-sentence]. Spring's label is short because its segment is narrow.
	 */
	const PHASES = array(
		'spring_training' => array( 'Spring Training', 'Spring', 'springTraining', 'Spring training' ),
		'regular_season'  => array( 'Regular Season', 'Regular season', 'regularSeason', 'Regular season' ),
		'post_season'     => array( 'Post Season', 'Postseason', 'postSeason', 'Postseason' ),
	);

	/**
	 * Share of the bar each phase gets when its dates aren't set yet: roughly a real season
	 * (about a month, six months, a month), so a half-filled-in Season Settings still looks right.
	 */
	const FALLBACK_SHARE = array(
		'spring_training' => 12,
		'regular_season'  => 74,
		'post_season'     => 14,
	);

	/**
	 * Build the timeline.
	 *
	 * @param array             $phases     Phase key => [start => ?DateTimeImmutable, end => ?DateTimeImmutable,
	 *                                      record => string, standing => string, link => string].
	 * @param string            $type       Basebelles_Season::current_type().
	 * @param DateTimeImmutable $today      Today, at midnight in the site time zone.
	 * @param array|null        $postseason Basebelles_API::get_postseason_status(), or null.
	 * @return array{segments: array, pin: ?float, pin_side: string, now: string, months: array}
	 */
	public static function build( array $phases, string $type, DateTimeImmutable $today, ?array $postseason = null ): array {
		$range    = self::range( $phases );
		$segments = array();
		$current  = null;

		foreach ( self::PHASES as $key => $meta ) {
			$phase = $phases[ $key ] ?? array();
			$start = $phase['start'] ?? null;
			$end   = $phase['end'] ?? null;
			$state = self::state( $key, $meta[2], $type, $start, $end, $today );

			$segment = array(
				'key'    => $key,
				'name'   => $meta[0],
				'short'  => $meta[1],
				'phrase' => $meta[3],
				'link'   => (string) ( $phase['link'] ?? '' ),
				'state'  => $state,
				'width'  => self::width( $key, $start, $end, $range ),
				'fill'   => 'current' === $state ? self::progress( $start, $end, $today ) : ( 'done' === $state ? 100.0 : 0.0 ),
				'result' => self::result( $key, $state, $phase, $postseason ),
			);

			if ( 'current' === $state ) {
				$current = $segment + array( 'start' => $start );
			}

			$segments[] = $segment;
		}

		$pin = null;

		// The pin only means something while a phase is under way and today is inside the bar.
		if ( null !== $current && null !== $range && $current['fill'] > 0 ) {
			$pin = self::position( $today, $range );
		}

		return array(
			'segments' => $segments,
			'pin'      => $pin,
			// Near either end the "Today" tag would hang off the bar, so it hugs the inside edge.
			'pin_side' => null === $pin ? '' : ( $pin > 88 ? 'end' : ( $pin < 12 ? 'start' : '' ) ),
			'now'      => self::now_line( $type, $current, $phases, $postseason ),
			'months'   => null === $range ? array() : self::months( $range ),
		);
	}

	/**
	 * Whether a phase is done, current or upcoming.
	 *
	 * The season type decides, the same way the old columns did, so the timeline agrees with
	 * Today's Game and the standings. Only the off season falls back to the dates, since it can
	 * mean "this season is over" or "next season hasn't started".
	 *
	 * @param string                 $key        Phase key.
	 * @param string                 $phase_type Season type that means this phase is on.
	 * @param string                 $type       Current season type.
	 * @param DateTimeImmutable|null $start      Phase start.
	 * @param DateTimeImmutable|null $end        Phase end.
	 * @param DateTimeImmutable      $today      Today.
	 * @return string done|current|upcoming
	 */
	private static function state( string $key, string $phase_type, string $type, ?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $today ): string {
		if ( 'offSeason' === $type ) {
			return ( null !== $end && $end < $today ) ? 'done' : 'upcoming';
		}

		$order   = array_keys( self::PHASES );
		$by_type = array_combine( array_column( self::PHASES, 2 ), $order );
		$on      = $by_type[ $type ] ?? 'regular_season';
		$diff    = array_search( $key, $order, true ) <=> array_search( $on, $order, true );

		return array( -1 => 'done', 0 => 'current', 1 => 'upcoming' )[ $diff ];
	}

	/**
	 * The bar's first and last day, or null when spring's start or the postseason's end isn't set.
	 *
	 * @param array $phases Phases.
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null
	 */
	private static function range( array $phases ): ?array {
		$first = $phases['spring_training']['start'] ?? null;
		$last  = $phases['post_season']['end'] ?? null;

		if ( ! $first instanceof DateTimeImmutable || ! $last instanceof DateTimeImmutable || $last <= $first ) {
			return null;
		}

		return array( $first, $last );
	}

	/**
	 * Whole days from one date to another.
	 *
	 * @param DateTimeImmutable $from From.
	 * @param DateTimeImmutable $to   To.
	 * @return int
	 */
	private static function days( DateTimeImmutable $from, DateTimeImmutable $to ): int {
		return (int) $from->diff( $to )->format( '%r%a' );
	}

	/**
	 * A phase's share of the bar, in percent.
	 *
	 * With every date known, each phase is as wide as its own days. The few off days between
	 * phases go to the phase before, so the three widths still add up to the whole bar.
	 *
	 * @param string                 $key   Phase key.
	 * @param DateTimeImmutable|null $start Phase start.
	 * @param DateTimeImmutable|null $end   Phase end.
	 * @param array|null             $range Bar range.
	 * @return float
	 */
	private static function width( string $key, ?DateTimeImmutable $start, ?DateTimeImmutable $end, ?array $range ): float {
		if ( null === $range || null === $start || null === $end ) {
			return (float) self::FALLBACK_SHARE[ $key ];
		}

		$total = max( 1, self::days( $range[0], $range[1] ) + 1 );

		return round( max( 1, self::days( $start, $end ) + 1 ) / $total * 100, 1 );
	}

	/**
	 * How far through a phase today is, 0 to 100.
	 *
	 * @param DateTimeImmutable|null $start Phase start.
	 * @param DateTimeImmutable|null $end   Phase end.
	 * @param DateTimeImmutable      $today Today.
	 * @return float
	 */
	private static function progress( ?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $today ): float {
		if ( null === $start || null === $end ) {
			return 50.0;
		}

		$length = max( 1, self::days( $start, $end ) + 1 );
		$done   = self::days( $start, $today ) + 1;

		return round( min( 100, max( 0, $done / $length * 100 ) ), 1 );
	}

	/**
	 * Where a date falls on the bar, in percent.
	 *
	 * @param DateTimeImmutable $date  Date.
	 * @param array             $range Bar range.
	 * @return float
	 */
	private static function position( DateTimeImmutable $date, array $range ): float {
		$total = max( 1, self::days( $range[0], $range[1] ) + 1 );

		return round( min( 100, max( 0, ( self::days( $range[0], $date ) + 0.5 ) / $total * 100 ) ), 1 );
	}

	/**
	 * The line under a segment: the record (plus the standing, if set) once a phase has started,
	 * the dates before that.
	 *
	 * The postseason uses its Standing field if set ("AL champions"), then the live status
	 * ("ALDS W 3-2"), then the record field.
	 *
	 * @param string     $key        Phase key.
	 * @param string     $state      Phase state.
	 * @param array      $phase      Phase data.
	 * @param array|null $postseason Postseason status.
	 * @return string
	 */
	private static function result( string $key, string $state, array $phase, ?array $postseason ): string {
		if ( 'upcoming' === $state ) {
			return self::date_span( $phase['start'] ?? null, $phase['end'] ?? null );
		}

		$standing = trim( (string) ( $phase['standing'] ?? '' ) );

		if ( 'post_season' === $key ) {
			// The Standing field is the editor's word on how it ended, so it beats the live result.
			if ( '' !== $standing ) {
				return $standing;
			}

			$from_status = self::postseason_result( $state, $postseason );

			if ( '' !== $from_status ) {
				return $from_status;
			}
		}

		$line = self::record_line( $phase );

		return '' !== $line ? $line : self::date_span( $phase['start'] ?? null, $phase['end'] ?? null );
	}

	/**
	 * "85 – 77 · AL Central champs": the record and, where there is one, the standing.
	 *
	 * @param array $phase Phase data.
	 * @return string
	 */
	private static function record_line( array $phase ): string {
		return implode(
			' · ',
			array_filter(
				array(
					trim( (string) ( $phase['record'] ?? '' ) ),
					trim( (string) ( $phase['standing'] ?? '' ) ),
				),
				'strlen'
			)
		);
	}

	/**
	 * The postseason segment's line. While it's on, the last round already decided (the round
	 * in progress is in the "now" line). Once it's over, how it ended.
	 *
	 * @param string     $state      Postseason segment state.
	 * @param array|null $postseason Postseason status.
	 * @return string
	 */
	private static function postseason_result( string $state, ?array $postseason ): string {
		if ( empty( $postseason ) || empty( $postseason['state'] ) ) {
			return '';
		}

		$status = (string) $postseason['state'];

		if ( 'not_qualified' === $status ) {
			return 'done' === $state ? 'Missed the postseason' : '';
		}

		if ( 'champion' === $status ) {
			return 'World Series champions';
		}

		// The rounds behind us, most recent last. Mid-series that's 'road', which leaves out the
		// round being played; otherwise 'rounds', which includes a round that just ended. A bye
		// is only worth showing if it's all there is.
		$key   = 'in_series' === $status ? 'road' : 'rounds';
		$legs  = array_values( (array) ( $postseason[ $key ] ?? array() ) );
		$shown = null;

		foreach ( $legs as $leg ) {
			if ( 'Bye' !== ( $leg['result'] ?? '' ) || null === $shown ) {
				$shown = $leg;
			}
		}

		// "Won 3-2 vs CWS" => "W 3-2": the segment is narrow, and the opponent is in the posts.
		if ( null !== $shown ) {
			$result = (string) ( $shown['result'] ?? '' );

			if ( preg_match( '/^(Won|Lost)\s+(\d+-\d+)/', $result, $match ) ) {
				$result = ( 'Won' === $match[1] ? 'W ' : 'L ' ) . $match[2];
			}

			return trim( ( $shown['round'] ?? '' ) . ' ' . $result );
		}

		return '';
	}

	/**
	 * "Feb 20 – Mar 23", or "TBD" for an unset end.
	 *
	 * @param DateTimeImmutable|null $start Start.
	 * @param DateTimeImmutable|null $end   End.
	 * @return string
	 */
	private static function date_span( ?DateTimeImmutable $start, ?DateTimeImmutable $end ): string {
		$from = $start ? $start->format( 'M j' ) : 'TBD';
		$to   = $end ? $end->format( 'M j' ) : 'TBD';

		return $from === $to ? $from : $from . ' – ' . $to;
	}

	/**
	 * The line at the top right: what's happening now.
	 *
	 * @param string     $type       Season type.
	 * @param array|null $current    The current segment, if any.
	 * @param array      $phases     Phases.
	 * @param array|null $postseason Postseason status.
	 * @return string
	 */
	private static function now_line( string $type, ?array $current, array $phases, ?array $postseason ): string {
		if ( null === $current ) {
			return 'Season complete · See you in spring';
		}

		// Set to spring training, but camp hasn't opened.
		if ( 0.0 === $current['fill'] ) {
			$start = $current['start'] ?? null;

			return $current['phrase'] . ( $start ? ' starts ' . $start->format( 'M j' ) : ' is coming up' );
		}

		if ( 'post_season' !== $current['key'] ) {
			$record = self::record_line( $phases[ $current['key'] ] ?? array() );

			return 'Now: ' . $current['phrase'] . ( '' !== $record ? ' · ' . $record : '' );
		}

		$status = (string) ( $postseason['state'] ?? '' );
		$round  = is_array( $postseason['current'] ?? null ) ? $postseason['current'] : null;

		switch ( $status ) {
			case 'in_series':
				return 'Now: ' . ( $round['round_short'] ?? 'Postseason' ) . ' · ' . ( $round['status_label'] ?? '' );
			case 'between_rounds':
			case 'awaiting':
				$next = Basebelles_Postseason::ROUND_SHORT[ (string) ( $postseason['next_round'] ?? '' ) ] ?? '';

				return 'Now: Postseason' . ( '' !== $next ? ' · ' . $next . ' next' : '' );
			case 'champion':
				return 'World Series champions!';
			case 'eliminated':
			case 'not_qualified':
				return 'Season complete · See you in spring';
		}

		return 'Now: Postseason';
	}

	/**
	 * Month ticks: the first of each month inside the bar, as [label, position].
	 *
	 * @param array $range Bar range.
	 * @return array<int, array{0: string, 1: float}>
	 */
	private static function months( array $range ): array {
		$ticks = array();
		$month = $range[0]->modify( 'first day of next month' );

		while ( $month <= $range[1] ) {
			$ticks[] = array( $month->format( 'M' ), self::position( $month, $range ) );
			$month   = $month->modify( 'first day of next month' );
		}

		return $ticks;
	}
}
