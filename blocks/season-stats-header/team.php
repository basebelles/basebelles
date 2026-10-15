<?php
/**
 * Team archive header: identity (logo, name, league) plus a stat strip that adapts to the
 * season type. Included by render.php, which has already set $api, $team_id, $team_data,
 * $stats, $season_type, $season_year, $fell_back, $is_past and $bb_has_value.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_name   = (string) ( $team_data['name'] ?? $queried_season_term->name );
$bb_league = (string) ( $team_data['league'] ?? '' );
$bb_logo   = $api->get_team_logo_url( (string) ( $team_data['slug'] ?? '' ) );

$bb_type_labels = array(
	'springTraining' => __( 'Spring Training', 'basebelles' ),
	'regularSeason'  => __( 'Regular Season', 'basebelles' ),
	'postseason'     => __( 'Post Season', 'basebelles' ),
);

// A team that has no post-season games shows its final regular season, and says so.
$bb_in_postseason = $fell_back || 'postseason' === $season_type;
$bb_type_label    = $fell_back
	? __( 'Final Regular Season', 'basebelles' )
	: ( $bb_type_labels[ $season_type ] ?? $bb_type_labels['regularSeason'] );

$bb_subtitle = trim( $bb_league . ' · ' . $season_year . ' ' . $bb_type_label, ' ·' );

/**
 * Add a signed class so a positive or negative number reads at a glance. The sign is in the
 * value itself, so colour is only reinforcement.
 *
 * @param string $value Value like "+10" or "-4".
 * @return string
 */
$bb_sign_class = static function ( $value ) {
	$first = substr( trim( (string) $value ), 0, 1 );

	return '+' === $first ? 'is-positive' : ( '-' === $first ? 'is-negative' : '' );
};

// Drop the division from "3rd (AL Central)"; the subtitle already says it.
$bb_rank = trim( (string) preg_replace( '/\s*\(.*\)\s*$/', '', (string) ( $stats['rank'] ?? '' ) ) );

// Post-season context: the tag beside the name, and (for the Guardians) the series for "Next".
$bb_tag         = '';
$bb_result      = null; // Last decided series: array( round, 'Won'|'Lost', our wins, their wins ).
$bb_next        = null;
$bb_postseason  = null;
$bb_is_guardian = Basebelles_API::GUARDIANS_TEAM_ID === $team_id;

if ( $bb_in_postseason ) {
	if ( $bb_is_guardian && method_exists( $api, 'get_postseason_status' ) ) {
		$bb_postseason = $api->get_postseason_status( $season_year );
	}

	if ( is_array( $bb_postseason ) && ! is_wp_error( $bb_postseason ) ) {
		$bb_current = is_array( $bb_postseason['current'] ?? null ) ? $bb_postseason['current'] : null;

		if ( $bb_current && ! empty( $bb_current['complete'] ) ) {
			$bb_result = array( $bb_current['round_short'], ! empty( $bb_current['won'] ), (int) $bb_current['guardians_wins'], (int) $bb_current['opponent_wins'] );
		}

		switch ( (string) ( $bb_postseason['state'] ?? '' ) ) {
			case 'in_series':
				$bb_tag = $bb_current['round_short'] . ' · ' . $bb_current['status_label'];
				foreach ( (array) $bb_current['games'] as $bb_game ) {
					if ( ! empty( $bb_game['next'] ) ) {
						$bb_next = $bb_game;
						break;
					}
				}
				break;
			case 'between_rounds':
				/* translators: %s: round name, e.g. ALDS */
				$bb_tag = sprintf( __( 'Won %s', 'basebelles' ), $bb_current['round_short'] );
				break;
			case 'awaiting':
				$bb_tag = ! empty( $bb_postseason['had_bye'] ) ? __( 'First-round bye', 'basebelles' ) : __( 'Playoffs', 'basebelles' );
				break;
			case 'eliminated':
				$bb_tag = __( 'Eliminated', 'basebelles' );
				break;
			case 'champion':
				$bb_tag = __( 'World Series champions', 'basebelles' );
				break;
			default:
				$bb_tag = __( 'Missed playoffs', 'basebelles' );
		}
	} elseif ( ! $bb_is_guardian ) {
		// Only the Guardians' series are tracked, so for anyone else the tag is the one thing the
		// standings can say for certain: whether they got in.
		// The one exception: a team that played the Guardians shows up as their opponent, so the
		// result of that series is known from the Guardians' data.
		$bb_vs_guardians = method_exists( $api, 'get_postseason_status' ) ? $api->get_postseason_status( $season_year ) : null;

		if ( is_array( $bb_vs_guardians ) && ! is_wp_error( $bb_vs_guardians ) ) {
			foreach ( (array) ( $bb_vs_guardians['series'] ?? array() ) as $bb_series ) {
				if ( $team_id !== (int) ( $bb_series['opponent']['id'] ?? 0 ) || empty( $bb_series['complete'] ) ) {
					continue;
				}

				$bb_guardians_abbr = (string) ( $bb_series['guardians']['abbreviation'] ?? 'CLE' );
				$bb_result         = array( $bb_series['round_short'], empty( $bb_series['won'] ), (int) $bb_series['opponent_wins'], (int) $bb_series['guardians_wins'] );

				if ( ! empty( $bb_series['won'] ) ) {
					/* translators: 1: round name (ALDS), 2: team abbreviation that won the series */
					$bb_tag = sprintf( __( 'Eliminated in %1$s by %2$s', 'basebelles' ), $bb_series['round_short'], $bb_guardians_abbr );
				} else {
					/* translators: 1: round name (ALDS), 2: team abbreviation that lost the series */
					$bb_tag = sprintf( __( 'Won %1$s over %2$s', 'basebelles' ), $bb_series['round_short'], $bb_guardians_abbr );
				}
			}
		}

		if ( '' === $bb_tag ) {
			$bb_seed = $api->get_team_playoff_seed( $team_id, $bb_league, $season_year );
			if ( null === $bb_seed ) {
				$bb_tag = __( 'Missed playoffs', 'basebelles' );
			} else {
				/* translators: %d: playoff seed */
				$bb_tag = sprintf( __( '#%d seed', 'basebelles' ), $bb_seed );
			}
		}
	}
}

// Wild Card Series: a bye, or how it went, shown ahead of the later rounds. Skipped when the
// Wild Card is the last series (the result cell already covers it).
$bb_wild_card = null; // array( value, class ).

if ( 'postseason' === $season_type && ! $fell_back ) {
	if ( is_array( $bb_postseason ) && ! is_wp_error( $bb_postseason ) ) {
		$bb_wc_series = $bb_postseason['series']['F'] ?? null;

		if ( ! empty( $bb_postseason['had_bye'] ) ) {
			$bb_wild_card = array( __( 'Bye', 'basebelles' ), '' );
		} elseif ( is_array( $bb_wc_series ) && ! empty( $bb_wc_series['complete'] ) && 'F' !== ( $bb_postseason['current']['game_type'] ?? '' ) ) {
			$bb_wild_card = array(
				sprintf( '%s %d – %d', ! empty( $bb_wc_series['won'] ) ? __( 'Won', 'basebelles' ) : __( 'Lost', 'basebelles' ), (int) $bb_wc_series['guardians_wins'], (int) $bb_wc_series['opponent_wins'] ),
				! empty( $bb_wc_series['won'] ) ? 'is-positive' : 'is-negative',
			);
		}
	} elseif ( ! $bb_is_guardian && $bb_result && 'Wild Card' !== $bb_result[0] ) {
		// Only the Guardians' series are tracked. A team that reached a later round against them
		// either had a bye (top two seeds) or won the Wild Card Series to get there.
		$bb_wc_seed = $api->get_team_playoff_seed( $team_id, $bb_league, $season_year );
		if ( null !== $bb_wc_seed ) {
			$bb_wild_card = $bb_wc_seed <= Basebelles_Postseason::BYE_SEEDS
				? array( __( 'Bye', 'basebelles' ), '' )
				: array( __( 'Won', 'basebelles' ), 'is-positive' );
		}
	}
}

// The strip: label, value, optional class.
$bb_cells = array();

if ( 'postseason' === $season_type && ! $fell_back ) {
	if ( $bb_wild_card ) {
		$bb_cells[] = array( __( 'Wild Card', 'basebelles' ), $bb_wild_card[0], $bb_wild_card[1] );
	}

	// Then how the last series ended. The overall game record is dropped once a series is
	// decided: "4 – 3" reads as a winning run even when the team was knocked out. Run
	// differential is left out for the post season for the same reason: it's across all games.
	if ( $bb_result ) {
		$bb_cells[] = array(
			$bb_result[0],
			sprintf( '%s %d – %d', $bb_result[1] ? __( 'Won', 'basebelles' ) : __( 'Lost', 'basebelles' ), $bb_result[2], $bb_result[3] ),
			$bb_result[1] ? 'is-positive' : 'is-negative',
		);
	}
	if ( ! $bb_result ) {
		$bb_cells[] = array( __( 'Post-season', 'basebelles' ), sprintf( '%d – %d', (int) $stats['wins'], (int) $stats['losses'] ), '' );
	}
	$bb_regular = $api->get_season_archive_stats( $season_year, 'regularSeason', $team_id );
	if ( ! is_wp_error( $bb_regular ) ) {
		$bb_cells[] = array( __( 'Reg. season', 'basebelles' ), sprintf( '%d – %d', (int) $bb_regular['wins'], (int) $bb_regular['losses'] ), '' );
	}

	if ( $bb_next ) {
		$bb_cells[] = array( __( 'Next', 'basebelles' ), $bb_next['label'] . ' · ' . $bb_next['value'], 'is-small' );
	}
} elseif ( 'springTraining' === $season_type ) {
	$bb_cells[] = array( __( 'Record', 'basebelles' ), sprintf( '%d – %d', (int) $stats['wins'], (int) $stats['losses'] ), '' );
	if ( $bb_has_value( $stats['pct'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Win %', 'basebelles' ), (string) $stats['pct'], '' );
	}
} else {
	$bb_cells[] = array( __( 'Record', 'basebelles' ), sprintf( '%d – %d', (int) $stats['wins'], (int) $stats['losses'] ), '' );
	if ( $bb_has_value( $bb_rank ) ) {
		$bb_cells[] = array( __( 'Division', 'basebelles' ), $bb_rank, '' );
	}
	if ( $bb_has_value( $stats['run_diff'] ?? '' ) ) {
		$bb_cells[] = array( __( 'Run diff', 'basebelles' ), (string) $stats['run_diff'], $bb_sign_class( $stats['run_diff'] ) );
	}

	// Live-season detail. Not worth showing once the regular season is over and we're only
	// showing the final line.
	if ( ! $fell_back ) {
		if ( $bb_has_value( $stats['games_back'] ?? '' ) && '0' !== (string) $stats['games_back'] ) {
			$bb_cells[] = array( __( 'GB', 'basebelles' ), (string) $stats['games_back'], '' );
		}
		if ( $bb_has_value( $stats['streak'] ?? '' ) ) {
			$bb_cells[] = array( __( 'Streak', 'basebelles' ), (string) $stats['streak'], 'L' === substr( (string) $stats['streak'], 0, 1 ) ? 'is-negative' : '' );
		}
		if ( $bb_has_value( $stats['last_ten'] ?? '' ) ) {
			$bb_cells[] = array( __( 'Last 10', 'basebelles' ), str_replace( '-', ' – ', (string) $stats['last_ten'] ), '' );
		}
	}
}

$bb_classes = array(
	'basebelles-season-stats-header',
	'basebelles-season-stats-header--team',
	'springTraining' === $season_type ? 'basebelles-season-stats-header-spring' : ( 'postseason' === $season_type ? 'basebelles-season-stats-header-postseason' : 'basebelles-season-stats-header-regular' ),
);
if ( $is_past ) {
	$bb_classes[] = 'is-past';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $bb_classes ) ); ?>">
	<div class="season-stats-inner season-team">
		<div class="season-stats-ghost" aria-hidden="true"></div>
		<div class="season-team-main">
			<?php if ( '' !== $bb_logo ) : ?>
				<img class="season-team-logo" src="<?php echo esc_url( $bb_logo ); ?>" alt="" width="84" height="84" loading="lazy" />
			<?php endif; ?>
			<div class="season-team-id">
				<h4 class="season-team-name"><?php echo esc_html( $bb_name ); ?></h4>
				<div class="season-team-sub"><?php echo esc_html( $bb_subtitle ); ?></div>
			</div>
			<?php if ( '' !== $bb_tag ) : ?>
				<span class="season-team-tag"><?php echo esc_html( $bb_tag ); ?></span>
			<?php endif; ?>
		</div>
		<dl class="season-team-strip">
			<?php foreach ( $bb_cells as $bb_cell ) : ?>
				<div class="season-team-stat <?php echo esc_attr( $bb_cell[2] ); ?>">
					<dt class="season-stats-label"><?php echo esc_html( $bb_cell[0] ); ?></dt>
					<dd class="season-team-value"><?php echo esc_html( $bb_cell[1] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>
</div>
