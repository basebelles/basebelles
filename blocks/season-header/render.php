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

/**
 * Parse an ACF date picker value (return format "F j, Y") into a date in the site timezone.
 *
 * The value has no time or timezone, so strtotime() would read it as midnight UTC and
 * wp_date() would then shift it back a day for sites west of UTC.
 *
 * @param string $value Date string from ACF.
 * @return DateTimeImmutable|null Null when empty, 'TBD' or unparseable.
 */
$parse_season_date = static function ( $value ) {
	if ( empty( $value ) || 'TBD' === $value ) {
		return null;
	}
	$date = DateTimeImmutable::createFromFormat( '!F j, Y', (string) $value, wp_timezone() );
	if ( false === $date ) {
		try {
			$date = new DateTimeImmutable( (string) $value, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}
	return $date;
};

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
// The ACF choice for Post Season is stored as 'postseason' (the MLB API value).
$season_type = $season_settings['season_type'] ?? 'regularSeason';
$season_type = ( 'postseason' === $season_type ) ? 'postSeason' : $season_type;
$this_season = (int) ( $season_settings['current_season'] ?? wp_date( 'Y' ) );
$plugin_url  = plugin_dir_url( dirname( __DIR__, 2 ) . '/basebelles.php' );
$off_season  = $plugin_url . 'blocks/season-header/off-season.jpg';

// Season Data Double Check
// Dates are compared as Y-m-d strings; an unset (TBD) end date never counts as past.
$today   = wp_date( 'Y-m-d' );
$is_past = static function ( $value ) use ( $parse_season_date, $today ) {
	$date = $parse_season_date( $value );
	return $date && $date->format( 'Y-m-d' ) < $today;
};

// 1. If the current season YEAR is in the past, set the season type to offSeason
if ( $this_season < (int) wp_date( 'Y' ) ) {
	$season_type = 'offSeason';
}

// 2. If it's spring training, but the END date is in the PAST, set the season type to regularSeason
if ( 'springTraining' === $season_type && $is_past( $season_details['spring_training']['end'] ) ) {
	$season_type = 'regularSeason';
}

// 3. If it's regular season, but the END date is in the PAST, set the season type to postSeason
if ( 'regularSeason' === $season_type && $is_past( $season_details['regular_season']['end'] ) ) {
	$season_type = 'postSeason';
}

// 4. If it's post season, but the END date is in the PAST, set the season type to offSeason
if ( 'postSeason' === $season_type && $is_past( $season_details['post_season']['end'] ) ) {
	$season_type = 'offSeason';
}

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
		'class'  => ( 'regularSeason' === $season_type ) ? 'active' : ( in_array( $season_type, array( 'postSeason', 'wildCard', 'offSeason' ), true ) ? 'over' : '' ),
		'link'   => '/season-type/regular-season/?season_year=' . $this_season,
		'record' => empty( $season_details['regular_season']['record'] ) ? '' : $season_details['regular_season']['record'],
	],
	'post_season'     => [
		'name'   => 'Post Season',
		'type'   => 'postSeason',
		'start'  => empty( $season_details['post_season']['start'] ) ? 'TBD' : $season_details['post_season']['start'],
		'end'    => empty( $season_details['post_season']['end'] ) ? 'TBD' : $season_details['post_season']['end'],
		'class'  => ( 'postSeason' === $season_type || 'wildCard' === $season_type ) ? 'active' : '',
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
