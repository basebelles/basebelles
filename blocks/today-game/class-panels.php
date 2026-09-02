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
	 * Determine which phase a game is in: far, near, live, or final.
	 *
	 * @param array $game Normalized game array from Basebelles_API::get_guardians_today_game().
	 * @return string
	 */
	public static function get_phase( array $game ) {
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
				<button type="button" class="tg-tab is-active" data-tab="score" role="tab" aria-selected="true">Score</button>
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
		if ( 'far' === $phase ) {
			return self::render_far_score( $game );
		}

		if ( 'near' === $phase ) {
			return self::render_countdown( $game );
		}

		if ( 'live' === $phase ) {
			return self::render_at_bat_now( $live_feed );
		}

		return self::render_final_score( $game );
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
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	private static function render_at_bat_now( array $live_feed ) {
		$current_play = $live_feed['current_play'] ?? array();

		if ( empty( $current_play ) ) {
			return '<p class="tg-empty">Live &mdash; check the Plays tab for the latest.</p>';
		}

		$bases = $current_play['bases'] ?? array();
		$half  = 'top' === strtolower( $current_play['half'] ) ? 'Top' : 'Bottom';

		ob_start();
		?>
		<div class="tg-at-bat">
			<div class="tg-at-bat-inning"><?php echo esc_html( $half . ' ' . $current_play['inning'] ); ?> &middot; <?php echo esc_html( $current_play['outs'] ); ?> out</div>
			<div class="tg-at-bat-matchup">
				<strong><?php echo esc_html( $current_play['batter'] ); ?></strong> at bat vs <strong><?php echo esc_html( $current_play['pitcher'] ); ?></strong>
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
		$d_state      = $game['detailed_state']['state'] ?? '';
		$reason       = $game['detailed_state']['reason'] ?? '';

		ob_start();
		?>
		<div class="game-scores">
			<div class="score-away"><?php echo esc_html( $game['scores']['away'] ); ?></div>
			<div class="game-meta">
				<?php if ( 'Postponed' === $d_state ) : ?>
					<strong class="status-ppd">
						PPD
						<?php if ( ! empty( $reason ) ) : ?>
							<br /><small><?php echo esc_html( $reason ); ?></small>
						<?php endif; ?>
					</strong>
				<?php elseif ( ! empty( $game['scores']['isFinal'] ) ) : ?>
					<?php $winner = $home_or_away === $game['scores']['winner'] ? 'guards-win' : 'oppo-win'; ?>
					<strong class="<?php echo esc_attr( $winner ); ?>">
						<?php if ( 'away' === $game['scores']['winner'] ) : ?><?php echo esc_html( '➜ ' ); ?><?php endif; ?>
						FINAL
						<?php if ( ! empty( $game['scores']['inning'] ) && $game['scores']['inning'] > 9 ) : ?>
							<?php echo esc_html( ' / ' . $game['scores']['inning'] ); ?>
						<?php endif; ?>
						<?php if ( 'home' === $game['scores']['winner'] ) : ?><?php echo esc_html( ' ➜' ); ?><?php endif; ?>
					</strong>
				<?php else : ?>
					<strong>LIVE</strong>
				<?php endif; ?>
			</div>
			<div class="score-home"><?php echo esc_html( $game['scores']['home'] ); ?></div>
		</div>
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
				<li>
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
	 * once the game is underway, the matchup's season ERA/record is stale, so show the starters'
	 * in-game lines instead.
	 *
	 * @param array  $game Normalized game array.
	 * @param string $phase Game phase.
	 * @param array  $live_feed Live feed data, or empty array.
	 * @return string
	 */
	public static function render_stats_panel( array $game, $phase, array $live_feed ) {
		if ( in_array( $phase, array( 'live', 'final' ), true ) ) {
			return self::render_pitching_lines( $game, $live_feed );
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
	 * Starting pitchers' in-game lines (IP, ER, K, BB, pitch count), once the game has started.
	 *
	 * Falls back to the pregame matchup if the live feed hasn't loaded a pitcher yet.
	 *
	 * @param array $game Normalized game array.
	 * @param array $live_feed Live feed data, or empty array.
	 * @return string
	 */
	private static function render_pitching_lines( array $game, array $live_feed ) {
		// pitchers[0] is always the starter -- see Basebelles_API::normalize_pitching_log().
		$away = $live_feed['pitchers']['away'][0] ?? array();
		$home = $live_feed['pitchers']['home'][0] ?? array();

		if ( empty( $away ) && empty( $home ) ) {
			return self::render_pitching_matchup( $game );
		}

		ob_start();
		?>
		<div class="probable-pitchers">
			<div class="pitcher-away">
				<div class="pitcher-name"><?php echo esc_html( $away['name'] ?? 'TBD' ); ?></div>
				<div class="pitcher-stats">
					<span><?php echo esc_html( ( $away['ip'] ?? '0.0' ) . ' IP' ); ?></span>
					&bull;
					<span><?php echo esc_html( ( $away['er'] ?? 0 ) . ' ER' ); ?></span>
					&bull;
					<span><?php echo esc_html( ( $away['k'] ?? 0 ) . ' K' ); ?></span>
				</div>
			</div>
			<div class="game-meta">v.s.</div>
			<div class="pitcher-home">
				<div class="pitcher-name"><?php echo esc_html( $home['name'] ?? 'TBD' ); ?></div>
				<div class="pitcher-stats">
					<span><?php echo esc_html( ( $home['ip'] ?? '0.0' ) . ' IP' ); ?></span>
					&bull;
					<span><?php echo esc_html( ( $home['er'] ?? 0 ) . ' ER' ); ?></span>
					&bull;
					<span><?php echo esc_html( ( $home['k'] ?? 0 ) . ' K' ); ?></span>
				</div>
			</div>
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

		ob_start();
		?>
		<div class="tg-subtabs">
			<div class="tg-subtab-bar" role="tablist">
				<button type="button" class="tg-subtab is-active" data-subtab="away" role="tab" aria-selected="true"><?php echo esc_html( $away_abbr ); ?></button>
				<button type="button" class="tg-subtab" data-subtab="home" role="tab" aria-selected="false"><?php echo esc_html( $home_abbr ); ?></button>
			</div>
			<div class="tg-subpanel is-active" data-subpanel="away">
				<h4 class="tg-section-label">Batting</h4>
				<?php self::render_lineup_table( $away_abbr, $away_lineup ); ?>
				<h4 class="tg-section-label">Pitching</h4>
				<?php self::render_pitching_log_table( $away_abbr, $away_pitching ); ?>
			</div>
			<div class="tg-subpanel" data-subpanel="home">
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
	 * Render one team's batting-order table.
	 *
	 * @param string $abbreviation Team abbreviation. The active sub-tab button already shows it
	 *                             visually, so it's a screen-reader-only table caption here.
	 * @param array  $lineup Ordered list of lineup entries from Basebelles_API::get_live_feed().
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
				<?php foreach ( $lineup as $index => $player ) : ?>
					<tr>
						<td class="tg-lineup-name"><?php echo esc_html( ( $index + 1 ) . '. ' . $player['name'] ); ?> <span class="tg-lineup-pos"><?php echo esc_html( $player['position'] ); ?></span></td>
						<td><?php echo esc_html( $player['avg'] ?? '.---' ); ?></td>
						<td><?php echo esc_html( $player['ab'] ); ?></td>
						<td><?php echo esc_html( $player['r'] ); ?></td>
						<td><?php echo esc_html( $player['h'] ); ?></td>
						<td><?php echo esc_html( $player['rbi'] ); ?></td>
						<td><?php echo esc_html( $player['bb'] ); ?></td>
						<td><?php echo esc_html( $player['k'] ); ?></td>
					</tr>
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
