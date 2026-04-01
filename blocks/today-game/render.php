<?php
/**
 * ACF Block Render Template for Guardians Today's Game.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_field' ) ) {
	return;
}

if ( ! class_exists( 'Basebelles_API' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-today-game placeholder"><p>Today\'s game data is unavailable.</p></div>';
	}

	return;
}

$game_date = (string) ( call_user_func( 'get_field', 'game_date' ) ?? '' );
$api       = Basebelles_API::get_instance();
$game      = $api->get_guardians_today_game( $game_date );

if ( is_wp_error( $game ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-today-game placeholder">';
		echo '<h3>Guardians Today\'s Game</h3>';
		echo '<p>No game was found for today, or the live schedule could not be loaded.</p>';
		echo '</div>';
	}

	return;
}

$away_pitcher = $game['away_pitcher'];
$home_pitcher = $game['home_pitcher'];

if ( empty( $away_pitcher ) ) {
	$away_pitcher = array(
		'name'   => 'TBD',
		'hand'   => '',
		'record' => '-',
		'era'    => '--',
		'url'    => '',
	);
}

if ( empty( $home_pitcher ) ) {
	$home_pitcher = array(
		'name'   => 'TBD',
		'hand'   => '',
		'record' => '-',
		'era'    => '--',
		'url'    => '',
	);
}
?>

<div class="basebelles-today-game">
	<div class="today-game-header">
		<div class="away-team">
			<div class="team-logo">
				<?php if ( ! empty( $game['away_team']['logo_url'] ) ) : ?>
					<img src="<?php echo esc_url( $game['away_team']['logo_url'] ); ?>" alt="<?php echo esc_attr( $game['away_team']['name'] ); ?> logo" />
				<?php endif; ?>
			</div>
			<div class="team-record"><?php echo esc_html( $game['away_team']['record'] ); ?></div>
		</div>
		<div class="game-meta">
			<?php echo esc_html( $game['day_date'] ); ?>
			<br /><div class="game-time"><?php echo esc_html( $game['game_time'] ); ?></div>
		</div>
		<div class="home-team">
			<div class="team-logo">
				<?php if ( ! empty( $game['home_team']['logo_url'] ) ) : ?>
					<img src="<?php echo esc_url( $game['home_team']['logo_url'] ); ?>" alt="<?php echo esc_attr( $game['home_team']['name'] ); ?> logo" />
				<?php endif; ?>
			</div>
			<div class="team-record"><?php echo esc_html( $game['home_team']['record'] ); ?></div>
		</div>
	</div>

	<?php if ( ! empty( $game['is_preview'] ) ) : ?>
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
					<span class="pitcher-era">ERA <?php echo esc_html( $away_pitcher['era'] ); ?></span>
				</div>
			</div>
			<div class="game-meta">
				v.s.
			</div>
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
					<span class="pitcher-era">ERA <?php echo esc_html( $home_pitcher['era'] ); ?></span>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
