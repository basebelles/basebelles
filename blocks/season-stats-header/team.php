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

/*
 * Team colours (team-info/list.json, primary first): the header takes the primary as its
 * background and the second colour as a stripe. Text is the site's cream unless that fails
 * WCAG AA (4.5:1) on the primary, in which case it's dark ink (Giants, Marlins, Orioles...).
 */
$bb_hex_colors = array();
foreach ( (array) ( $team_data['colors'] ?? array() ) as $bb_color ) {
	$bb_hex = strtoupper( (string) ( $bb_color['hex'] ?? '' ) );
	if ( preg_match( '/^#[0-9A-F]{6}$/', $bb_hex ) ) {
		$bb_hex_colors[] = $bb_hex;
	}
}

/**
 * WCAG relative luminance of a #RRGGBB colour.
 *
 * @param string $hex Colour.
 * @return float
 */
$bb_luminance = static function ( $hex ) {
	$channels = array_map(
		static function ( $pair ) {
			$value = hexdec( $pair ) / 255;
			return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
		},
		str_split( substr( $hex, 1 ), 2 )
	);

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
};

$bb_contrast = static function ( $one, $two ) use ( $bb_luminance ) {
	$first  = $bb_luminance( $one );
	$second = $bb_luminance( $two );

	return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
};

$bb_team_style = '';
$bb_team_light = false;

if ( $bb_hex_colors ) {
	$bb_cream      = '#F3EFE0';
	$bb_ink        = '#111111';
	$bb_team_bg    = $bb_hex_colors[0];
	$bb_team_light = $bb_contrast( $bb_team_bg, $bb_cream ) < 4.5;
	$bb_team_fg    = $bb_team_light ? $bb_ink : $bb_cream;
	$bb_team_style = sprintf(
		'--bb-team-bg:%1$s;--bb-team-fg:%2$s;--bb-team-accent:%3$s;',
		$bb_team_bg,
		$bb_team_fg,
		$bb_hex_colors[1] ?? $bb_team_fg
	);
}

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

// Post-season context: the tag beside the name, and the series for "Next". Built from the
// team's own post-season schedule, so it works for any team, not only the Guardians.
$bb_tag        = '';
$bb_result     = null; // Last decided series: array( round, 'Won'|'Lost', our wins, their wins ).
$bb_next       = null;
$bb_postseason = null;

if ( $bb_in_postseason ) {
	if ( method_exists( $api, 'get_postseason_status' ) ) {
		$bb_postseason = $api->get_postseason_status( $season_year, $team_id );
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
				$bb_eliminator = (string) ( $bb_current['opponent']['abbreviation'] ?? '' );
				$bb_tag        = '' !== $bb_eliminator
					/* translators: 1: round name (ALDS), 2: abbreviation of the team that won the series */
					? sprintf( __( 'Eliminated in %1$s by %2$s', 'basebelles' ), $bb_current['round_short'], $bb_eliminator )
					: __( 'Eliminated', 'basebelles' );
				break;
			case 'champion':
				$bb_tag = __( 'World Series champions', 'basebelles' );
				break;
			default:
				$bb_tag = __( 'Missed playoffs', 'basebelles' );
		}
	} else {
		// No schedule to go on (the API is down): the standings can still say whether they got in.
		$bb_seed = $api->get_team_playoff_seed( $team_id, $bb_league, $season_year );
		if ( null === $bb_seed ) {
			$bb_tag = __( 'Missed playoffs', 'basebelles' );
		} else {
			/* translators: %d: playoff seed */
			$bb_tag = sprintf( __( '#%d seed', 'basebelles' ), $bb_seed );
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
if ( '' !== $bb_team_style ) {
	$bb_classes[] = 'has-team-colors';
}
if ( $bb_team_light ) {
	$bb_classes[] = 'is-light';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $bb_classes ) ); ?>"<?php echo '' !== $bb_team_style ? ' style="' . esc_attr( $bb_team_style ) . '"' : ''; ?>>
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
