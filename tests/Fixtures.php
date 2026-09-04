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
