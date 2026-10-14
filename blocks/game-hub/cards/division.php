<?php
/**
 * Game Hub: the regular-season division card. Included by ../render.php, which has set
 * $bb_hub_api.
 *
 * Last game and Full Standings across the top, the division table, the Guardians' wild card
 * line (not shown while they lead the division), and the next few games.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_div_table = $bb_hub_api->get_division_table();
$bb_div_table = is_wp_error( $bb_div_table ) ? array() : (array) $bb_div_table;
$bb_div_last  = (array) $bb_hub_api->get_last_game();
$bb_div_next  = (string) $bb_hub_api->get_next_games_line( 3 );

$bb_div_result_class = array(
	'W' => 'is-win',
	'L' => 'is-loss',
	'T' => '',
);
?>

<section class="bb-hub-card bb-hub-division">
	<div class="bb-hub-division-top">
		<?php if ( ! empty( $bb_div_last ) ) : ?>
			<p class="bb-hub-last">
				<span class="bb-hub-eyebrow">Last game</span>
				<span class="bb-hub-last-result <?php echo esc_attr( $bb_div_result_class[ $bb_div_last['result'] ] ?? '' ); ?>"><?php echo esc_html( $bb_div_last['result'] . ' ' . $bb_div_last['score'] ); ?></span>
				<span class="bb-hub-last-opponent"><?php echo esc_html( $bb_div_last['where'] . ' ' . $bb_div_last['opponent'] ); ?></span>
			</p>
		<?php endif; ?>
		<a class="bb-hub-link" href="https://www.mlb.com/standings/" target="_blank" rel="noopener noreferrer">Full Standings <span aria-hidden="true">→</span></a>
	</div>

	<?php if ( ! empty( $bb_div_table['rows'] ) ) : ?>
		<h3 class="bb-hub-eyebrow bb-hub-division-name"><?php echo esc_html( (string) $bb_div_table['name'] ); ?></h3>
		<table class="bb-hub-standings">
			<caption class="screen-reader-text"><?php echo esc_html( (string) $bb_div_table['name'] ); ?> standings</caption>
			<thead>
				<tr>
					<th scope="col" class="is-team">Team</th>
					<th scope="col">W</th>
					<th scope="col">L</th>
					<th scope="col">GB</th>
					<th scope="col"><abbr title="Last 10 games">L10</abbr></th>
					<th scope="col"><abbr title="Streak">STRK</abbr></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $bb_div_table['rows'] as $bb_div_row ) : ?>
					<?php
					$bb_div_streak_class = '';

					if ( 0 === strpos( (string) $bb_div_row['streak'], 'W' ) ) {
						$bb_div_streak_class = 'is-win';
					} elseif ( 0 === strpos( (string) $bb_div_row['streak'], 'L' ) ) {
						$bb_div_streak_class = 'is-loss';
					}
					?>
					<tr<?php echo $bb_div_row['is_us'] ? ' class="is-us"' : ''; ?>>
						<th scope="row" class="is-team">
							<span class="bb-hub-team">
								<?php if ( ! empty( $bb_div_row['logo_url'] ) ) : ?>
									<img class="bb-hub-team-logo" src="<?php echo esc_url( $bb_div_row['logo_url'] ); ?>" alt="" width="32" height="32" loading="lazy" />
								<?php else : ?>
									<span class="bb-hub-team-badge" aria-hidden="true"><?php echo esc_html( $bb_div_row['abbreviation'] ); ?></span>
								<?php endif; ?>
								<span class="bb-hub-team-name"><?php echo esc_html( $bb_div_row['name'] ); ?></span>
							</span>
						</th>
						<td><?php echo (int) $bb_div_row['wins']; ?></td>
						<td><?php echo (int) $bb_div_row['losses']; ?></td>
						<td><?php echo esc_html( $bb_div_row['games_back'] ); ?></td>
						<td><?php echo esc_html( $bb_div_row['last_ten'] ); ?></td>
						<td class="<?php echo esc_attr( $bb_div_streak_class ); ?>"><?php echo esc_html( '' !== $bb_div_row['streak'] ? $bb_div_row['streak'] : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( '' !== (string) $bb_div_table['wild_card'] ) : ?>
			<p class="bb-hub-wildcard"><?php echo esc_html( (string) $bb_div_table['wild_card'] ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="bb-hub-empty">Standings are unavailable right now.</p>
	<?php endif; ?>

	<?php if ( '' !== $bb_div_next ) : ?>
		<p class="bb-hub-next"><span class="bb-hub-next-label">Next</span> <?php echo esc_html( $bb_div_next ); ?></p>
	<?php endif; ?>
</section>
