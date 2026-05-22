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

// ACF Group: Series Results (group_69c9b611cc123)
$home_game    = (bool) ( get_field( 'home_game' ) ?? true );
$guards_won   = (int) ( get_field( 'guards_won' ) ?? 0 );
$opponent     = get_field( 'opponent' );
$opponent_won = (int) ( get_field( 'oppo_won' ) ?? 0 );
$game_dates   = get_field( 'game_dates' ) ?? array();
$first_game   = $game_dates['first_game'] ?? '';
$last_game    = $game_dates['last_game'] ?? '';

// If data is missing and not in admin, just return.
if ( ! is_admin() && ! $opponent ) {
	return;
}

// Team Data from teams.json
$plugin_path = dirname( __DIR__, 2 );
$plugin_url  = plugin_dir_url( $plugin_path . '/basebelles.php' );
$teams_json  = file_exists( $plugin_path . '/team-info/list.json' ) ? file_get_contents( $plugin_path . '/team-info/list.json' ) : '{}';
$teams_data  = json_decode( $teams_json, true );

// Opponent identification
$opponent_slug = $opponent ?? 'tbd';

// If the opponent field contains a colon, split it to get the
// slug (e.g. "mariners: Seattle Mariners" -> "mariners")
if ( str_contains( $opponent_slug, ':' ) ) {
	$parts         = explode( ':', $opponent_slug );
	$opponent_slug = trim( $parts[0] );
}

$opponent_info = $teams_data[ $opponent_slug ] ?? array();

// Determine Home/Away objects
if ( $home_game ) {
	$home_team = array(
		'name'      => 'Guardians',
		'abbr'      => 'CLE',
		'logo'      => $plugin_url . 'team-info/logos/guardians.png',
		'games_won' => $guards_won,
	);
	$away_team = array(
		'name'      => $opponent_info['short_name'] ?? ucwords( str_replace( '-', ' ', $opponent_slug ) ),
		'abbr'      => $opponent_info['abbreviation'] ?? '',
		'logo'      => $plugin_url . 'team-info/logos/' . $opponent_slug . '.png',
		'games_won' => $opponent_won,
	);
} else {
	$away_team = array(
		'name'      => 'Guardians',
		'abbr'      => 'CLE',
		'logo'      => $plugin_url . 'team-info/logos/guardians.png',
		'games_won' => $guards_won,
	);
	$home_team = array(
		'name'      => $opponent_info['short_name'] ?? ucwords( str_replace( '-', ' ', $opponent_slug ) ),
		'abbr'      => $opponent_info['abbreviation'] ?? '',
		'logo'      => $plugin_url . 'team-info/logos/' . $opponent_slug . '.png',
		'games_won' => $opponent_won,
	);
}

// Calculate winner
$total_games = $guards_won + $opponent_won;

if ( $home_team['games_won'] > $away_team['games_won'] ) {
	$home_team['winner'] = true;
	$away_team['winner'] = false;
	$guards_win          = ( $home_game ) ? true : false;
} elseif ( $away_team['games_won'] > $home_team['games_won'] ) {
	$away_team['winner'] = true;
	$home_team['winner'] = false;
	$guards_win          = ( $home_game ) ? false : true;
} else {
	// series can be tied
	$away_team['winner'] = false;
	$home_team['winner'] = false;
	$guards_win          = 'split';
}

$winner_class = ( ! $guards_win ) ? 'oppo-win' : ( ( 'split' === $guards_win ) ? 'split-win' : 'guards-win' );

?>
<div class="basebelles-series-results">
	<?php
	if ( 'tbd' === $opponent ) {
		?>
		<div class="series-date placeholder-text">
			Series Results Coming Soon
		</div>
		<?php
	} else {
		?>
		<!-- Series Results Section -->
		<div class="series-date">
			<?php
			if ( $first_game !== $last_game ) {
				echo esc_html( $first_game ) . ' - ' . esc_html( $last_game );
			} else {
				echo esc_html( $first_game );
			}
			?>
		</div>
		<div class="series-results">
			<div class="series-summary away">
				<div class="team-name"><?php echo esc_html( strtoupper( $away_team['name'] ) ); ?></div>
				<div class="team-series-record"><?php echo esc_html( $away_team['games_won'] ); ?></div>
			</div>
			<div class="team-logo">
				<img src="<?php echo esc_url( $away_team['logo'] ); ?>" alt="<?php echo esc_attr( $away_team['name'] ); ?> Logo" />
			</div>

			<div class="series-spacer">
				<span class="series-winner <?php echo esc_attr( $winner_class ); ?>">
				<?php
				if ( $away_team['winner'] ) {
					echo esc_html( strtoupper( $away_team['abbr'] ) );
				} elseif ( $home_team['winner'] ) {
					echo esc_html( strtoupper( $home_team['abbr'] ) );
				} elseif ( ! $home_team['winner'] && ! $away_team['winner'] ) {
					echo 'SPLIT';
				}
				?>
				</span>
			</div>

			<div class="team-logo">
				<img src="<?php echo esc_url( $home_team['logo'] ); ?>" alt="<?php echo esc_attr( $home_team['name'] ); ?> Logo" />
			</div>
			<div class="series-summary home">
				<div class="team-name"><?php echo esc_html( strtoupper( $home_team['name'] ) ); ?></div>
				<div class="team-series-record"><?php echo esc_html( $home_team['games_won'] ); ?></div>
			</div>
		</div>
		<?php
	}
	?>
</div>
