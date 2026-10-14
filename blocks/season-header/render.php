<?php
/**
 * ACF Block Render Template for Season Header
 *
 * @var array $block The block settings and attributes.
 */

// If ACF is not active, exit.
if ( ! function_exists( 'get_field' ) ) {
	return;
}

$parse_season_date = array( 'Basebelles_Season', 'parse_date' );

// ACF Group: Season Info (group_69cd81d38a341)
$season_settings = get_field( 'season_settings', 'option' );
$season_settings = $season_settings ? $season_settings : [];
$season_details  = array(
	'spring_training' => [
		'start'  => get_field( 'spring_start', 'option' ) ?: 'TBD',
		'end'    => get_field( 'spring_end', 'option' ) ?: 'TBD',
		'record' => get_field( 'spring_record', 'option' ) ?? '',
	],
	'regular_season'  => [
		'start'  => get_field( 'reg_start', 'option' ) ?: 'TBD',
		'end'    => get_field( 'reg_end', 'option' ) ?: 'TBD',
		'record' => get_field( 'reg_record', 'option' ) ?? '',
	],
	'post_season'     => [
		'start'  => get_field( 'post_start', 'option' ) ?: 'TBD',
		'end'    => get_field( 'post_end', 'option' ) ?: 'TBD',
		'record' => get_field( 'post_record', 'option' ) ?? '',
	],
);

// Overall Settings
// Basebelles_Season works out the effective type (the setting, moved on by past dates), so the
// off-season picture switches at the same moment Today's Game and the standings hide.
$season_type  = Basebelles_Season::current_type();
$this_season  = (int) ( $season_settings['current_season'] ?? wp_date( 'Y' ) );
$off_season   = Basebelles_Season::off_season_image_url();
$last_ws_year = Basebelles_Season::last_world_series_year();

// Season Data
$season_data = array(
	'spring_training' => [
		'name'   => 'Spring Training',
		'type'   => 'springTraining',
		'start'  => empty( $season_details['spring_training']['start'] ) ? 'TBD' : $season_details['spring_training']['start'],
		'end'    => empty( $season_details['spring_training']['end'] ) ? 'TBD' : $season_details['spring_training']['end'],
		'class'  => ( 'springTraining' === $season_type ) ? 'active' : ( ( 'offSeason' === $season_type ) ? '' : 'over' ),
		'link'   => '/season-type/spring-training/?season_year=' . $this_season,
		'record' => empty( $season_details['spring_training']['record'] ) ? '' : $season_details['spring_training']['record'],
	],
	'regular_season'  => [
		'name'   => 'Regular Season',
		'type'   => 'regularSeason',
		'start'  => empty( $season_details['regular_season']['start'] ) ? 'TBD' : $season_details['regular_season']['start'],
		'end'    => empty( $season_details['regular_season']['end'] ) ? 'TBD' : $season_details['regular_season']['end'],
		'class'  => ( 'regularSeason' === $season_type ) ? 'active' : ( in_array( $season_type, array( 'postSeason', 'offSeason' ), true ) ? 'over' : '' ),
		'link'   => '/season-type/regular-season/?season_year=' . $this_season,
		'record' => empty( $season_details['regular_season']['record'] ) ? '' : $season_details['regular_season']['record'],
	],
	'post_season'     => [
		'name'   => 'Post Season',
		'type'   => 'postSeason',
		'start'  => empty( $season_details['post_season']['start'] ) ? 'TBD' : $season_details['post_season']['start'],
		'end'    => empty( $season_details['post_season']['end'] ) ? 'TBD' : $season_details['post_season']['end'],
		'class'  => ( 'postSeason' === $season_type ) ? 'active' : '',
		'link'   => '/season-type/post-season/?season_year=' . $this_season,
		'record' => empty( $season_details['post_season']['record'] ) ? '' : $season_details['post_season']['record'],
	],
);
?>
<div class="basebelles-season-header-grid">
	<div class="season-year-title">
		<?php echo esc_html( $this_season ); ?> Season
	</div>
	<?php if ( 'offSeason' === $season_type ) { ?>
			<div class="off-season-container">
				<img class="off-season-image" src="<?php echo esc_url( $off_season ); ?>" alt="Off Season - See you in the spring!" />
				<?php if ( '' !== $last_ws_year ) : ?>
					<p class="off-season-last-ws">The Guardians last won the World Series in <?php echo esc_html( $last_ws_year ); ?></p>
				<?php endif; ?>
			</div>
	<?php } else { ?>
		<?php foreach ( $season_data as $season_type => $season ) { ?>
			<div class="season-col <?php echo esc_attr( $season_type ); ?>">
				<h3><a href="<?php echo esc_attr( $season['link'] ); ?>" class="season-type-link"><?php echo esc_html( $season['name'] ); ?></a></h3>
				<span class="<?php echo esc_attr( $season['class'] ); ?>">
					<?php
					// Format the start and end dates to be MON DAY
					$start_date = $parse_season_date( $season['start'] );
					$end_date   = $parse_season_date( $season['end'] );
					$start      = $start_date ? $start_date->format( 'M j' ) : 'TBD';
					$end        = $end_date ? $end_date->format( 'M j' ) : 'TBD';
					if ( $start === $end ) {
						echo esc_html( $start );
					} else {
						echo esc_html( $start . ' - ' . $end );
					}
					?>
				</span>
				<?php if ( ! empty( $season['record'] ) ) { ?>
					<span class="season-record">
						<?php echo esc_html( $season['record'] ); ?>
					</span>
				<?php } ?>
			</div>
		<?php } ?>
	<?php } ?>
</div>
