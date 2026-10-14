<?php
/**
 * Game Hub: the postseason series card. Included by ../render.php, which has set
 * $bb_hub_postseason (see Basebelles_Postseason::build_status()).
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_ser_state   = (string) ( $bb_hub_postseason['state'] ?? '' );
$bb_ser_current = is_array( $bb_hub_postseason['current'] ?? null ) ? $bb_hub_postseason['current'] : null;
$bb_ser_live    = 'in_series' === $bb_ser_state && $bb_ser_current;

if ( $bb_ser_live ) {
	$bb_ser_eyebrow = $bb_ser_current['round'] . ' · Best of ' . (int) $bb_ser_current['games_in_series'];
	$bb_ser_status  = (string) $bb_ser_current['status_label'];
	$bb_ser_lead    = (int) $bb_ser_current['guardians_wins'] <=> (int) $bb_ser_current['opponent_wins'];
	$bb_ser_games   = (array) $bb_ser_current['games'];
} else {
	// Waiting on a bye, or between rounds: the next round, no opponent yet.
	$bb_ser_eyebrow = Basebelles_Postseason::ROUND_FULL[ (string) ( $bb_hub_postseason['next_round'] ?? 'D' ) ] ?? '';
	$bb_ser_status  = 'Opponent TBD';
	$bb_ser_lead    = 0;
	$bb_ser_games   = array();
}

$bb_ser_status_class = array(
	1  => 'is-positive',
	0  => '',
	-1 => 'is-negative',
);

$bb_ser_cell_class = array(
	'win'        => 'is-won',
	'loss'       => 'is-lost',
	'live'       => 'is-live',
	'not_needed' => 'is-dim',
	'scheduled'  => '',
);

// "Wild Card bye · ALDS won 3-2 vs CWS".
$bb_ser_road = array();

foreach ( (array) ( $bb_hub_postseason['road'] ?? array() ) as $bb_ser_leg ) {
	$bb_ser_road[] = $bb_ser_leg['round'] . ' ' . lcfirst( (string) $bb_ser_leg['result'] );
}

$bb_ser_footnote = false;
?>

<section class="bb-hub-card bb-hub-series">
	<h3 class="bb-hub-eyebrow"><?php echo esc_html( $bb_ser_eyebrow ); ?></h3>
	<p class="bb-hub-series-status <?php echo esc_attr( $bb_ser_status_class[ $bb_ser_lead ] ); ?>"><?php echo esc_html( $bb_ser_status ); ?></p>

	<?php if ( ! empty( $bb_ser_games ) ) : ?>
		<ol class="bb-hub-series-games">
			<?php foreach ( $bb_ser_games as $bb_ser_game ) : ?>
				<?php
				// The cell label is "G3 · @CWS" or "G1 · vs CWS"; a not-yet-scheduled slot is just "G5".
				$bb_ser_where = '';

				if ( false !== strpos( (string) $bb_ser_game['label'], ' · ' ) ) {
					$bb_ser_where = substr( (string) $bb_ser_game['label'], strpos( (string) $bb_ser_game['label'], ' · ' ) + strlen( ' · ' ) );
					$bb_ser_where = preg_replace( '/^@\s*/', '@ ', $bb_ser_where );
				}

				$bb_ser_classes = array_filter(
					array(
						'bb-hub-series-game',
						$bb_ser_cell_class[ $bb_ser_game['state'] ] ?? '',
						! empty( $bb_ser_game['next'] ) ? 'is-next' : '',
					)
				);

				$bb_ser_maybe = ! empty( $bb_ser_game['if_necessary'] );

				if ( $bb_ser_maybe ) {
					$bb_ser_footnote = true;
				}
				?>
				<li class="<?php echo esc_attr( implode( ' ', $bb_ser_classes ) ); ?>"<?php echo ! empty( $bb_ser_game['next'] ) ? ' aria-current="true"' : ''; ?>>
					<span class="bb-hub-series-num">G<?php echo (int) $bb_ser_game['number']; ?></span>
					<span class="bb-hub-series-where"><?php echo esc_html( $bb_ser_where ); ?></span>
					<span class="bb-hub-series-value">
						<?php echo esc_html( (string) $bb_ser_game['value'] ); ?><?php if ( $bb_ser_maybe ) : ?><span class="bb-footnote-mark" aria-hidden="true">*</span><span class="screen-reader-text"> (if necessary)</span><?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php if ( ! empty( $bb_ser_road ) || $bb_ser_footnote ) : ?>
		<p class="bb-hub-foot">
			<?php if ( ! empty( $bb_ser_road ) ) : ?>
				<strong>Road here:</strong> <?php echo esc_html( implode( ' · ', $bb_ser_road ) ); ?>.
			<?php endif; ?>
			<?php if ( $bb_ser_footnote ) : ?>
				<br /><span class="bb-hub-if-necessary" aria-hidden="true">* If necessary.</span>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</section>
