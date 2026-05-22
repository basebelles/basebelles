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

// ACF Group: Season Info (group_69cd81d38a341)
$season_settings   = get_field( 'season_settings', 'option' );
$season_settings   = $season_settings ? $season_settings : array();
$default_start_end = array(
	'start'  => 'TBD',
	'end'    => 'TBD',
	'record' => '',
);
$season_details    = array(
	'spring_training' => get_field( 'spring_training', 'option' ) ?? $default_start_end,
	'regular_season'  => get_field( 'regular_season', 'option' ) ?? $default_start_end,
	'post_season'     => get_field( 'post_season', 'option' ) ?? $default_start_end,
);

// Overall Settings
$season_type = $season_settings['season_type'] ?? 'regularSeason';
$this_season = (int) ( $season_settings['current_season'] ?? wp_date( 'Y' ) );
$plugin_url  = plugin_dir_url( dirname( __DIR__, 2 ) . '/basebelles.php' );
$off_season  = $plugin_url . 'blocks/season-header/off-season.jpg';

// Season Data
$season_data = array(
	'spring_training' => array(
		'name'   => 'Spring Training',
		'type'   => 'springTraining',
		'start'  => empty( $season_details['spring_training']['start'] ) ? 'TBD' : $season_details['spring_training']['start'],
		'end'    => empty( $season_details['spring_training']['end'] ) ? 'TBD' : $season_details['spring_training']['end'],
		'class'  => ( 'springTraining' === $season_type ) ? 'active' : ( ( 'offSeason' === $season_type ) ? '' : 'over' ),
		'link'   => '/season-type/spring-training/?season_year=' . $this_season,
		'record' => empty( $season_details['spring_training']['record'] ) ? '' : $season_details['spring_training']['record'],
	),
	'regular_season'  => array(
		'name'   => 'Regular Season',
		'type'   => 'regularSeason',
		'start'  => empty( $season_details['regular_season']['start'] ) ? 'TBD' : $season_details['regular_season']['start'],
		'end'    => empty( $season_details['regular_season']['end'] ) ? 'TBD' : $season_details['regular_season']['end'],
		'class'  => ( 'regularSeason' === $season_type ) ? 'active' : ( in_array( $season_type, array( 'postSeason', 'wildCard', 'offSeason' ), true ) ? 'over' : '' ),
		'link'   => '/season-type/regular-season/?season_year=' . $this_season,
		'record' => empty( $season_details['regular_season']['record'] ) ? '' : $season_details['regular_season']['record'],
	),
	'post_season'     => array(
		'name'   => 'Post Season',
		'type'   => 'postSeason',
		'start'  => empty( $season_details['post_season']['start'] ) ? 'TBD' : $season_details['post_season']['start'],
		'end'    => empty( $season_details['post_season']['end'] ) ? 'TBD' : $season_details['post_season']['end'],
		'class'  => ( 'postSeason' === $season_type || 'wildCard' === $season_type ) ? 'active' : '',
		'link'   => '/season-type/post-season/?season_year=' . $this_season,
		'record' => empty( $season_details['post_season']['record'] ) ? '' : $season_details['post_season']['record'],
	),
);

// Season Data Double Check
// 1. If the current season YEAR is in the past, set the season type to offSeason
if ( $this_season < (int) wp_date( 'Y' ) ) {
	$season_type = 'offSeason';
}

// 2. If it's spring training, but the END date is in the PAST, set the season type to regularSeason
if ( 'springTraining' === $season_type && $season_data['spring_training']['end'] < wp_date( 'Y-m-d' ) ) {
	$season_type = 'regularSeason';
}

// 3. If it's regular season, but the END date is in the PAST, set the season type to postSeason
if ( 'regularSeason' === $season_type && $season_data['regular_season']['end'] < wp_date( 'Y-m-d' ) ) {
	$season_type = 'postSeason';
}

// 4. If it's post season, but the END date is in the PAST, set the season type to offSeason
if ( 'postSeason' === $season_type && $season_data['post_season']['end'] < wp_date( 'Y-m-d' ) ) {
	$season_type = 'offSeason';
}
?>
<div class="basebelles-season-header-grid">
	<div class="season-year-title">
		<?php echo esc_html( $this_season ); ?> Season
	</div>
	<?php if ( 'offSeason' === $season_type ) { ?>
			<div class="off-season-container">
				<img class="off-season-image" src="<?php echo esc_url( $off_season ); ?>" alt="Off Season - See you in the spring!" />
			</div>
	<?php } else { ?>
		<?php foreach ( $season_data as $season_type => $season ) { ?>
			<div class="season-col <?php echo esc_attr( $season_type ); ?>">
				<h3><a href="<?php echo esc_attr( $season['link'] ); ?>" class="season-type-link"><?php echo esc_html( $season['name'] ); ?></a></h3>
				<span class="<?php echo esc_attr( $season['class'] ); ?>">
					<?php
					// Format the start and end dates to be MON DAY
					$start = empty( $season['start'] ) || 'TBD' === $season['start'] ? 'TBD' : wp_date( 'M j', strtotime( $season['start'] ) );
					$end   = empty( $season['end'] ) || 'TBD' === $season['end'] ? 'TBD' : wp_date( 'M j', strtotime( $season['end'] ) );
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
