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
$season_settings = get_field( 'season_settings' );

// Overall Settings
$season_type = $season_settings['season_type'] ?? 'regularSeason';
$this_season = (int) $season_settings['current_season'] ?? wp_date( 'Y' );
$plugin_url   = plugin_dir_url( dirname( __DIR__, 2 ) . '/basebelles.php' );
$off_season  = $plugin_url . 'blocks/season-header/off-season.jpg';

// Season Data
$spring_training = array(
  'start' => $season_settings['spring-training']['start'] ?? 'TBD',
  'end'   => $season_settings['spring-training']['end'] ?? 'TBD',
  'class' => ( 'springTraining' === $season_type ) ? 'active' : ( ( 'offSeason' === $season_type ) ? '' : 'over' ),
  'link'  => '/season-type/spring-training/?year=' . $this_season,
);

$regular_season = array(
  'start' => $season_settings['regular-season']['start'] ?? 'TBD',
  'end'   => $season_settings['regular-season']['end'] ?? 'TBD',
  'class' => ( 'regularSeason' === $season_type ) ? 'active' : ( in_array($season_type, ['postSeason', 'wildCard', 'offSeason']) ? 'over' : '' ),
  'link'  => '/season-type/regular-season/?year=' . $this_season,
);


$postseason = array(
  'start' => $season_settings['post-season']['start'] ?? 'TBD',
  'end'   => $season_settings['post-season']['end'] ?? 'TBD',
  'class' => ( 'postSeason' === $season_type || 'wildCard' === $season_type ) ? 'active' : '',
  'link'  => '/season-type/post-season/?year=' . $this_season,
);

?>
<div class="basebelles-season-header">
  <div class="season-year">
  <?php if ( 'offSeason' === $season_type ) { ?>
      <div class="off-season">
        <img src=class="off-season-image" src="<?php echo esc_url( $off_season ); ?>" alt="Off Season - See you in the spring!" />
      </div>
  <?php } else { ?>
	<div class="spring-training">
		<h3><a href="<?php echo esc_attr( $spring_training['link'] ); ?>">Spring Training</a></h3>
    <span class="<?php echo esc_attr( $spring_training['class'] ); ?>">
    <?php echo esc_html( $spring_training['start'] . ' - ' . $spring_training['start'] ); ?>
    </span>
	</div>
  <div class="regular-season">
		<h3><a href="<?php echo esc_attr( $regular_season['link'] ); ?>">Regular Season</a></h3>
    <span class="<?php echo esc_attr( $regular_season['class'] ); ?>">
    <?php echo esc_html( $regular_season['start'] . ' - ' . $regular_season['end'] ); ?>
    </span>
	</div>
  <div class="post-season">
		<h3><a href="<?php echo esc_attr( $postseason['link'] ); ?>">Post Season</a></h3>
    <span class="<?php echo esc_attr( $postseason['class'] ); ?>">
    <?php echo esc_html( $postseason['start'] . ' - ' . $postseason['end'] ); ?>
    </span>
	</div>
  <?php } ?>
</div>
