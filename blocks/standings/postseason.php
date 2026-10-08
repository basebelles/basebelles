<?php
/**
 * Postseason variant of the standings ticker. Included by render.php, which has already
 * fetched $postseason (see Basebelles_Postseason::build_status() for its shape) and defined
 * $basebelles_render_ticker_row.
 *
 * Closures rather than functions, because render.php can be included more than once a request
 * (and is, in the tests) and a named function would be redeclared.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_ps_state   = (string) ( $postseason['state'] ?? '' );
$bb_ps_current = is_array( $postseason['current'] ?? null ) ? $postseason['current'] : null;

$bb_ps_short = array(
	'F' => 'Wild Card',
	'D' => 'ALDS',
	'L' => 'ALCS',
	'W' => 'World Series',
);
$bb_ps_full  = array(
	'F' => 'AL Wild Card Series',
	'D' => 'AL Division Series',
	'L' => 'AL Championship Series',
	'W' => 'World Series',
);

// Before the first series is scheduled there's no team object from the schedule to lean on.
$bb_ps_us = $bb_ps_current ? $bb_ps_current['guardians'] : array(
	'abbreviation' => 'CLE',
	'seed'         => $postseason['seed'] ?? null,
	'logo_url'     => (string) ( $postseason['guardians_logo_url'] ?? '' ),
);

/**
 * One team in the matchup: logo, abbreviation, seed. Logo alt is empty because the
 * abbreviation right next to it already names the team.
 *
 * @param array|null $team Team array, or null for a not-yet-known opponent.
 * @return string Escaped markup.
 */
$bb_ps_team = static function ( $team ) {
	if ( empty( $team ) || '' === (string) ( $team['abbreviation'] ?? '' ) ) {
		return '<span class="bb-team is-tbd">TBD</span>';
	}

	$html = '<span class="bb-team">';

	if ( ! empty( $team['logo_url'] ) ) {
		$html .= '<img class="bb-team-logo" src="' . esc_url( $team['logo_url'] ) . '" alt="" width="24" height="24" loading="lazy" />';
	}

	$html .= '<span class="bb-team-abbr">' . esc_html( $team['abbreviation'] ) . '</span>';

	if ( null !== ( $team['seed'] ?? null ) ) {
		$html .= ' <span class="bb-team-seed">(' . (int) $team['seed'] . ')</span>';
	}

	return $html . '</span>';
};

$bb_ps_matchup = static function ( $us, $them ) use ( $bb_ps_team ) {
	return $bb_ps_team( $us ) . ' <span class="bb-matchup-vs">vs</span> ' . $bb_ps_team( $them );
};

$bb_ps_wild_card = array();
$bb_ps_wc_label  = (string) ( $postseason['wild_card_label'] ?? '' );

// The Wild Card column is context for later rounds; while the Wild Card Series is the one being
// played, the round column already says so.
if ( '' !== $bb_ps_wc_label && ( ! $bb_ps_current || 'F' !== $bb_ps_current['game_type'] ) ) {
	$bb_ps_wild_card = array(
		'label' => 'Wild Card',
		'value' => $bb_ps_wc_label,
		'class' => 0 === strpos( $bb_ps_wc_label, 'Lost' ) ? 'is-negative' : '',
	);
}

$primary = array();
$games   = array();

switch ( $bb_ps_state ) {
	case 'in_series':
		$bb_ps_lead = $bb_ps_current['guardians_wins'] <=> $bb_ps_current['opponent_wins'];
		$primary    = array(
			array(
				'label' => 'Round',
				'value' => $bb_ps_current['round'],
				'class' => 'is-round',
			),
			array(
				'label' => 'Matchup',
				'html'  => $bb_ps_matchup( $bb_ps_current['guardians'], $bb_ps_current['opponent'] ),
				'class' => 'is-matchup',
			),
			array(
				'label' => 'Series · Best of ' . (int) $bb_ps_current['games_in_series'],
				'value' => $bb_ps_current['status_label'],
				'class' => ( 1 === $bb_ps_lead ) ? 'is-positive' : ( ( -1 === $bb_ps_lead ) ? 'is-negative' : '' ),
			),
		);
		$games      = $bb_ps_current['games'];
		break;

	case 'awaiting':
		$bb_ps_next = (string) ( $postseason['next_round'] ?? 'D' );
		$primary    = array(
			array(
				'label' => 'Round',
				'value' => $bb_ps_full[ $bb_ps_next ] ?? '',
				'class' => 'is-round',
			),
			array(
				'label' => 'Matchup',
				'html'  => $bb_ps_matchup( $bb_ps_us, null ),
				'class' => 'is-matchup',
			),
		);
		break;

	case 'between_rounds':
		$bb_ps_next = (string) ( $postseason['next_round'] ?? '' );
		$primary    = array(
			array(
				'label' => 'Round',
				'value' => $bb_ps_short[ $bb_ps_next ] ?? '',
				'class' => 'is-round',
			),
			array(
				'label' => 'Matchup',
				'html'  => $bb_ps_matchup( $bb_ps_us, null ),
				'class' => 'is-matchup',
			),
			array(
				'label' => $bb_ps_current['round_short'],
				'value' => $bb_ps_current['result_label'],
				'class' => 'is-positive',
			),
		);
		break;

	case 'eliminated':
	case 'champion':
		$bb_ps_won = 'champion' === $bb_ps_state;
		$primary   = array(
			array(
				'label' => 'Season',
				'value' => $bb_ps_won ? 'World Series champions' : 'Eliminated',
				'class' => 'is-round',
			),
			array(
				'label' => 'Final series',
				'html'  => $bb_ps_matchup( $bb_ps_current['guardians'], $bb_ps_current['opponent'] ),
				'class' => 'is-matchup',
			),
			array(
				'label' => $bb_ps_current['round_short'],
				'value' => ( $bb_ps_won ? 'Won ' : 'Lost ' ) . (int) $bb_ps_current['guardians_wins'] . '-' . (int) $bb_ps_current['opponent_wins'],
				'class' => $bb_ps_won ? 'is-positive' : 'is-negative',
			),
			array(
				'label' => 'Postseason',
				'value' => (string) ( $postseason['postseason_record'] ?? '' ),
			),
		);
		$games     = $bb_ps_current['games'];
		$bb_ps_wild_card = array();
		break;
}

if ( ! empty( $bb_ps_wild_card ) ) {
	$primary[] = $bb_ps_wild_card;
}

$bb_ps_state_class = array(
	'win'        => 'is-positive',
	'loss'       => 'is-negative',
	'live'       => 'is-live',
	'not_needed' => 'is-dim',
	'scheduled'  => '',
);

$secondary = array();

foreach ( $games as $bb_ps_game ) {
	$bb_ps_classes = array_filter(
		array(
			$bb_ps_state_class[ $bb_ps_game['state'] ] ?? '',
			! empty( $bb_ps_game['next'] ) ? 'is-next' : '',
		)
	);

	$secondary[] = array(
		'label' => $bb_ps_game['label'],
		'value' => $bb_ps_game['value'],
		'class' => implode( ' ', $bb_ps_classes ),
	);
}
?>

<div class="basebelles-standings is-postseason is-<?php echo esc_attr( str_replace( '_', '-', $bb_ps_state ) ); ?>">
	<div class="bb-ticker">
		<?php $basebelles_render_ticker_row( $primary, 'is-primary' ); ?>
		<?php if ( ! empty( $secondary ) ) : ?>
			<?php $basebelles_render_ticker_row( $secondary, 'is-secondary is-games' ); ?>
		<?php endif; ?>
	</div>
</div>
