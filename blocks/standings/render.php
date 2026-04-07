<?php
/**
 * ACF Block Render Template for Guardians Standings.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_mode = (string) ( $block['data']['display_mode'] ?? 'standard' );
$display_mode = in_array( $display_mode, array( 'standard', 'expanded' ), true ) ? $display_mode : 'standard';

if ( ! class_exists( 'Basebelles_API' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-standings placeholder"><p>Standings API integration is unavailable.</p></div>';
	}

	return;
}

// Get the season type and year from ACF Options or query variables. Default to regular season and current year.
$season_type = (string) ( get_field( 'season_type', 'option' ) ?? 'regularSeason' );
$season_year = (int) ( get_query_var( 'season_year' ) ?? get_field( 'season_year', 'option' ) ?? (int) gmdate( 'Y' ) );
if ( ! is_numeric( $season_year ) ) {
	$season_year = (int) gmdate( 'Y' );
}

// Get the standings from the API.
$api       = Basebelles_API::get_instance();
$standings = $api->get_guardians_standings( (string) $season_type, (int) $season_year );

if ( is_wp_error( $standings ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-standings placeholder">';
		echo '<h3>Guardians Standings</h3>';
		echo '<p>Live standings are unavailable right now. Don\'t panic, we\'re working on it.</p>';
		echo '</div>';
	}

	return;
}

$columns = array(
	array(
		'label' => 'Current Standing',
		'value' => $standings['summary'],
	),
	array(
		'label' => 'W',
		'value' => $standings['wins'],
	),
	array(
		'label' => 'L',
		'value' => $standings['losses'],
	),
	array(
		'label' => 'PCT',
		'value' => $standings['winning_percentage'],
	),
	array(
		'label' => 'GB',
		'value' => $standings['games_back'],
	),
	array(
		'label' => 'WCGB',
		'value' => $standings['wild_card_games_back'],
	),
);

if ( 'expanded' === $display_mode ) {
	$extra_columns = array(
		array(
			'label' => 'L10',
			'value' => $standings['last_ten'],
		),
		array(
			'label' => 'STK',
			'value' => $standings['streak'],
		),
		array(
			'label' => 'RS',
			'value' => $standings['runs_scored'],
		),
		array(
			'label' => 'RA',
			'value' => $standings['runs_allowed'],
		),
		array(
			'label' => 'DIFF',
			'value' => $standings['run_differential'],
		),
		array(
			'label' => 'Home',
			'value' => $standings['home'],
		),
		array(
			'label' => 'Away',
			'value' => $standings['away'],
		),
		array(
			'label' => '>.500',
			'value' => $standings['over_500'],
		),
	);
}
?>

<div class="basebelles-standings mode-<?php echo esc_attr( $display_mode ); ?>">
	<div class="standings-table-wrap">
		<table class="standings-table">
			<thead>
				<tr>
					<?php foreach ( $columns as $column ) : ?>
						<th scope="col"><?php echo esc_html( $column['label'] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<?php foreach ( $columns as $column ) : ?>
						<td><?php echo esc_html( (string) $column['value'] ); ?></td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
		<?php if ( 'expanded' === $display_mode ) : ?>
			<table class="standings-table">
				<thead>
					<tr>
						<?php foreach ( $extra_columns as $column ) : ?>
							<th scope="col"><?php echo esc_html( $column['label'] ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<?php foreach ( $extra_columns as $column ) : ?>
							<td><?php echo esc_html( (string) $column['value'] ); ?></td>
						<?php endforeach; ?>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
