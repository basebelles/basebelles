<?php
/**
 * ACF Block Render Template for Series Results
 *
 * @var array $block The block settings and attributes.
 */

// If ACF is not active, exit.
if ( ! function_exists( 'get_field' ) ) {
	return;
}

// ACF Group: Series Results (group_69c70525044fc)
$home_game    = (bool) get_field( 'home_game' ) ?? true;
$guards_won   = (int) get_field( 'guards_won' ) ?? 0;
$opponent     = get_field( 'opponent' );
$opponent_won = (int) get_field( 'oppo_won' ) ?? 0;

// If data is missing and not in admin, just return.
if ( ! is_admin() && ! $opponent ) {
	return;
}

// Team Data from teams.json
$plugin_path = dirname( __DIR__, 2 );
$plugin_url  = plugin_dir_url( $plugin_path . '/basebelles.php' );
$teams_json  = file_exists( $plugin_path . '/teams.json' ) ? file_get_contents( $plugin_path . '/teams.json' ) : '{}';
$teams_data  = json_decode( $teams_json, true );

// Opponent identification
$opponent_raw  = $opponent_details['opponent'] ?? '';
$parts         = explode( ': ', $opponent_raw );
$opponent_slug = $parts[0] ?? '';
$opponent_info = $teams_data[ $opponent_slug ] ?? array();

// Determine Home/Away objects
if ( $is_home ) {
	$home_team = array(
		'name'      => 'Guardians',
		'abbr'      => 'CLE',
		'logo'      => $plugin_url . 'team-icons/guardians.png',
		'games_won' => $guards_won,
	);
	$away_team = array(
		'name'      => $opponent_short_name,
		'abbr'      => $opponent_info['abbreviation'] ?? '',
		'logo'      => $plugin_url . 'team-icons/' . $opponent_slug . '.png',
		'games_won' => $opponent_won,
	);
} else {
	$away_team = array(
		'name'      => 'Guardians',
		'abbr'      => 'CLE',
		'logo'      => $plugin_url . 'team-icons/guardians.png',
		'games_won' => $guards_won,
	);
	$home_team = array(
		'name'      => $opponent_short_name,
		'abbr'      => $opponent_info['abbreviation'] ?? '',
		'logo'      => $plugin_url . 'team-icons/' . $opponent_slug . '.png',
		'games_won' => $opponent_won,
	);
}

// Calculate winner
$total_games = $guards_won + $opponent_won;

if ( $home_team['games_won'] > $away_team['games_won'] ) {
    $home_team['winner'] = true;
    $away_team['winner'] = false;
} elseif ( $away_team['games_won'] > $home_team['games_won'] ) {
    $away_team['winner'] = true;
    $home_team['winner'] = false;
} else {
    // Somehow we tied? should never happen!
}

?>
<div class="basebelles-results">
	<!-- Header Section -->
	<div class="results-header">
		<div class="team-series-summary away">
			<div class="team-name"><?php echo esc_html( strtoupper( $away_team['name'] ) ); ?></div>
			<div class="team-series-record"><?php echo esc_html( $away_team['games_won'] ); ?></div>
		</div>
		<div class="team-logo">
			<img src="<?php echo esc_url( $away_team['logo'] ); ?>" alt="<?php echo esc_attr( $away_team['name'] ); ?> Logo" />
		</div>
		<div class="team-won-series"><?php 
            if ( $away_team['winner] ) {
            	// some icon for winner...
            }
        ?></div>

		<div class="score-spacer">&nbsp;</div>

		<div class="team-won-series"><?php 
            if ( $home_team['winner] ) {
            	// some icon for winner...
            }
        ?></div>
		<div class="team-logo">
			<img src="<?php echo esc_url( $home_team['logo'] ); ?>" alt="<?php echo esc_attr( $home_team['name'] ); ?> Logo" />
		</div>
		<div class="team-series-summary home">
			<div class="team-name"><?php echo esc_html( strtoupper( $home_team['name'] ) ); ?></div>
			<div class="team-series-record"><?php echo esc_html( $home_team['games_won'] ); ?></div>
		</div>
	</div>
</div>
