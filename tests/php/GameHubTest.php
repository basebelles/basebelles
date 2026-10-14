<?php
/**
 * Tests for Basebelles_Game_Hub, the pure shaping behind the Game Hub's cards.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class GameHubTest extends TestCase {

	/*
	 * -----------------------------------------------------------------------
	 * Division table
	 * -----------------------------------------------------------------------
	 */

	/**
	 * One standings teamRecords entry, hydrated with team.
	 */
	private function team_record( int $id, string $abbr, string $name, int $rank, int $w, int $l, string $gb, array $extra = array() ): array {
		return array_merge(
			array(
				'team'              => array(
					'id'           => $id,
					'abbreviation' => $abbr,
					'teamName'     => $name,
				),
				'divisionRank'      => (string) $rank,
				'wins'              => $w,
				'losses'            => $l,
				'divisionGamesBack' => $gb,
				'streak'            => array( 'streakCode' => 'w2' ),
				'records'           => array(
					'splitRecords' => array(
						array(
							'type'   => 'lastTen',
							'wins'   => 7,
							'losses' => 3,
						),
					),
				),
			),
			$extra
		);
	}

	private function central(): array {
		return array(
			// Another division first, to prove the right group is picked.
			array(
				'division'    => array( 'nameShort' => 'AL East' ),
				'teamRecords' => array( $this->team_record( 139, 'TB', 'Rays', 1, 90, 72, '-' ) ),
			),
			array(
				'division'    => array( 'nameShort' => 'AL Central' ),
				// Out of order on purpose: the table sorts by divisionRank.
				'teamRecords' => array(
					$this->team_record( 142, 'DET', 'Tigers', 3, 64, 60, '8.0' ),
					$this->team_record( 145, 'CWS', 'White Sox', 1, 72, 52, '-', array( 'divisionLeader' => true ) ),
					$this->team_record( 114, 'CLE', 'Guardians', 2, 68, 56, '4.0', array( 'wildCardRank' => '2', 'wildCardGamesBack' => '+1.5' ) ),
				),
			),
		);
	}

	public function test_the_division_is_the_one_holding_our_team(): void {
		$table = Basebelles_Game_Hub::division_table( $this->central(), 114 );

		$this->assertSame( 'AL Central', $table['name'] );
		$this->assertSame( array( 'CWS', 'CLE', 'DET' ), array_column( $table['rows'], 'abbreviation' ) );
	}

	public function test_rows_carry_the_columns_the_card_shows(): void {
		$rows = Basebelles_Game_Hub::division_table( $this->central(), 114 )['rows'];

		$this->assertSame( 'White Sox', $rows[0]['name'] );
		$this->assertSame( '—', $rows[0]['games_back'] );
		$this->assertSame( '4.0', $rows[1]['games_back'] );
		$this->assertSame( '7-3', $rows[1]['last_ten'] );
		$this->assertSame( 'W2', $rows[1]['streak'] );
		$this->assertTrue( $rows[1]['is_us'] );
		$this->assertFalse( $rows[0]['is_us'] );
	}

	public function test_the_wild_card_line_comes_from_our_row(): void {
		$table = Basebelles_Game_Hub::division_table( $this->central(), 114 );

		$this->assertSame( 'AL Wild Card: 2nd · +1.5', $table['wild_card'] );
	}

	public function test_a_team_not_in_the_standings_gets_no_rows(): void {
		$table = Basebelles_Game_Hub::division_table( $this->central(), 999 );

		$this->assertSame( array(), $table['rows'] );
	}

	public function test_a_division_leader_has_no_wild_card_line(): void {
		$this->assertSame( '', Basebelles_Game_Hub::wild_card_line( array( 'divisionLeader' => true, 'wildCardGamesBack' => '-' ) ) );
	}

	public function test_the_third_wild_card_holds_the_last_spot(): void {
		$this->assertSame( 'AL Wild Card: 3rd · last spot', Basebelles_Game_Hub::wild_card_line( array( 'wildCardRank' => '3', 'wildCardGamesBack' => '-' ) ) );
	}

	public function test_outside_the_spots_shows_games_back(): void {
		$this->assertSame( 'AL Wild Card: 2.5 GB', Basebelles_Game_Hub::wild_card_line( array( 'wildCardRank' => '5', 'wildCardGamesBack' => '2.5' ) ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Last and next games
	 * -----------------------------------------------------------------------
	 */

	/**
	 * One raw regular-season game against a given opponent.
	 */
	private function game( string $date, string $opp, bool $cle_home, string $state = 'Preview', ?int $cle = null, ?int $them = null, int $number = 1, string $detailed = '' ): array {
		$us    = array( 'team' => array( 'id' => 114, 'abbreviation' => 'CLE' ) );
		$other = array( 'team' => array( 'id' => 200, 'abbreviation' => $opp ) );

		if ( null !== $cle ) {
			$us['score']       = $cle;
			$other['score']    = $them;
			$us['isWinner']    = $cle > $them;
			$other['isWinner'] = $them > $cle;
		}

		return array(
			'officialDate' => $date,
			'gameNumber'   => $number,
			'gamePk'       => (int) str_replace( '-', '', $date ) * 10 + $number,
			'status'       => array(
				'abstractGameState' => $state,
				'detailedState'     => '' !== $detailed ? $detailed : $state,
			),
			'teams'        => array(
				'home' => $cle_home ? $us : $other,
				'away' => $cle_home ? $other : $us,
			),
		);
	}

	private function dates( array $games ): array {
		$by_date = array();

		foreach ( $games as $game ) {
			$by_date[ $game['officialDate'] ]['date']    = $game['officialDate'];
			$by_date[ $game['officialDate'] ]['games'][] = $game;
		}

		return array_values( $by_date );
	}

	public function test_the_last_game_is_the_latest_final_before_today(): void {
		$dates = $this->dates(
			array(
				$this->game( '2026-08-17', 'KC', false, 'Final', 3, 5 ),
				$this->game( '2026-08-18', 'KC', false, 'Final', 6, 2 ),
				// Today's game, already final, isn't the "last" one.
				$this->game( '2026-08-19', 'CWS', true, 'Final', 1, 0 ),
			)
		);

		$last = Basebelles_Game_Hub::last_game( $dates, 114, '2026-08-19' );

		$this->assertSame( 'W', $last['result'] );
		$this->assertSame( '6-2', $last['score'] );
		$this->assertSame( '@', $last['where'] );
		$this->assertSame( 'KC', $last['opponent'] );
	}

	public function test_a_doubleheader_nightcap_is_the_last_game(): void {
		$dates = $this->dates(
			array(
				$this->game( '2026-08-18', 'DET', true, 'Final', 4, 1, 1 ),
				$this->game( '2026-08-18', 'DET', true, 'Final', 2, 3, 2 ),
			)
		);

		$last = Basebelles_Game_Hub::last_game( $dates, 114, '2026-08-19' );

		$this->assertSame( 'L', $last['result'] );
		$this->assertSame( '2-3', $last['score'] );
		$this->assertSame( 'vs', $last['where'] );
	}

	public function test_no_finished_game_means_no_last_game(): void {
		$dates = $this->dates( array( $this->game( '2026-08-18', 'DET', true, 'Final', 4, 1, 1, 'Postponed' ) ) );

		$this->assertSame( array(), Basebelles_Game_Hub::last_game( $dates, 114, '2026-08-19' ) );
	}

	public function test_a_series_folds_into_a_day_range(): void {
		// 2026-08-19 is a Wednesday.
		$dates = $this->dates(
			array(
				$this->game( '2026-08-19', 'CWS', true ),
				$this->game( '2026-08-20', 'CWS', true ),
				$this->game( '2026-08-21', 'DET', false ),
				$this->game( '2026-08-22', 'DET', false ),
			)
		);

		$this->assertSame( 'Wed–Thu vs CWS · Fri @ DET', Basebelles_Game_Hub::next_games_line( $dates, 114, '2026-08-18', 3 ) );
	}

	public function test_a_single_game_is_just_its_day(): void {
		$dates = $this->dates( array( $this->game( '2026-08-19', 'CWS', true ) ) );

		$this->assertSame( 'Wed vs CWS', Basebelles_Game_Hub::next_games_line( $dates, 114, '2026-08-18', 3 ) );
	}

	public function test_a_doubleheader_counts_once_in_the_range(): void {
		$dates = $this->dates(
			array(
				$this->game( '2026-08-19', 'CWS', true, 'Preview', null, null, 1 ),
				$this->game( '2026-08-19', 'CWS', true, 'Preview', null, null, 2 ),
				$this->game( '2026-08-20', 'CWS', true ),
			)
		);

		$this->assertSame( 'Wed–Thu vs CWS', Basebelles_Game_Hub::next_games_line( $dates, 114, '2026-08-18', 3 ) );
	}

	public function test_postponed_games_and_today_are_skipped(): void {
		$dates = $this->dates(
			array(
				$this->game( '2026-08-18', 'KC', true ),
				$this->game( '2026-08-19', 'CWS', true, 'Preview', null, null, 1, 'Postponed' ),
				$this->game( '2026-08-21', 'DET', false ),
			)
		);

		$this->assertSame( 'Fri @ DET', Basebelles_Game_Hub::next_games_line( $dates, 114, '2026-08-18', 3 ) );
	}

	public function test_a_game_more_than_a_week_out_gets_its_date(): void {
		$dates = $this->dates( array( $this->game( '2026-07-17', 'DET', false ) ) );

		$this->assertSame( 'Fri 7/17 @ DET', Basebelles_Game_Hub::next_games_line( $dates, 114, '2026-07-10', 3 ) );
	}

	public function test_nothing_scheduled_is_an_empty_line(): void {
		$this->assertSame( '', Basebelles_Game_Hub::next_games_line( array(), 114, '2026-08-18', 3 ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * News feed
	 * -----------------------------------------------------------------------
	 */

	private function feed(): string {
		$item = static function ( string $title, string $link, string $date ) {
			return '<item><title><![CDATA[' . $title . ']]></title><link>' . $link . '</link><pubDate>' . $date . '</pubDate></item>';
		};

		return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Guardians News</title>'
			. $item( 'Guards come up big late to rally past Rays', 'https://www.mlb.com/guardians/news/guardians-win-alcs-game-1-2026', 'Tue, 13 Oct 2026 06:00:00 GMT' )
			. $item( 'Solve today\'s Guardians trivia puzzle', 'https://www.mlb.com/app/atbat/guardians/daily-walkoff', 'Tue, 03 Feb 2026 15:20:14 GMT' )
			. $item( 'Guardians\' Top 30 Prospects list', 'https://www.mlb.com/milb/prospects/2026/guardians/', 'Tue, 05 Mar 2024 21:00:00 GMT' )
			. $item( 'Podcast: Guards suddenly can\'t lose', 'https://mlb-cuts-diamond.mlb.com/FORGE/2026/asset.mp4', 'Tue, 13 Oct 2026 05:12:18 GMT' )
			. $item( 'Guardians-Rays ALCS Game 2 FAQ', 'https://www.mlb.com/guardians/news/guardians-vs-rays-alcs-game-2', 'Tue, 13 Oct 2026 20:29:00 GMT' )
			. $item( 'Guardians erase 0-2 hole &amp; punch ALCS ticket', 'https://www.mlb.com/guardians/news/guardians-win-alds-2026', 'Sun, 11 Oct 2026 07:22:00 GMT' )
			. $item( 'Guardians-Rays ALCS Game 2 FAQ', 'https://www.mlb.com/guardians/news/guardians-vs-rays-alcs-game-2', 'Tue, 13 Oct 2026 20:29:00 GMT' )
			. '</channel></rss>';
	}

	public function test_only_guardians_news_stories_are_kept(): void {
		$urls = array_column( Basebelles_Game_Hub::parse_news( $this->feed(), 10 ), 'url' );

		$this->assertCount( 3, $urls );

		foreach ( $urls as $url ) {
			$this->assertStringStartsWith( 'https://www.mlb.com/guardians/news/', $url );
		}
	}

	public function test_news_is_newest_first(): void {
		$titles = array_column( Basebelles_Game_Hub::parse_news( $this->feed(), 10 ), 'title' );

		$this->assertSame(
			array(
				'Guardians-Rays ALCS Game 2 FAQ',
				'Guards come up big late to rally past Rays',
				'Guardians erase 0-2 hole & punch ALCS ticket',
			),
			$titles
		);
	}

	public function test_news_respects_the_limit(): void {
		$this->assertCount( 2, Basebelles_Game_Hub::parse_news( $this->feed(), 2 ) );
	}

	public function test_a_broken_feed_is_no_news(): void {
		$this->assertSame( array(), Basebelles_Game_Hub::parse_news( '<rss><channel><item>', 5 ) );
		$this->assertSame( array(), Basebelles_Game_Hub::parse_news( '', 5 ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Countdown
	 * -----------------------------------------------------------------------
	 */

	public function test_days_until_spring_training(): void {
		$this->assertSame( 129, Basebelles_Game_Hub::days_until( '2027-02-19', '2026-10-13' ) );
		$this->assertSame( 0, Basebelles_Game_Hub::days_until( '2027-02-19', '2027-02-19' ) );
	}

	public function test_a_past_or_missing_date_has_no_countdown(): void {
		$this->assertNull( Basebelles_Game_Hub::days_until( '2026-02-19', '2026-10-13' ) );
		$this->assertNull( Basebelles_Game_Hub::days_until( '', '2026-10-13' ) );
		$this->assertNull( Basebelles_Game_Hub::days_until( 'TBD', '2026-10-13' ) );
	}

	public function test_the_first_spring_date_is_the_earliest_from_today(): void {
		$dates = array(
			array( 'date' => '2027-02-20' ),
			array( 'date' => '2027-02-19' ),
			array( 'date' => '2026-02-19' ),
		);

		$this->assertSame( '2027-02-19', Basebelles_Game_Hub::first_date_from( $dates, '2026-10-13' ) );
		$this->assertSame( '', Basebelles_Game_Hub::first_date_from( $dates, '2027-03-01' ) );
	}
}
