<?php
/**
 * ACF Block Render Template for Season Header
 *
 * One bar for the whole season -- spring training, regular season, postseason -- each part as
 * wide as it is on the calendar, with a pin for today. Basebelles_Season_Timeline does the
 * working out; this gathers the inputs and draws it. The off season adds the snowy-field
 * picture under the bar.
 *
 * @var array $block The block settings and attributes.
 */

// If ACF is not active, exit.
if ( ! function_exists( 'get_field' ) ) {
	return;
}

// ACF Group: Season Info (group_69cd81d38a341)
$season_settings = get_field( 'season_settings', 'option' );
$season_settings = $season_settings ? $season_settings : array();

// Basebelles_Season works out the effective type (the setting, moved on by past dates), so the
// off-season picture switches at the same moment Today's Game and the standings hide.
$season_type  = Basebelles_Season::current_type();
$this_season  = (int) ( $season_settings['current_season'] ?? wp_date( 'Y' ) );
$off_season   = Basebelles_Season::off_season_image_url();
$last_ws_year = Basebelles_Season::last_world_series_year();

$season_phases = array();

foreach ( array(
	'spring_training' => array( 'spring', 'spring-training' ),
	'regular_season'  => array( 'reg', 'regular-season' ),
	'post_season'     => array( 'post', 'post-season' ),
) as $phase_key => $phase_fields ) {
	$season_phases[ $phase_key ] = array(
		'start'    => Basebelles_Season::parse_date( get_field( $phase_fields[0] . '_start', 'option' ) ),
		'end'      => Basebelles_Season::parse_date( get_field( $phase_fields[0] . '_end', 'option' ) ),
		'record'   => (string) ( get_field( $phase_fields[0] . '_record', 'option' ) ?? '' ),
		// Regular season and postseason only; spring training has no standings.
		'standing' => 'spring' === $phase_fields[0] ? '' : (string) ( get_field( $phase_fields[0] . '_standing', 'option' ) ?? '' ),
		'link'     => '/season-type/' . $phase_fields[1] . '/?season_year=' . $this_season,
	);
}

// Only the postseason segment reads the live status, so only ask for it once there's one.
$postseason_status = null;

if ( in_array( $season_type, array( 'postSeason', 'offSeason' ), true )
	&& class_exists( 'Basebelles_API' )
	&& method_exists( 'Basebelles_API', 'get_postseason_status' ) ) {
	$postseason_result = Basebelles_API::get_instance()->get_postseason_status( $this_season );
	$postseason_status = ( is_array( $postseason_result ) && ! is_wp_error( $postseason_result ) ) ? $postseason_result : null;
}

$timeline = Basebelles_Season_Timeline::build(
	$season_phases,
	$season_type,
	new DateTimeImmutable( 'today', wp_timezone() ),
	$postseason_status
);

// A plain-words version of the bar for screen readers, since the bar itself is hidden from them.
$timeline_summary = array();

foreach ( $timeline['segments'] as $segment ) {
	$timeline_summary[] = $segment['phrase'] . ' ' . array(
		'done'     => 'finished',
		'current'  => 'under way',
		'upcoming' => 'coming up',
	)[ $segment['state'] ];
}
?>
<div class="basebelles-season-header-grid bb-season-timeline">
	<div class="bb-tl-top">
		<div class="season-year-title"><?php echo esc_html( (string) $this_season ); ?> Season</div>
		<div class="bb-tl-now"><?php echo esc_html( $timeline['now'] ); ?></div>
	</div>

	<p class="screen-reader-text"><?php echo esc_html( implode( ', ', $timeline_summary ) . '.' ); ?></p>

	<div class="bb-tl-bar" aria-hidden="true">
		<?php foreach ( $timeline['segments'] as $segment ) : ?>
			<span class="bb-tl-seg is-<?php echo esc_attr( $segment['state'] ); ?>" style="--bb-tl-w: <?php echo esc_attr( (string) $segment['width'] ); ?>%">
				<span class="bb-tl-fill" style="width: <?php echo esc_attr( (string) $segment['fill'] ); ?>%"></span>
			</span>
		<?php endforeach; ?>
		<?php if ( null !== $timeline['pin'] ) : ?>
			<span class="bb-tl-pin<?php echo '' !== $timeline['pin_side'] ? ' is-' . esc_attr( $timeline['pin_side'] ) : ''; ?>" style="left: <?php echo esc_attr( (string) $timeline['pin'] ); ?>%">
				<span class="bb-tl-pin-label">Today</span>
			</span>
		<?php endif; ?>
	</div>

	<ol class="bb-tl-labels">
		<?php foreach ( $timeline['segments'] as $segment ) : ?>
			<li class="bb-tl-label is-<?php echo esc_attr( $segment['state'] ); ?>" style="--bb-tl-w: <?php echo esc_attr( (string) $segment['width'] ); ?>%">
				<a class="bb-tl-name" href="<?php echo esc_attr( $segment['link'] ); ?>"><?php echo esc_html( $segment['short'] ); ?></a>
				<span class="bb-tl-result"><?php echo esc_html( $segment['result'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( ! empty( $timeline['months'] ) ) : ?>
		<div class="bb-tl-months" aria-hidden="true">
			<?php foreach ( $timeline['months'] as $month_tick ) : ?>
				<span style="left: <?php echo esc_attr( (string) $month_tick[1] ); ?>%"><?php echo esc_html( $month_tick[0] ); ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'offSeason' === $season_type ) : ?>
		<div class="off-season-container">
			<img class="off-season-image" src="<?php echo esc_url( $off_season ); ?>" alt="Off Season - See you in the spring!" />
			<?php if ( '' !== $last_ws_year ) : ?>
				<p class="off-season-last-ws">The Guardians last won the World Series in <?php echo esc_html( $last_ws_year ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
