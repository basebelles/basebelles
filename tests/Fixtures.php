<?php
/**
 * Test fixtures shaped like the real API payloads.
 *
 * Field names and value formats mirror Basebelles_API: pre-signed run differentials ("+11"),
 * uppercased streak codes ("L2"), integer wins/losses, and 'scores' populated only once a game
 * is Live or Final. If the API's normalisation changes shape, these are the place to update.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

class Fixtures {

	/**
	 * One normalized game, as Basebelles_API::normalize_scheduled_game() returns it.
	 * Defaults to a scheduled game two hours out.
	 *
	 * @param array $overrides Keys to replace.
	 * @return array
	 */
	public static function game( array $overrides = array() ): array {
		return array_merge(
			array(
				'game_pk'        => 777001,
				'game_number'    => 1,
				'doubleheader'   => 'N',
				'day_date'       => 'Fri 9/4',
				'game_time'      => '2:10 PM EDT',
				'status'         => 'Preview',
				'game_status'    => 'Preview',
				'detailed_state' => array(
					'state'  => 'Scheduled',
					'reason' => '',
				),
				'away_team'      => self::team( 'DET', 'Tigers', 'Detroit Tigers', '64-76' ),
				'home_team'      => self::team( 'CLE', 'Guardians', 'Cleveland Guardians', '71-70' ),
				'away_pitcher'   => array(
					'name'   => 'Keider Montero',
					'hand'   => 'R',
					'record' => '4-7',
					'era'    => '6.28',
					'url'    => '',
				),
				'home_pitcher'   => array(
					'name'   => 'Logan Allen',
					'hand'   => 'L',
					'record' => '7-8',
					'era'    => '4.91',
					'url'    => '',
				),
				'recent_form'    => array(
					'away' => array(
						'last_ten' => '4-6',
						'streak'   => 'L1',
					),
					'home' => array(
						'last_ten' => '6-4',
						'streak'   => 'W2',
					),
				),
				'series'         => array(
					'game_number'  => 1,
					'games_total'  => 4,
					'status_label' => '',
				),
				'scores'         => array(),
				'broadcasts'     => array(
					'radio' => array( 'WTAM', 'WMMS' ),
					'tv'    => array( 'Guardians.TV' ),
				),
				'sort_time'      => time() + 7200,
				'show_label'     => false,
			),
			$overrides
		);
	}

	/**
	 * One team entry.
	 *
	 * @param string $abbr Abbreviation.
	 * @param string $short Short name.
	 * @param string $full Full name.
	 * @param string $record W-L record.
	 * @return array
	 */
	public static function team( string $abbr, string $short, string $full, string $record ): array {
		return array(
			'id'           => 0,
			'name'         => $full,
			'short_name'   => $short,
			'abbreviation' => $abbr,
			'logo_url'     => 'https://example.test/logo-' . strtolower( $abbr ) . '.svg',
			'record'       => $record,
		);
	}

	/**
	 * A game stopped mid-play. 'scores' is present because MLB reports the game as Live.
	 *
	 * @param string $state detailedState, e.g. "Delayed: Rain".
	 * @param array  $overrides Keys to replace.
	 * @return array
	 */
	public static function delayed_in_progress( string $state = 'Delayed: Rain', array $overrides = array() ): array {
		return self::game(
			array_merge(
				array(
					'game_status'    => 'Live',
					'detailed_state' => array(
						'state'  => $state,
						'reason' => '',
					),
					'scores'         => array(
						'away'    => 3,
						'home'    => 1,
						'winner'  => '',
						'inning'  => 'Top of the 6th',
						'isFinal' => false,
					),
				),
				$overrides
			)
		);
	}

	/**
	 * A game whose first pitch has been pushed back. Still Preview, so no scores.
	 *
	 * @param string $state detailedState, e.g. "Delayed Start: Rain".
	 * @param array  $overrides Keys to replace.
	 * @return array
	 */
	public static function delayed_start( string $state = 'Delayed Start: Rain', array $overrides = array() ): array {
		return self::game(
			array_merge(
				array(
					'game_status'    => 'Preview',
					'detailed_state' => array(
						'state'  => $state,
						'reason' => '',
					),
				),
				$overrides
			)
		);
	}

	/**
	 * A completed game.
	 *
	 * @param int   $away Away runs.
	 * @param int   $home Home runs.
	 * @param array $overrides Keys to replace.
	 * @return array
	 */
	public static function final_game( int $away = 2, int $home = 6, array $overrides = array() ): array {
		return self::game(
			array_merge(
				array(
					'game_status'    => 'Final',
					'detailed_state' => array(
						'state'  => 'Final',
						'reason' => '',
					),
					'scores'         => array(
						'away'    => $away,
						'home'    => $home,
						'winner'  => $home > $away ? 'home' : 'away',
						'inning'  => 9,
						'isFinal' => true,
					),
				),
				$overrides
			)
		);
	}

	/**
	 * A game in progress.
	 *
	 * @param array $overrides Keys to replace.
	 * @return array
	 */
	public static function live_game( array $overrides = array() ): array {
		return self::game(
			array_merge(
				array(
					'game_status'    => 'Live',
					'detailed_state' => array(
						'state'  => 'In Progress',
						'reason' => '',
					),
					'scores'         => array(
						'away'    => 3,
						'home'    => 1,
						'winner'  => '',
						'inning'  => 'Top of the 6th',
						'isFinal' => false,
					),
				),
				$overrides
			)
		);
	}

	/**
	 * A live feed payload with a current at-bat.
	 *
	 * @param array $overrides Keys to replace in current_play.
	 * @return array
	 */
	public static function live_feed( array $overrides = array() ): array {
		return array(
			'current_play' => array_merge(
				array(
					'inning'  => 6,
					'half'    => 'top',
					'outs'    => 1,
					'batter'  => 'Colt Keith',
					'pitcher' => 'Foster Griffin',
					'balls'   => 1,
					'strikes' => 2,
					'bases'   => array(
						'first'  => false,
						'second' => false,
						'third'  => true,
					),
				),
				$overrides
			),
			'recent_plays' => array(),
			'lineups'      => array(
				'away' => array(),
				'home' => array(),
			),
			'pitchers'     => array(
				'away' => array(),
				'home' => array(),
			),
			'game_summary' => array(
				'line'              => array(
					'away' => array(
						'runs'   => 3,
						'hits'   => 5,
						'errors' => 0,
					),
					'home' => array(
						'runs'   => 1,
						'hits'   => 4,
						'errors' => 1,
					),
				),
				'innings'           => array(),
				'scheduled_innings' => 9,
				'comparison'        => array(
					'away' => array(),
					'home' => array(),
				),
				'hp_umpire'         => 'Pat Hoberg',
			),
		);
	}

	/**
	 * A day's schedule.
	 *
	 * @param array  $games Normalized game arrays.
	 * @param string $day_date Display date for the day.
	 * @return array
	 */
	public static function schedule( array $games, string $day_date = 'Fri 9/4' ): array {
		foreach ( $games as $index => $game ) {
			$games[ $index ]['show_label'] = count( $games ) > 1;
		}

		return array(
			'day_date' => $day_date,
			'off_day'  => false,
			'games'    => $games,
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Belle directory
	 * -----------------------------------------------------------------------
	 */

	/**
	 * A Guardians roster, as Basebelles_API::get_guardians_roster() returns it: already sorted
	 * by name, because the real method sorts before handing it back.
	 *
	 * @return array[]
	 */
	public static function roster(): array {
		return array(
			array(
				'id'       => 680757,
				'name'     => 'Bo Naylor',
				'jersey'   => '23',
				'position' => 'C',
			),
			array(
				'id'       => 608070,
				'name'     => 'José Ramírez',
				'jersey'   => '11',
				'position' => '3B',
			),
			array(
				'id'       => 663581,
				'name'     => 'Steven Kwan',
				'jersey'   => '38',
				'position' => 'LF',
			),
		);
	}

	/**
	 * One completed WPForms submission, in the shape wpforms_process_complete receives.
	 *
	 * Keys are field IDs; each entry carries the label in 'name', which is what the intake
	 * matches on. Pass label => value overrides to change or add a field, or a value of null
	 * to drop that field from the submission entirely.
	 *
	 * @param array $overrides Field label => value (or null to remove).
	 * @return array
	 */
	public static function entry( array $overrides = array() ): array {
		$defaults = array(
			'Your Name'                  => array( 'Mika Epstein', 'name' ),
			'Email Address'              => array( 'Mika@Example.COM', 'email' ),
			'Location'                   => array( 'Cleveland, OH, USA', 'text' ),
			'Favorite Current Player'    => array( 'José Ramírez', 'select' ),
			'Favorite Historical Player' => array( 'Kenny Lofton', 'text' ),
			// A GDPR checkbox submits its own label text when ticked, nothing when not.
			'GDPR Agreement'             => array(
				'I consent to having this website store my submitted information so they can list me as a Belle.',
				'gdpr-checkbox',
			),
		);

		foreach ( $overrides as $label => $value ) {
			$type = $defaults[ $label ][1] ?? 'text';

			if ( null === $value ) {
				unset( $defaults[ $label ] );
				continue;
			}

			$defaults[ $label ] = array( $value, $type );
		}

		$fields = array();
		$id     = 1;

		foreach ( $defaults as $label => $field ) {
			$fields[ $id ] = array(
				'name'  => $label,
				'value' => $field[0],
				'id'    => $id,
				'type'  => $field[1],
			);

			++$id;
		}

		return $fields;
	}

	/**
	 * A stored WPForms form with one choice field, as the roster sync expects to find it.
	 *
	 * @param string $field_id ID of the choice field.
	 * @return array
	 */
	public static function wpforms_form( string $field_id = '4' ): array {
		return array(
			'id'       => 42,
			'settings' => array(
				'form_title' => 'Be a Belle',
			),
			'fields'   => array(
				'2'       => array(
					'id'    => '2',
					'type'  => 'email',
					'label' => 'Email Address',
				),
				$field_id => array(
					'id'      => $field_id,
					'type'    => 'select',
					'label'   => 'Favorite Current Player',
					'choices' => array(
						1 => array(
							'label' => 'Somebody Stale',
							'value' => '',
						),
					),
				),
			),
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Postseason (raw MLB payloads, for Basebelles_Postseason)
	 * -----------------------------------------------------------------------
	 */

	/** Opponents used by the postseason fixtures: MLB team ID => abbreviation. */
	const PS_TEAMS = array(
		114 => array( 'CLE', 'Cleveland Guardians' ),
		145 => array( 'CWS', 'Chicago White Sox' ),
		117 => array( 'HOU', 'Houston Astros' ),
		139 => array( 'TB', 'Tampa Bay Rays' ),
		147 => array( 'NYY', 'New York Yankees' ),
		119 => array( 'LAD', 'Los Angeles Dodgers' ),
	);

	/**
	 * One raw postseason game, shaped like the schedule endpoint with hydrate=team,linescore.
	 *
	 * @param string   $type     Game type: F, D, L or W.
	 * @param int      $number   seriesGameNumber.
	 * @param int      $total    gamesInSeries.
	 * @param int      $opponent Opponent team ID.
	 * @param bool     $cle_home Whether Cleveland is home.
	 * @param string   $state    Final, Live or Preview.
	 * @param int|null $cle      Cleveland runs (Final/Live).
	 * @param int|null $opp      Opponent runs (Final/Live).
	 * @param array    $extra    Top-level keys to replace (officialDate, gameDate, ifNecessary...).
	 * @return array
	 */
	public static function ps_game( string $type, int $number, int $total, int $opponent, bool $cle_home, string $state = 'Preview', ?int $cle = null, ?int $opp = null, array $extra = array() ): array {
		$side = static function ( int $id, ?int $runs, ?bool $winner ) {
			$team = array(
				'team' => array(
					'id'           => $id,
					'name'         => self::PS_TEAMS[ $id ][1],
					'abbreviation' => self::PS_TEAMS[ $id ][0],
				),
			);

			if ( null !== $runs ) {
				$team['score'] = $runs;
			}

			if ( null !== $winner ) {
				$team['isWinner'] = $winner;
			}

			return $team;
		};

		$final   = 'Final' === $state;
		$cle_won = $final ? ( (int) $cle > (int) $opp ) : null;
		$opp_won = $final ? ! $cle_won : null;
		$us      = $side( 114, $cle, $cle_won );
		$them    = $side( $opponent, $opp, $opp_won );

		return array_merge(
			array(
				'gameType'          => $type,
				'gameDate'          => '2026-10-0' . min( 9, $number ) . 'T23:00:00Z',
				'officialDate'      => '2026-10-0' . min( 9, $number ),
				'status'            => array(
					'abstractGameState' => $state,
					'detailedState'     => $final ? 'Final' : ( 'Live' === $state ? 'In Progress' : 'Scheduled' ),
					'startTimeTBD'      => false,
				),
				'teams'             => array(
					'home' => $cle_home ? $us : $them,
					'away' => $cle_home ? $them : $us,
				),
				'linescore'         => array(
					'inningHalf'           => 'Top',
					'currentInningOrdinal' => '6th',
				),
				'gamesInSeries'     => $total,
				'seriesGameNumber'  => $number,
				'seriesDescription' => array(
					'F' => 'AL Wild Card Series',
					'D' => 'AL Division Series',
					'L' => 'AL Championship Series',
					'W' => 'World Series',
				)[ $type ],
				'ifNecessary'       => 'N',
			),
			$extra
		);
	}

	/**
	 * Wrap games in a schedule `dates` array, one date per game.
	 *
	 * @param array[] $games Raw games.
	 * @return array
	 */
	public static function ps_dates( array $games ): array {
		return array_map(
			static function ( $game ) {
				return array(
					'date'  => $game['officialDate'],
					'games' => array( $game ),
				);
			},
			$games
		);
	}

	/**
	 * The real 2026 ALDS as of the morning of Game 4: CWS won 1 and 2 at Progressive, CLE won 3
	 * at Rate Field, Game 4 tonight in Chicago, Game 5 back in Cleveland if necessary.
	 *
	 * @return array[]
	 */
	public static function alds_2026_before_game_four(): array {
		return array(
			self::ps_game( 'D', 1, 5, 145, true, 'Final', 0, 3, array( 'officialDate' => '2026-10-03', 'gameDate' => '2026-10-03T17:00:00Z' ) ),
			self::ps_game( 'D', 2, 5, 145, true, 'Final', 3, 4, array( 'officialDate' => '2026-10-05', 'gameDate' => '2026-10-05T21:00:00Z' ) ),
			self::ps_game( 'D', 3, 5, 145, false, 'Final', 9, 3, array( 'officialDate' => '2026-10-07', 'gameDate' => '2026-10-07T20:00:00Z' ) ),
			self::ps_game( 'D', 4, 5, 145, false, 'Preview', null, null, array( 'officialDate' => '2026-10-08', 'gameDate' => '2026-10-09T00:00:00Z' ) ),
			self::ps_game( 'D', 5, 5, 145, true, 'Preview', null, null, array( 'officialDate' => '2026-10-10', 'gameDate' => '2026-10-11T00:00:00Z', 'ifNecessary' => 'Y' ) ),
		);
	}

	/**
	 * The 2026 AL regular-season standings, trimmed to what seeding reads.
	 *
	 * @return array The `records` array.
	 */
	public static function al_standings_2026(): array {
		$row = static function ( int $id, string $league_rank, bool $leader, string $wild = '' ) {
			$row = array(
				'team'           => array( 'id' => $id ),
				'leagueRank'     => $league_rank,
				'divisionLeader' => $leader,
				'divisionChamp'  => $leader,
			);

			if ( '' !== $wild ) {
				$row['wildCardRank'] = $wild;
			}

			return $row;
		};

		return array(
			// AL East: Rays won it, Yankees and Red Sox are the top two wild cards.
			array( 'teamRecords' => array( $row( 139, '1', true ), $row( 147, '2', false, '1' ), $row( 111, '3', false, '2' ), $row( 110, '8', false, '5' ) ) ),
			// AL Central: Guardians by one game over the White Sox, the third wild card.
			array( 'teamRecords' => array( $row( 114, '4', true ), $row( 145, '5', false, '3' ), $row( 142, '10', false, '7' ) ) ),
			// AL West: Astros won it at 81-81, Rangers just missed.
			array( 'teamRecords' => array( $row( 117, '6', true ), $row( 140, '7', false, '4' ) ) ),
		);
	}

	/**
	 * Standings, as Basebelles_API::fetch_standings() returns them. Defaults to a .500 team on a
	 * losing streak with a negative run differential, so both negative flags are exercised.
	 *
	 * @param array $overrides Keys to replace.
	 * @return array
	 */
	public static function standings( array $overrides = array() ): array {
		return array_merge(
			array(
				'summary'              => '2nd in the AL Central',
				'division_name'        => 'AL Central',
				'division_rank'        => '2',
				'wins'                 => 70,
				'losses'               => 70,
				'winning_percentage'   => '.500',
				'games_back'           => '3.0',
				'wild_card_games_back' => '-',
				'last_ten'             => '6-4',
				'streak'               => 'L2',
				'runs_scored'          => 566,
				'runs_allowed'         => 577,
				'run_differential'     => '-11',
				'home'                 => '33-38',
				'away'                 => '37-32',
				'over_500'             => '23-32',
				'season'               => 2026,
				'season_type'          => 'regularSeason',
			),
			$overrides
		);
	}
}
