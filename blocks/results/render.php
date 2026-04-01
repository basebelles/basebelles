<?php
/**
 * ACF Block Render Template for Game Results
 *
 * @var array $block The block settings and attributes.
 */

// If ACF is not active, exit.
if ( ! function_exists( 'get_field' ) ) {
	return;
}

// ACF Group: Game Results (group_69c70525044fc)
$game_details     = get_field( 'game_details' );
$team_details     = get_field( 'team_details' );
$opponent_details = get_field( 'opponent_details' );

// Placeholder for the editor if data is missing.
if ( is_admin() && ( ! $game_details || ! $team_details || ! $opponent_details ) ) {
	echo '<div class="basebelles-results placeholder" style="padding: 20px; border: 2px dashed #ccc; text-align: center;">';
	echo '<h3>Cleveland Guardians Game Results</h3>';
	echo '<p>Please enter game details in the block settings. Ensure you have imported the Game Results ACF JSON.</p>';
	echo '</div>';
	return;
}

// If data is missing and not in admin, just return.
if ( ! $game_details || ! $team_details || ! $opponent_details ) {
	return;
}

$is_home = $game_details['home'] ?? true; // TRUE means HOME is Guardians

// Team Data from teams.json
$plugin_path = dirname( __DIR__, 2 );
$plugin_url  = plugin_dir_url( $plugin_path . '/basebelles.php' );
$teams_json  = file_exists( $plugin_path . '/team-info/list.json' ) ? file_get_contents( $plugin_path . '/team-info/list.json' ) : '{}';
$teams_data  = json_decode( $teams_json, true );

// Opponent identification
$opponent_raw  = $opponent_details['opponent'] ?? 'guardians: Cleveland Guardians';
$parts         = explode( ': ', $opponent_raw );
$opponent_slug = $parts[0] ?? 'guardians';
$opponent_info = $teams_data[ $opponent_slug ] ?? array();

// Extract just the short team name (e.g. "Mariners" from "mariners")
$opponent_short_name = $opponent_info['short_name'] ?? ucwords( str_replace( '-', ' ', $opponent_slug ) );
$guards_info         = $teams_data['guardians'] ?? array();

// Scores Logic
$inning_scores   = $game_details['inning_scores'] ?? array();
$num_innings     = is_array( $inning_scores ) ? count( $inning_scores ) : 0;
$display_innings = max( 9, $num_innings ); // Always show at least 9 columns

$away_total_runs = 0;
$home_total_runs = 0;

if ( ! empty( $inning_scores ) && is_array( $inning_scores ) ) {
	foreach ( $inning_scores as $score ) {
		// Parse numeric part from text (e.g., '1x' -> 1, 'X' -> 0)
		$away_val = $score['away'] ?? '0';
		$home_val = $score['home'] ?? '0';

		$away_total_runs += (int) preg_replace( '/[^0-9]/', '', $away_val );
		$home_total_runs += (int) preg_replace( '/[^0-9]/', '', $home_val );
	}
} elseif ( $is_home ) {
	$away_total_runs = (int) ( $game_details['runs']['opposition'] ?? 0 );
	$home_total_runs = (int) ( $game_details['runs']['guardians'] ?? 0 );
} else {
	$away_total_runs = (int) ( $game_details['runs']['guardians'] ?? 0 );
	$home_total_runs = (int) ( $game_details['runs']['opposition'] ?? 0 );
}

// Determine Home/Away objects
if ( $is_home ) {
	$home_team = array(
		'name'     => 'Guardians',
		'abbr'     => 'CLE',
		'logo'     => $plugin_url . 'team-info/logos/guardians.png',
		'score'    => $home_total_runs,
		'wl'       => $team_details['guards_wl'] ?? '',
		'standing' => ( $team_details['guards_standing'] ?? '' ) . ' ' . ( $guards_info['league'] ?? '' ),
		'hits'     => $game_details['hits']['guardians'] ?? 0,
		'errors'   => $game_details['errors']['guardians'] ?? 0,
	);
	$away_team = array(
		'name'     => $opponent_short_name,
		'abbr'     => $opponent_info['abbreviation'] ?? '',
		'logo'     => $plugin_url . 'team-info/logos/' . $opponent_slug . '.png',
		'score'    => $away_total_runs,
		'wl'       => $opponent_details['opponent_wl'] ?? '',
		'standing' => ( $opponent_details['opponent_standing'] ?? '' ) . ' ' . ( $opponent_info['league'] ?? '' ),
		'hits'     => $game_details['hits']['opposition'] ?? 0,
		'errors'   => $game_details['errors']['opposition'] ?? 0,
	);
} else {
	$away_team = array(
		'name'     => 'Guardians',
		'abbr'     => 'CLE',
		'logo'     => $plugin_url . 'team-info/logos/guardians.png',
		'score'    => $away_total_runs,
		'wl'       => $team_details['guards_wl'] ?? '',
		'standing' => ( $team_details['guards_standing'] ?? '' ) . ' ' . ( $guards_info['league'] ?? '' ),
		'hits'     => $game_details['hits']['guardians'] ?? 0,
		'errors'   => $game_details['errors']['guardians'] ?? 0,
	);
	$home_team = array(
		'name'     => $opponent_short_name,
		'abbr'     => $opponent_info['abbreviation'] ?? '',
		'logo'     => $plugin_url . 'team-info/logos/' . $opponent_slug . '.png',
		'score'    => $home_total_runs,
		'wl'       => $opponent_details['opponent_wl'] ?? '',
		'standing' => ( $opponent_details['opponent_standing'] ?? '' ) . ' ' . ( $opponent_info['league'] ?? '' ),
		'hits'     => $game_details['hits']['opposition'] ?? 0,
		'errors'   => $game_details['errors']['opposition'] ?? 0,
	);
}

?>

<div class="basebelles-results">
	<!-- Header Section -->
	<div class="results-header">
		<div class="team-summary away">
			<div class="team-name"><?php echo esc_html( strtoupper( $away_team['name'] ) ); ?></div>
			<div class="team-record"><?php echo esc_html( $away_team['wl'] ); ?></div>
		</div>
		<div class="team-logo">
			<img src="<?php echo esc_url( $away_team['logo'] ); ?>" alt="<?php echo esc_attr( $away_team['name'] ); ?> Logo" />
		</div>
		<div class="team-score"><?php echo esc_html( $away_team['score'] ); ?></div>

		<div class="score-spacer"><strong>FINAL</strong></div>

		<div class="team-score"><?php echo esc_html( $home_team['score'] ); ?></div>
		<div class="team-logo">
			<img src="<?php echo esc_url( $home_team['logo'] ); ?>" alt="<?php echo esc_attr( $home_team['name'] ); ?> Logo" />
		</div>
		<div class="team-summary home">
			<div class="team-name"><?php echo esc_html( strtoupper( $home_team['name'] ) ); ?></div>
			<div class="team-record"><?php echo esc_html( $home_team['wl'] ); ?></div>
		</div>
	</div>

	<!-- Innings Table Section -->
	<div class="results-table-container">
		<table class="results-innings-table">
			<thead>
				<tr>
					<th></th>
					<?php for ( $i = 1; $i <= $display_innings; $i++ ) : ?>
						<th><?php echo (int) $i; ?></th>
					<?php endfor; ?>
					<th>R</th>
					<th>H</th>
					<th>E</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class="team-label"><?php echo esc_html( strtoupper( $away_team['abbr'] ) ); ?></td>
					<?php for ( $i = 1; $i <= $display_innings; $i++ ) : ?>
						<td>
							<?php
							if ( isset( $inning_scores[ $i - 1 ] ) ) {
								$val = $inning_scores[ $i - 1 ]['away'] ?? '';
								echo ( '' === $val ) ? '0' : esc_html( $val );
							} else {
								echo '-';
							}
							?>
						</td>
					<?php endfor; ?>
					<td class="stat-cell"><?php echo esc_html( $away_team['score'] ); ?></td>
					<td class="stat-cell"><?php echo esc_html( $away_team['hits'] ); ?></td>
					<td class="stat-cell"><?php echo esc_html( $away_team['errors'] ); ?></td>
				</tr>
				<tr>
					<td class="team-label"><?php echo esc_html( strtoupper( $home_team['abbr'] ) ); ?></td>
					<?php for ( $i = 1; $i <= $display_innings; $i++ ) : ?>
						<td>
							<?php
							if ( isset( $inning_scores[ $i - 1 ] ) ) {
								$val = $inning_scores[ $i - 1 ]['home'] ?? '';
								echo ( '' === $val ) ? '0' : esc_html( $val );
							} else {
								echo '-';
							}
							?>
						</td>
					<?php endfor; ?>
					<td class="stat-cell"><?php echo esc_html( $home_team['score'] ); ?></td>
					<td class="stat-cell"><?php echo esc_html( $home_team['hits'] ); ?></td>
					<td class="stat-cell"><?php echo esc_html( $home_team['errors'] ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
