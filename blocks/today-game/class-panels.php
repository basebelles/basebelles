<?php
/**
 * Renders the Score / Plays / Stats / Players tabs for the today-game block.
 *
 * Shared by the block's render.php (first paint) and the today-game REST
 * endpoint (live polling), so both always produce identical markup.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Today_Game_Panels {

	/** How close to first pitch the Score tab switches from the far-out matchup view to the countdown view. */
	const NEAR_PHASE_WINDOW = 30 * MINUTE_IN_SECONDS;

	/**
	 * detailedState values that mean play is stopped but the game is still expected to happen.
	 *
	 * Matched as prefixes, because MLB appends the cause to most of them -- "Delayed: Rain",
	 * "Delayed Start: Rain", "Suspended: Rain" -- and the bare form shows up too when it hasn't
	 * decided on a cause yet. Rain is the common case but nothing here is rain-specific.
	 */
	const DELAY_STATES = array( 'Delayed Start', 'Delayed', 'Suspended', 'Umpire Delay' );

	/**
	 * Determine which phase a game is in: far, near, live, delayed, postponed, or final.
	 *
	 * @param array $game Normalized game array from Basebelles_API::get_guardians_today_game().
	 * @return string
	 */
	public static function get_phase( array $game ) {
		$d_state = (string) ( $game['detailed_state']['state'] ?? '' );

		if ( 'Postponed' === $d_state ) {
			return 'postponed';
		}

		// Ahead of the Final check on purpose: a game that was delayed and then played out reports
		// a plain "Final", so it lands in the final phase on its own once MLB drops the delay
		// state. Checking Final first would instead swallow "Suspended", which MLB can report
		// with an abstract state of Final while the game is still going to be resumed.
		if ( self::is_delay_state( $d_state ) ) {
			return 'delayed';
		}

		if ( 'Final' === ( $game['game_status'] ?? '' ) ) {
			return 'final';
		}

		if ( 'Live' === ( $game['game_status'] ?? '' ) ) {
			return 'live';
		}

		$seconds_to_first_pitch = (int) ( $game['sort_time'] ?? 0 ) - time();

		return $seconds_to_first_pitch <= self::NEAR_PHASE_WINDOW ? 'near' : 'far';
	}

	/**
	 * Whether a detailedState is one of the stopped-but-still-expected states.
	 *
	 * @param string $d_state MLB's detailedState.
	 * @return bool
	 */
	public static function is_delay_state( $d_state ) {
		foreach ( self::DELAY_STATES as $prefix ) {
			if ( 0 === stripos( (string) $d_state, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a delayed game had already started when play stopped, which decides whether the
	 * delay panel talks about a halted game or a pushed-back first pitch. MLB keeps a delayed
	 * start in Preview and moves to Live at the actual first pitch, so the abstract state is the
	 * signal -- the linescore reports zeroed innings either way.
	 *
	 * @param array $game Normalized game array.
	 * @return bool
	 */
	public static function delay_is_in_progress( array $game ) {
		return 'Live' === ( $game['game_status'] ?? '' );
	}

	/**
	 * The cause of a delay, when MLB names one. It arrives either in the status reason field or
	 * appended to detailedState after a colon; the two disagree often enough to check both.
	 *
	 * @param array $game Normalized game array.
	 * @return string Reason, or an empty string.
	 */
	public static function get_delay_reason( array $game ) {
		$reason = trim( (string) ( $game['detailed_state']['reason'] ?? '' ) );

		if ( '' !== $reason ) {
			return $reason;
		}

		$d_state = (string) ( $game['detailed_state']['state'] ?? '' );
		$colon   = strpos( $d_state, ':' );

		return false === $colon ? '' : trim( substr( $d_state, $colon + 1 ) );
	}

	/**
	 * Short badge text for a delay -- "Rain delay" when the cause is known, otherwise the state
	 * itself. Suspended and umpire delays keep their own wording rather than being folded into
	 * "<cause> delay", which would misdescribe them.
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	public static function get_delay_label( array $game ) {
		$d_state = (string) ( $game['detailed_state']['state'] ?? '' );
		$reason  = self::get_delay_reason( $game );

		if ( 0 === stripos( $d_state, 'Umpire Delay' ) ) {
			return 'Umpire delay';
		}

		if ( 0 === stripos( $d_state, 'Suspended' ) ) {
			return '' === $reason ? 'Suspended' : 'Suspended · ' . $reason;
		}

		return '' === $reason ? 'Delayed' : $reason . ' delay';
	}

	/**
	 * The .game-time element in the card header: a LIVE badge, a delay badge, or the scheduled
	 * first-pitch time. Shared by the block and the REST endpoint so a game that goes into (or
	 * comes out of) a delay mid-poll gets the right badge without the browser guessing at it.
	 *
	 * @param array  $game Normalized game array.
	 * @param string $phase Result of self::get_phase( $game ).
	 * @return string
	 */
	public static function render_time_label( array $game, $phase ) {
		if ( 'live' === $phase ) {
			return '<div class="game-time is-live"><span class="tg-live-dot" aria-hidden="true"></span>LIVE</div>';
		}

		if ( 'delayed' === $phase ) {
			return '<div class="game-time is-delayed">' . esc_html( self::get_delay_label( $game ) ) . '</div>';
		}

		if ( 'postponed' === $phase ) {
			return '<div class="game-time is-postponed">Postponed</div>';
		}

		return '<div class="game-time">' . esc_html( (string) ( $game['game_time'] ?? '' ) ) . '</div>';
	}

	/**
	 * Compact "1 - 3" score line for the header, shown under the LIVE badge (or the scheduled
	 * time, once final). Reads the live feed's linescore rather than the schedule's, since the
	 * live feed refreshes every 60s during the game and the schedule call doesn't.
	 *
	 * @param array $game Normalized game array.
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	public static function render_header_score( array $game, array $live_feed ) {
		// MLB's linescore already reports zeroed (not absent) runs before first pitch, so gate
		// on game status directly rather than on whether the runs value is present.
		if ( 'Preview' === ( $game['game_status'] ?? '' ) ) {
			return '';
		}

		$line      = $live_feed['game_summary']['line'] ?? array();
		$away_runs = $line['away']['runs'] ?? null;
		$home_runs = $line['home']['runs'] ?? null;

		if ( null === $away_runs || null === $home_runs ) {
			return '';
		}

		return '<div class="tg-header-score">' . esc_html( $away_runs ) . ' &ndash; ' . esc_html( $home_runs ) . '</div>';
	}

	/**
	 * Short status suffix for a game's switcher tab -- "Final 2-6", "Live", or first-pitch time.
	 *
	 * Returned as HTML rather than text because the live variant carries a pulse dot. Kept
	 * separate from the tab markup so the REST endpoint can hand the browser a fresh label when
	 * a game changes state while the reader is looking at the other one.
	 *
	 * @param array  $game Normalized game array.
	 * @param string $phase Result of self::get_phase( $game ).
	 * @return string
	 */
	public static function render_switcher_label( array $game, $phase ) {
		$number = 'Game ' . (int) ( $game['game_number'] ?? 1 );

		if ( 'postponed' === $phase ) {
			return esc_html( $number . ' · PPD' );
		}

		// Just "Delayed" here even when the cause is known -- "Game 1 · Rain delay" is wider than
		// the pill holds on a phone, and the panel says why anyway.
		if ( 'delayed' === $phase ) {
			return esc_html( $number . ' · Delayed' );
		}

		if ( 'live' === $phase ) {
			return '<span class="tg-live-dot" aria-hidden="true"></span>' . esc_html( $number ) . ' &middot; Live';
		}

		if ( 'final' === $phase ) {
			$away = $game['scores']['away'] ?? null;
			$home = $game['scores']['home'] ?? null;

			if ( null === $away || null === $home ) {
				return esc_html( $number . ' · Final' );
			}

			return esc_html( $number ) . ' &middot; Final ' . esc_html( $away ) . '&ndash;' . esc_html( $home );
		}

		// The scheduled time carries a timezone and, for the back half of a traditional
		// doubleheader, the "TBD (30m after G1)" placeholder -- both too long for a pill this
		// narrow, so the tab gets the short form and the panel keeps the full string.
		$time = (string) ( $game['game_time'] ?? '' );

		if ( '' === $time ) {
			return esc_html( $number );
		}

		if ( 0 === strpos( $time, 'TBD' ) ) {
			$time = 'TBD';
		}

		return esc_html( $number . ' · ' . $time );
	}

	/**
	 * Which game the switcher should open on, in order of preference: the live one, a delayed one,
	 * the next one still to be played, else the last one finished. Games arrive sorted by first
	 * pitch, so "next" and "last" are just the first and last matches.
	 *
	 * Delayed outranks finished because the pair can genuinely occur: a suspended opener that
	 * will resume another day, alongside a nightcap played to completion. The unfinished game is
	 * the one still worth watching.
	 *
	 * @param array $games Normalized game arrays for the day.
	 * @param array $phases Phase string per game, in the same order.
	 * @return int Index into $games.
	 */
	public static function get_default_game_index( array $games, array $phases ) {
		$delayed  = null;
		$upcoming = null;

		foreach ( $phases as $index => $phase ) {
			if ( 'live' === $phase ) {
				return $index;
			}

			if ( null === $delayed && 'delayed' === $phase ) {
				$delayed = $index;
			}

			if ( null === $upcoming && in_array( $phase, array( 'far', 'near' ), true ) ) {
				$upcoming = $index;
			}
		}

		if ( null !== $delayed ) {
			return $delayed;
		}

		if ( null !== $upcoming ) {
			return $upcoming;
		}

		return max( 0, count( $games ) - 1 );
	}

	/**
	 * The segmented Game 1 / Game 2 control shown above a doubleheader's panel.
	 *
	 * @param array $games Normalized game arrays for the day.
	 * @param array $phases Phase string per game, in the same order.
	 * @param int   $active_index Index of the game to mark active.
	 * @return string
	 */
	public static function render_game_switcher( array $games, array $phases, $active_index ) {
		ob_start();
		?>
		<div class="tg-game-switcher" role="tablist" aria-label="Doubleheader games">
			<?php foreach ( $games as $index => $game ) : ?>
				<?php $is_active = (int) $index === (int) $active_index; ?>
				<button
					type="button"
					class="tg-game-tab<?php echo $is_active ? ' is-active' : ''; ?>"
					data-game-tab="<?php echo esc_attr( (string) $game['game_pk'] ); ?>"
					role="tab"
					aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
				><?php echo self::render_switcher_label( $game, $phases[ $index ] ?? 'far' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the tab bar and all four panels for one game.
	 *
	 * @param array  $game Normalized game array.
	 * @param string $phase Result of self::get_phase( $game ).
	 * @param array  $live_feed Result of Basebelles_API::get_live_feed(), or empty array.
	 * @return string
	 */
	public static function render( array $game, $phase, array $live_feed ) {
		ob_start();
		?>
		<div class="tg-tabs" data-game-pk="<?php echo esc_attr( (string) $game['game_pk'] ); ?>" data-phase="<?php echo esc_attr( $phase ); ?>">
			<div class="tg-tab-bar" role="tablist">
				<button type="button" class="tg-tab is-active" data-tab="score" role="tab" aria-selected="true">Status</button>
				<button type="button" class="tg-tab" data-tab="plays" role="tab" aria-selected="false">Plays</button>
				<button type="button" class="tg-tab" data-tab="stats" role="tab" aria-selected="false">Stats</button>
				<button type="button" class="tg-tab" data-tab="players" role="tab" aria-selected="false">Players</button>
			</div>
			<div class="tg-panel is-active" data-panel="score"><?php echo self::render_score_panel( $game, $phase, $live_feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="tg-panel" data-panel="plays"><?php echo self::render_plays_panel( $live_feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="tg-panel" data-panel="stats"><?php echo self::render_stats_panel( $game, $phase, $live_feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="tg-panel" data-panel="players"><?php echo self::render_players_panel( $game, $live_feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Score tab: far-out reuses the Stats tab, near shows a countdown, live shows the at-bat, final shows the score.
	 *
	 * @param array  $game Normalized game array.
	 * @param string $phase Game phase.
	 * @param array  $live_feed Live feed data, or empty array.
	 * @return string
	 */
	public static function render_score_panel( array $game, $phase, array $live_feed ) {
		if ( 'postponed' === $phase ) {
			return self::render_postponed( $game );
		}

		if ( 'delayed' === $phase ) {
			return self::render_delayed( $game, $live_feed );
		}

		if ( 'far' === $phase ) {
			return self::render_far_score( $game );
		}

		if ( 'near' === $phase ) {
			return self::render_countdown( $game );
		}

		if ( 'live' === $phase ) {
			return self::render_at_bat_now( $game, $live_feed );
		}

		return self::render_final_score( $game );
	}

	/**
	 * Delayed-phase Score content. Two shapes: a game stopped in progress says where it stopped
	 * and keeps the score on screen, while a pushed-back first pitch says when the game was
	 * meant to start. Neither promises a restart time -- MLB doesn't publish one, so inventing
	 * "resumes shortly" would be making it up.
	 *
	 * @param array $game Normalized game array.
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	private static function render_delayed( array $game, array $live_feed ) {
		$in_progress = self::delay_is_in_progress( $game );
		$reason      = self::get_delay_reason( $game );

		ob_start();
		?>
		<div class="tg-delay">
			<div class="tg-delay-badge"><?php echo esc_html( self::get_delay_label( $game ) ); ?></div>

			<?php if ( $in_progress ) : ?>
				<?php
				$current_play = $live_feed['current_play'] ?? array();
				$stopped_at   = '';

				if ( ! empty( $current_play['inning'] ) ) {
					$stopped_at = ( 'top' === strtolower( (string) $current_play['half'] ) ? 'Top' : 'Bottom' )
						. ' ' . $current_play['inning'];
				} elseif ( ! empty( $game['scores']['inning'] ) ) {
					// Pre-formatted by the schedule as "Top of the 6th" when the live feed is thin.
					$stopped_at = (string) $game['scores']['inning'];
				}
				?>
				<div class="tg-delay-detail">
					<?php if ( '' !== $stopped_at ) : ?>
						Play stopped in the <?php echo esc_html( strtolower( $stopped_at ) ); ?>.
					<?php else : ?>
						Play is stopped.
					<?php endif; ?>
				</div>
				<?php if ( isset( $game['scores']['away'], $game['scores']['home'] ) ) : ?>
					<div class="tg-delay-score">
						<span><?php echo esc_html( $game['away_team']['abbreviation'] ?? '' ); ?> <?php echo esc_html( $game['scores']['away'] ); ?></span>
						<span><?php echo esc_html( $game['home_team']['abbreviation'] ?? '' ); ?> <?php echo esc_html( $game['scores']['home'] ); ?></span>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="tg-delay-detail">
					<?php if ( ! empty( $game['game_time'] ) ) : ?>
						First pitch was scheduled for <?php echo esc_html( $game['game_time'] ); ?>.
					<?php else : ?>
						First pitch is on hold.
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="tg-delay-note">
				<?php if ( '' !== $reason ) : ?>
					<?php echo esc_html( $reason ); ?> &middot; no updated start time yet.
				<?php else : ?>
					No updated start time yet.
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Postponed-phase Score content. Previously this lived inside render_final_score(), which
	 * meant a postponed game was drawn as a final one with its runs blanked to "-" on either side
	 * of a PPD badge. It isn't a result, so it no longer borrows the score layout.
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	private static function render_postponed( array $game ) {
		$reason = self::get_delay_reason( $game );

		ob_start();
		?>
		<div class="tg-delay is-postponed">
			<div class="tg-delay-badge">Postponed</div>
			<div class="tg-delay-detail">
				<?php if ( '' !== $reason ) : ?>
					Called for <?php echo esc_html( strtolower( $reason ) ); ?>.
				<?php else : ?>
					This game won't be played today.
				<?php endif; ?>
			</div>
			<div class="tg-delay-note">Watch for a makeup date.</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Far-phase Score content: the pitching matchup, with a countdown to first pitch in place of
	 * the Stats tab's season-form line (there's nothing to count down to once the game is close).
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	private static function render_far_score( array $game ) {
		ob_start();
		echo self::render_pitching_matchup( $game ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="tg-far-countdown">
			<?php echo self::render_countdown_timer( $game ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The "First pitch in ..." countdown element. Shared by the far and near Score views.
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	private static function render_countdown_timer( array $game ) {
		$first_pitch = $game['sort_time'] ? wp_date( 'c', $game['sort_time'] ) : '';

		ob_start();
		?>
		<div class="tg-countdown" data-first-pitch="<?php echo esc_attr( $first_pitch ); ?>">
			First pitch in <span class="tg-countdown-value">&hellip;</span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Near-phase Score content: countdown to first pitch, team records, and projected starters.
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	private static function render_countdown( array $game ) {
		$away_pitcher = $game['away_pitcher'] ?? array();
		$home_pitcher = $game['home_pitcher'] ?? array();

		ob_start();
		echo self::render_countdown_timer( $game ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="tg-near-records">
			<span><?php echo esc_html( $game['away_team']['abbreviation'] ?? '' ); ?> <?php echo esc_html( $game['away_team']['record'] ?? '' ); ?></span>
			<span><?php echo esc_html( $game['home_team']['abbreviation'] ?? '' ); ?> <?php echo esc_html( $game['home_team']['record'] ?? '' ); ?></span>
		</div>
		<div class="tg-near-starters">
			<div><?php echo esc_html( $away_pitcher['name'] ?? 'TBD' ); ?> <span class="pitcher-hand"><?php echo esc_html( $away_pitcher['hand'] ?? '' ); ?></span></div>
			<div><?php echo esc_html( $home_pitcher['name'] ?? 'TBD' ); ?> <span class="pitcher-hand"><?php echo esc_html( $home_pitcher['hand'] ?? '' ); ?></span></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Live-phase Score content: a condensed "who's at bat" card.
	 *
	 * @param array $game Normalized game array.
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	private static function render_at_bat_now( array $game, array $live_feed ) {
		$current_play = $live_feed['current_play'] ?? array();

		if ( empty( $current_play ) ) {
			return '<p class="tg-empty">Live &mdash; check the Plays tab for the latest.</p>';
		}

		$bases  = $current_play['bases'] ?? array();
		$is_top = 'top' === strtolower( $current_play['half'] );
		$half   = $is_top ? 'Top' : 'Bottom';
		// Top of the inning: away team bats, home team pitches. Bottom: the reverse.
		$batter_abbr  = $is_top ? ( $game['away_team']['abbreviation'] ?? '' ) : ( $game['home_team']['abbreviation'] ?? '' );
		$pitcher_abbr = $is_top ? ( $game['home_team']['abbreviation'] ?? '' ) : ( $game['away_team']['abbreviation'] ?? '' );

		ob_start();
		?>
		<div class="tg-at-bat">
			<div class="tg-at-bat-inning"><?php echo esc_html( $half . ' ' . $current_play['inning'] ); ?> &middot; <?php echo esc_html( $current_play['outs'] ); ?> out</div>
			<div class="tg-at-bat-matchup">
				<strong><?php echo esc_html( $current_play['batter'] ); ?></strong> (<?php echo esc_html( $batter_abbr ); ?>) at bat vs <strong><?php echo esc_html( $current_play['pitcher'] ); ?></strong> (<?php echo esc_html( $pitcher_abbr ); ?>)
			</div>
			<div class="tg-at-bat-count"><?php echo esc_html( $current_play['balls'] . '-' . $current_play['strikes'] ); ?></div>
			<div class="tg-bases" aria-hidden="true">
				<span class="tg-base tg-base-second<?php echo ! empty( $bases['second'] ) ? ' is-occupied' : ''; ?>"></span>
				<span class="tg-base tg-base-third<?php echo ! empty( $bases['third'] ) ? ' is-occupied' : ''; ?>"></span>
				<span class="tg-base tg-base-first<?php echo ! empty( $bases['first'] ) ? ' is-occupied' : ''; ?>"></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Final/live score line. Same markup the block has always shown for scored games.
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	private static function render_final_score( array $game ) {
		if ( empty( $game['scores'] ) ) {
			return '<p class="tg-empty">Score unavailable.</p>';
		}

		$home_or_away = ( 'CLE' === ( $game['home_team']['abbreviation'] ?? '' ) ) ? 'home' : 'away';

		ob_start();
		?>
		<div class="game-scores">
			<div class="score-away"><?php echo esc_html( $game['scores']['away'] ); ?></div>
			<div class="game-meta">
				<?php if ( ! empty( $game['scores']['isFinal'] ) ) : ?>
					<?php $winner = $home_or_away === $game['scores']['winner'] ? 'guards-win' : 'oppo-win'; ?>
					<strong class="<?php echo esc_attr( $winner ); ?>">
						FINAL
						<?php if ( ! empty( $game['scores']['inning'] ) && $game['scores']['inning'] > 9 ) : ?>
							<?php echo esc_html( ' / ' . $game['scores']['inning'] ); ?>
						<?php endif; ?>
					</strong>
				<?php else : ?>
					<strong>LIVE</strong>
				<?php endif; ?>
			</div>
			<div class="score-home"><?php echo esc_html( $game['scores']['home'] ); ?></div>
		</div>
		<?php if ( ! empty( $game['scores']['isFinal'] ) && ! empty( $game['scores']['winner'] ) ) : ?>
			<?php $guardians_win = $home_or_away === $game['scores']['winner']; ?>
			<div class="tg-result-headline <?php echo $guardians_win ? 'is-win' : 'is-loss'; ?>">
				The Guardians <?php echo $guardians_win ? 'win' : 'lose'; ?>
			</div>
		<?php endif; ?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Plays tab: recent play-by-play, newest first, once the game has started.
	 *
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	public static function render_plays_panel( array $live_feed ) {
		$plays = $live_feed['recent_plays'] ?? array();

		if ( empty( $plays ) ) {
			return '<p class="tg-empty">No plays yet.</p>';
		}

		ob_start();
		?>
		<ul class="tg-plays">
			<?php foreach ( $plays as $play ) : ?>
				<li<?php echo ! empty( $play['is_scoring'] ) ? ' class="tg-play-scoring"' : ''; ?>>
					<span class="tg-play-inning"><?php echo esc_html( ( 'top' === strtolower( $play['half'] ) ? 'Top' : 'Bot' ) . ' ' . $play['inning'] ); ?></span>
					<span class="tg-play-desc"><?php echo esc_html( $play['description'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Stats tab: pregame shows the probable-pitcher matchup plus each team's recent form (the team
	 * records themselves already show in the card header, so repeating them here added nothing);
	 * once the game is underway, the pregame matchup's season ERA/record is stale and the starters
	 * may already be out of the game, so show the linescore and team-vs-team box score instead.
	 *
	 * @param array  $game Normalized game array.
	 * @param string $phase Game phase.
	 * @param array  $live_feed Live feed data, or empty array.
	 * @return string
	 */
	public static function render_stats_panel( array $game, $phase, array $live_feed ) {
		// A game stopped in progress has a real box score to show; a delayed start has nothing but
		// the pregame matchup, so it falls through with the scheduled games.
		if ( in_array( $phase, array( 'live', 'final' ), true )
			|| ( 'delayed' === $phase && self::delay_is_in_progress( $game ) ) ) {
			return self::render_game_summary( $game, $live_feed );
		}

		$away_form = $game['recent_form']['away'] ?? array();
		$home_form = $game['recent_form']['home'] ?? array();

		ob_start();
		echo self::render_pitching_matchup( $game ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="tg-season-form">
			<div class="tg-season-form-col">
				<strong><?php echo esc_html( $away_form['last_ten'] ?? '-' ); ?></strong>
				<span><?php echo esc_html( ( $away_form['streak'] ?? '-' ) . ' streak' ); ?></span>
			</div>
			<span class="tg-season-form-label">Last 10</span>
			<div class="tg-season-form-col">
				<strong><?php echo esc_html( $home_form['last_ten'] ?? '-' ); ?></strong>
				<span><?php echo esc_html( ( $home_form['streak'] ?? '-' ) . ' streak' ); ?></span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Live/final Stats content: linescore, HP umpire, runs-by-inning, and a team-vs-team box score
	 * (times on base, contact, and pitch command). Falls back to the pregame matchup if the live
	 * feed hasn't loaded yet.
	 *
	 * @param array $game Normalized game array.
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	private static function render_game_summary( array $game, array $live_feed ) {
		$summary = $live_feed['game_summary'] ?? array();

		if ( empty( $summary ) ) {
			return self::render_pitching_matchup( $game );
		}

		$away_abbr   = $game['away_team']['abbreviation'] ?? '';
		$home_abbr   = $game['home_team']['abbreviation'] ?? '';
		$away_line   = $summary['line']['away'] ?? array();
		$home_line   = $summary['line']['home'] ?? array();
		$away_cmp    = $summary['comparison']['away'] ?? array();
		$home_cmp    = $summary['comparison']['home'] ?? array();
		$innings     = $summary['innings'] ?? array();
		$num_innings = max( (int) ( $summary['scheduled_innings'] ?? 9 ), count( $innings ) );

		ob_start();
		?>
		<div class="tg-summary-header">
			<div class="tg-summary-team">
				<strong><?php echo esc_html( $away_abbr ); ?></strong>
				<span><?php echo esc_html( $away_line['runs'] ?? 0 ); ?> R &bull; <?php echo esc_html( $away_line['hits'] ?? 0 ); ?> H &bull; <?php echo esc_html( $away_line['errors'] ?? 0 ); ?> E</span>
			</div>
			<?php if ( ! empty( $summary['hp_umpire'] ) ) : ?>
				<div class="tg-summary-umpire">HP <?php echo esc_html( $summary['hp_umpire'] ); ?></div>
			<?php endif; ?>
			<div class="tg-summary-team">
				<strong><?php echo esc_html( $home_abbr ); ?></strong>
				<span><?php echo esc_html( $home_line['runs'] ?? 0 ); ?> R &bull; <?php echo esc_html( $home_line['hits'] ?? 0 ); ?> H &bull; <?php echo esc_html( $home_line['errors'] ?? 0 ); ?> E</span>
			</div>
		</div>
		<div class="tg-innings-wrap">
			<table class="tg-innings">
				<thead>
					<tr>
						<th scope="col"></th>
						<?php for ( $i = 0; $i < $num_innings; $i++ ) : ?>
							<th scope="col"><?php echo esc_html( (string) ( $i + 1 ) ); ?></th>
						<?php endfor; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html( $away_abbr ); ?></th>
						<?php for ( $i = 0; $i < $num_innings; $i++ ) : ?>
							<td><?php echo esc_html( isset( $innings[ $i ]['away'] ) && null !== $innings[ $i ]['away'] ? (string) $innings[ $i ]['away'] : '-' ); ?></td>
						<?php endfor; ?>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $home_abbr ); ?></th>
						<?php for ( $i = 0; $i < $num_innings; $i++ ) : ?>
							<td><?php echo esc_html( isset( $innings[ $i ]['home'] ) && null !== $innings[ $i ]['home'] ? (string) $innings[ $i ]['home'] : '-' ); ?></td>
						<?php endfor; ?>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="tg-comparison">
			<table class="tg-comparison-group">
				<caption>Traffic</caption>
				<thead>
					<tr><th scope="col"></th><th scope="col"><?php echo esc_html( $away_abbr ); ?></th><th scope="col"><?php echo esc_html( $home_abbr ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td>On Base</td><td><?php echo esc_html( $away_cmp['on_base'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['on_base'] ?? 0 ); ?></td></tr>
					<tr><td>BB</td><td><?php echo esc_html( $away_cmp['bb'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['bb'] ?? 0 ); ?></td></tr>
					<tr><td>LOB</td><td><?php echo esc_html( $away_cmp['lob'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['lob'] ?? 0 ); ?></td></tr>
				</tbody>
			</table>
			<table class="tg-comparison-group">
				<caption>Contact</caption>
				<thead>
					<tr><th scope="col"></th><th scope="col"><?php echo esc_html( $away_abbr ); ?></th><th scope="col"><?php echo esc_html( $home_abbr ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td>H</td><td><?php echo esc_html( $away_cmp['h'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['h'] ?? 0 ); ?></td></tr>
					<tr><td>XBH</td><td><?php echo esc_html( $away_cmp['xbh'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['xbh'] ?? 0 ); ?></td></tr>
					<tr><td>TB</td><td><?php echo esc_html( $away_cmp['tb'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['tb'] ?? 0 ); ?></td></tr>
				</tbody>
			</table>
			<table class="tg-comparison-group">
				<caption>Command</caption>
				<thead>
					<tr><th scope="col"></th><th scope="col"><?php echo esc_html( $away_abbr ); ?></th><th scope="col"><?php echo esc_html( $home_abbr ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td>Pitches</td><td><?php echo esc_html( $away_cmp['pitches'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['pitches'] ?? 0 ); ?></td></tr>
					<tr><td>Strike%</td><td><?php echo esc_html( $away_cmp['strike_pct'] ?? '0%' ); ?></td><td><?php echo esc_html( $home_cmp['strike_pct'] ?? '0%' ); ?></td></tr>
					<tr><td>K</td><td><?php echo esc_html( $away_cmp['k'] ?? 0 ); ?></td><td><?php echo esc_html( $home_cmp['k'] ?? 0 ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The pitching-matchup card. Shared by the Stats tab and the far-phase Score view.
	 *
	 * @param array $game Normalized game array.
	 * @return string
	 */
	private static function render_pitching_matchup( array $game ) {
		$away_pitcher = $game['away_pitcher'] ?? array();
		$home_pitcher = $game['home_pitcher'] ?? array();

		if ( empty( $away_pitcher ) ) {
			$away_pitcher = array( 'name' => 'TBD', 'hand' => '', 'record' => '-', 'era' => '--', 'url' => '' );
		}

		if ( empty( $home_pitcher ) ) {
			$home_pitcher = array( 'name' => 'TBD', 'hand' => '', 'record' => '-', 'era' => '--', 'url' => '' );
		}

		ob_start();
		?>
		<div class="probable-pitchers">
			<div class="pitcher-away">
				<div class="pitcher-name">
					<?php if ( ! empty( $away_pitcher['url'] ) ) : ?>
						<a href="<?php echo esc_url( $away_pitcher['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $away_pitcher['name'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $away_pitcher['name'] ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $away_pitcher['hand'] ) ) : ?>
						<span class="pitcher-hand"><?php echo esc_html( $away_pitcher['hand'] ); ?></span>
					<?php endif; ?>
				</div>
				<div class="pitcher-stats">
					<span><?php echo esc_html( $away_pitcher['record'] ); ?></span>
					&bull;
					<span class="pitcher-era"><?php echo esc_html( $away_pitcher['era'] ); ?> ERA</span>
				</div>
			</div>
			<div class="game-meta">v.s.</div>
			<div class="pitcher-home">
				<div class="pitcher-name">
					<?php if ( ! empty( $home_pitcher['url'] ) ) : ?>
						<a href="<?php echo esc_url( $home_pitcher['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $home_pitcher['name'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $home_pitcher['name'] ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $home_pitcher['hand'] ) ) : ?>
						<span class="pitcher-hand"><?php echo esc_html( $home_pitcher['hand'] ); ?></span>
					<?php endif; ?>
				</div>
				<div class="pitcher-stats">
					<span><?php echo esc_html( $home_pitcher['record'] ); ?></span>
					&bull;
					<span class="pitcher-era"><?php echo esc_html( $home_pitcher['era'] ); ?> ERA</span>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Players tab: full starting lineups for both teams, once MLB has posted them.
	 *
	 * @param array $game Normalized game array.
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	public static function render_players_panel( array $game, array $live_feed ) {
		$away_lineup = $live_feed['lineups']['away'] ?? array();
		$home_lineup = $live_feed['lineups']['home'] ?? array();

		if ( empty( $away_lineup ) && empty( $home_lineup ) ) {
			return '<p class="tg-empty">Lineups haven\'t been posted yet. Please check back closer to game time.</p>';
		}

		$away_abbr     = $game['away_team']['abbreviation'] ?? '';
		$home_abbr     = $game['home_team']['abbreviation'] ?? '';
		$away_pitching = $live_feed['pitchers']['away'] ?? array();
		$home_pitching = $live_feed['pitchers']['home'] ?? array();

		// Default to whichever team is currently batting -- 'bottom' of the inning means the home
		// team is up. Falls back to away (e.g. pregame, no current_play yet).
		$active_side = 'bottom' === strtolower( $live_feed['current_play']['half'] ?? '' ) ? 'home' : 'away';

		ob_start();
		?>
		<div class="tg-subtabs">
			<div class="tg-subtab-bar" role="tablist">
				<button type="button" class="tg-subtab<?php echo 'away' === $active_side ? ' is-active' : ''; ?>" data-subtab="away" role="tab" aria-selected="<?php echo 'away' === $active_side ? 'true' : 'false'; ?>"><?php echo esc_html( $away_abbr ); ?></button>
				<button type="button" class="tg-subtab<?php echo 'home' === $active_side ? ' is-active' : ''; ?>" data-subtab="home" role="tab" aria-selected="<?php echo 'home' === $active_side ? 'true' : 'false'; ?>"><?php echo esc_html( $home_abbr ); ?></button>
			</div>
			<div class="tg-subpanel<?php echo 'away' === $active_side ? ' is-active' : ''; ?>" data-subpanel="away">
				<h4 class="tg-section-label">Batting</h4>
				<?php self::render_lineup_table( $away_abbr, $away_lineup ); ?>
				<h4 class="tg-section-label">Pitching</h4>
				<?php self::render_pitching_log_table( $away_abbr, $away_pitching ); ?>
			</div>
			<div class="tg-subpanel<?php echo 'home' === $active_side ? ' is-active' : ''; ?>" data-subpanel="home">
				<h4 class="tg-section-label">Batting</h4>
				<?php self::render_lineup_table( $home_abbr, $home_lineup ); ?>
				<h4 class="tg-section-label">Pitching</h4>
				<?php self::render_pitching_log_table( $home_abbr, $home_pitching ); ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render one team's batting-order table. Each slot (1-9) may hold more than one player if a
	 * pinch hitter/runner or defensive replacement has taken over that spot -- everyone who has
	 * occupied it gets a row, with earlier occupants struck through and the current one noting
	 * who they replaced, rather than the substitution silently erasing the original player.
	 *
	 * @param string $abbreviation Team abbreviation. The active sub-tab button already shows it
	 *                             visually, so it's a screen-reader-only table caption here.
	 * @param array  $lineup Slot (1-9) => ordered list of appearances, from Basebelles_API::get_live_feed().
	 * @return void
	 */
	private static function render_lineup_table( $abbreviation, array $lineup ) {
		if ( empty( $lineup ) ) {
			echo '<p class="tg-empty">Lineup unavailable.</p>';
			return;
		}
		?>
		<table class="tg-lineup">
			<caption class="screen-reader-text"><?php echo esc_html( $abbreviation ); ?> batting order</caption>
			<thead>
				<tr>
					<th scope="col" class="tg-lineup-name">Batter</th>
					<th scope="col">AVG</th>
					<th scope="col">AB</th>
					<th scope="col">R</th>
					<th scope="col">H</th>
					<th scope="col">RBI</th>
					<th scope="col">BB</th>
					<th scope="col">K</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $lineup as $slot => $appearances ) : ?>
					<?php $last_index = count( $appearances ) - 1; ?>
					<?php foreach ( $appearances as $i => $player ) : ?>
						<tr<?php echo $i < $last_index ? ' class="tg-lineup-replaced"' : ''; ?>>
							<td class="tg-lineup-name">
								<?php if ( 0 === $i ) : ?>
									<?php echo esc_html( $slot . '. ' ); ?>
								<?php else : ?>
									<span class="tg-lineup-sub-arrow" aria-hidden="true">&#8627;</span>
								<?php endif; ?>
								<?php echo esc_html( $player['name'] ); ?> <span class="tg-lineup-pos"><?php echo esc_html( $player['position'] ); ?></span>
							</td>
							<td><?php echo esc_html( $player['avg'] ?? '.---' ); ?></td>
							<td><?php echo esc_html( $player['ab'] ); ?></td>
							<td><?php echo esc_html( $player['r'] ); ?></td>
							<td><?php echo esc_html( $player['h'] ); ?></td>
							<td><?php echo esc_html( $player['rbi'] ); ?></td>
							<td><?php echo esc_html( $player['bb'] ); ?></td>
							<td><?php echo esc_html( $player['k'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render one team's full pitching log (every pitcher used, starter first).
	 *
	 * @param string $abbreviation Team abbreviation, used as a screen-reader-only caption.
	 * @param array  $pitching_log Result of Basebelles_API's pitching-log normalization.
	 * @return void
	 */
	private static function render_pitching_log_table( $abbreviation, array $pitching_log ) {
		if ( empty( $pitching_log ) ) {
			echo '<p class="tg-empty">No pitchers yet.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		?>
		<table class="tg-pitching-log">
			<caption class="screen-reader-text"><?php echo esc_html( $abbreviation ); ?> pitching</caption>
			<thead>
				<tr>
					<th scope="col" class="tg-lineup-name">Pitcher</th>
					<th scope="col">IP</th>
					<th scope="col">H</th>
					<th scope="col">R</th>
					<th scope="col">ER</th>
					<th scope="col">BB</th>
					<th scope="col">K</th>
					<th scope="col">P</th>
					<th scope="col">ERA</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pitching_log as $pitcher ) : ?>
					<tr>
						<td class="tg-lineup-name">
							<?php echo esc_html( $pitcher['name'] ); ?>
							<?php if ( ! empty( $pitcher['decision'] ) ) : ?>
								<span class="tg-decision"><?php echo esc_html( $pitcher['decision'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $pitcher['ip'] ); ?></td>
						<td><?php echo esc_html( $pitcher['h'] ); ?></td>
						<td><?php echo esc_html( $pitcher['r'] ); ?></td>
						<td><?php echo esc_html( $pitcher['er'] ); ?></td>
						<td><?php echo esc_html( $pitcher['bb'] ); ?></td>
						<td><?php echo esc_html( $pitcher['k'] ); ?></td>
						<td><?php echo esc_html( $pitcher['pitches'] ); ?></td>
						<td><?php echo esc_html( $pitcher['era'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
