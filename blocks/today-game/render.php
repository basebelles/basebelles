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

$plugin_url    = plugin_dir_url( dirname( __DIR__, 2 ) . '/basebelles.php' );
$off_day_image = $plugin_url . 'blocks/today-game/off-day.jpg';
$game_date     = (string) ( call_user_func( 'get_field', 'game_date' ) ?? '' );
$api           = Basebelles_API::get_instance();
$schedule      = $api->get_guardians_today_game( $game_date );

if ( is_wp_error( $schedule ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-today-game placeholder">';
		echo '<h3>Guardians Today\'s Game</h3>';
		echo '<p>No game was found for today, or the live schedule could not be loaded.</p>';
		echo '</div>';
	}

	return;
}

?>

<div class="basebelles-today-game">
	<?php if ( ! empty( $schedule['off_day'] ) ) : ?>
		<div class="off-day-box">
			<img class="off-day-image" width="500px" src="<?php echo esc_url( $off_day_image ); ?>" alt="Off day" />
		</div>
	<?php else : ?>
		<?php foreach ( $schedule['games'] as $game ) : ?>
			<div class="game-card">
				<?php if ( ! empty( $game['show_label'] ) ) : ?>
					<div class="game-label">Game <?php echo esc_html( (string) $game['game_number'] ); ?></div>
				<?php endif; ?>

				<div class="today-game-header">
					<div class="away-team">
						<div class="team-logo">
							<?php if ( ! empty( $game['away_team']['logo_url'] ) ) : ?>
								<img src="<?php echo esc_url( $game['away_team']['logo_url'] ); ?>" alt="<?php echo esc_attr( $game['away_team']['name'] ); ?> logo" />
							<?php else : ?>
								<div class="placeholder-logo"><?php echo esc_html( $game['away_team']['abbreviation'] ); ?></div>
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
							<?php else : ?>
								<div class="placeholder-logo"><?php echo esc_html( $game['home_team']['abbreviation'] ); ?></div>
							<?php endif; ?>
						</div>
						<div class="team-record"><?php echo esc_html( $game['home_team']['record'] ); ?></div>
					</div>
				</div>

				<div class="game-broadcasts">
					<?php if ( ! empty( $game['broadcasts']['radio'] ) ) : ?>
						<div class="broadcast-radio">
							📻 <?php echo esc_html( implode( ', ', $game['broadcasts']['radio'] ) ); ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $game['broadcasts']['tv'] ) ) : ?>
						<div class="broadcast-tv">
							📺 <?php echo esc_html( implode( ', ', $game['broadcasts']['tv'] ) ); ?>
						</div>
					<?php endif; ?>
				</div>

				<?php
				// Always ask MLB for the live feed -- lineups and plays show up on MLB's own
				// schedule (often 60-90+ minutes out), not tied to the Score tab's 30-minute
				// countdown threshold below. The 60s cache in get_live_feed() bounds the real
				// request cost regardless of how early this runs.
				$phase       = Basebelles_Today_Game_Panels::get_phase( $game );
				$live_feed   = array();
				$feed_result = $api->get_live_feed( $game['game_pk'] );

				if ( ! is_wp_error( $feed_result ) ) {
					$live_feed = $feed_result;
				}

				echo Basebelles_Today_Game_Panels::render( $game, $phase, $live_feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		<?php endforeach; ?>

		<?php
		if ( ! wp_script_is( 'basebelles-today-game', 'enqueued' ) ) {
			wp_enqueue_script(
				'basebelles-today-game',
				$plugin_url . 'blocks/today-game/today-game.js',
				array(),
				Basebelles::$version,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
			wp_localize_script(
				'basebelles-today-game',
				'bbTodayGame',
				array(
					'restUrl' => esc_url_raw( rest_url( 'basebelles/v1/today-game/' ) ),
				)
			);
		}
		?>
	<?php endif; ?>
</div>
