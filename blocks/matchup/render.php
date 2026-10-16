<?php
/**
 * ACF Block Render Template for Game Matchup.
 *
 * Meant for the game post template in the Site Editor: shows the post's opponent (its `team`
 * term) as a card in that team's colours, with both logos, the season type and home/away as
 * pills, and a link to every post about that team. Colours and logos come from
 * team-info/list.json, matched on its `taxonomy_term`. A team with no entry there gets the
 * site's wine red and no logo.
 *
 * @var array      $block      The block settings and attributes.
 * @var bool       $is_preview Whether the block is rendering inside the editor.
 * @var int|string $post_id    The post being rendered, from ACF.
 * @var array      $context    Block context (postId inside a template or Query Loop).
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bb_in_editor = ( isset( $is_preview ) && $is_preview ) || is_admin();

// Inside a template the post comes from block context; ACF's $post_id covers the post editor.
$bb_post_id = 0;
if ( isset( $context['postId'] ) ) {
	$bb_post_id = (int) $context['postId'];
} elseif ( isset( $post_id ) && is_numeric( $post_id ) ) {
	$bb_post_id = (int) $post_id;
}

$bb_placeholder = static function () use ( $bb_in_editor ) {
	if ( $bb_in_editor ) {
		echo '<div class="basebelles-matchup basebelles-matchup--placeholder"><p>Game Matchup shows the opponent from the post&#8217;s <strong>Team</strong>, in their colours.</p></div>';
	}
};

if ( ! $bb_post_id || ! class_exists( 'Basebelles_API' ) || ! class_exists( 'Basebelles_Team_Colors' ) ) {
	$bb_placeholder();
	return;
}

// The Guardians are always one side, so a Guardians team term isn't an opponent.
$bb_guardians_term = 'cleveland-guardians';

/**
 * A post's terms in a taxonomy; get_the_terms() can also hand back false or a WP_Error.
 *
 * @param string $taxonomy Taxonomy name.
 * @return object[]
 */
$bb_terms_for = static function ( $taxonomy ) use ( $bb_post_id ) {
	$terms = get_the_terms( $bb_post_id, $taxonomy );

	return is_array( $terms ) ? $terms : array();
};

$bb_opponents = array();
foreach ( $bb_terms_for( 'team' ) as $bb_term ) {
	if ( is_object( $bb_term ) && $bb_guardians_term !== $bb_term->slug ) {
		$bb_opponents[] = $bb_term;
	}
}

if ( ! $bb_opponents ) {
	$bb_placeholder();
	return;
}

$bb_api           = Basebelles_API::get_instance();
$bb_guardians_img = $bb_api->get_team_logo_url( 'guardians' );

/**
 * Pills for a taxonomy: name and archive link per term.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array<int, array{0: string, 1: string}>
 */
$bb_pills_for = static function ( $taxonomy ) use ( $bb_terms_for ) {
	$pills = array();

	foreach ( $bb_terms_for( $taxonomy ) as $term ) {
		if ( ! is_object( $term ) ) {
			continue;
		}

		$link    = get_term_link( $term );
		$pills[] = array( (string) $term->name, is_wp_error( $link ) ? '' : (string) $link );
	}

	return $pills;
};

// Season type first ("Post-season"), then where it was played ("Away", "Home").
$bb_pills = array_merge( $bb_pills_for( 'season-type' ), $bb_pills_for( 'venue-type' ) );

foreach ( $bb_opponents as $bb_term ) {
	$bb_team    = $bb_api->get_team_by_taxonomy_slug( (string) $bb_term->slug );
	$bb_name    = (string) ( $bb_team['name'] ?? $bb_term->name );
	$bb_short   = (string) ( $bb_team['short_name'] ?? $bb_term->name );
	$bb_logo    = $bb_team ? $bb_api->get_team_logo_url( (string) ( $bb_team['slug'] ?? '' ) ) : '';
	$bb_colors  = $bb_team ? Basebelles_Team_Colors::for_team( $bb_team ) : array();
	$bb_style   = Basebelles_Team_Colors::style( $bb_colors );
	$bb_link    = get_term_link( $bb_term );
	$bb_link    = is_wp_error( $bb_link ) ? '' : (string) $bb_link;
	$bb_classes = array( 'basebelles-matchup' );

	if ( '' !== $bb_style ) {
		$bb_classes[] = 'has-team-colors';
	}
	if ( ! empty( $bb_colors['light'] ) ) {
		$bb_classes[] = 'is-light';
	}
	if ( ! empty( $block['align'] ) ) {
		$bb_classes[] = 'align' . sanitize_html_class( (string) $block['align'] );
	}
	?>
	<section class="<?php echo esc_attr( implode( ' ', $bb_classes ) ); ?>"<?php echo '' !== $bb_style ? ' style="' . esc_attr( $bb_style ) . '"' : ''; ?><?php echo ! empty( $block['anchor'] ) ? ' id="' . esc_attr( (string) $block['anchor'] ) . '"' : ''; ?> aria-label="The matchup">
		<div class="matchup-inner">
			<?php if ( '' !== $bb_guardians_img || '' !== $bb_logo ) : ?>
				<div class="matchup-logos" aria-hidden="true">
					<?php if ( '' !== $bb_guardians_img ) : ?>
						<span class="matchup-logo"><img src="<?php echo esc_url( $bb_guardians_img ); ?>" alt="" width="44" height="44" loading="lazy" /></span>
					<?php endif; ?>
					<?php if ( '' !== $bb_logo ) : ?>
						<span class="matchup-vs">vs</span>
						<span class="matchup-logo"><img src="<?php echo esc_url( $bb_logo ); ?>" alt="" width="44" height="44" loading="lazy" /></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="matchup-body">
				<p class="matchup-kicker">The matchup</p>
				<h2 class="matchup-title">Guardians vs <?php echo esc_html( $bb_name ); ?></h2>
				<?php if ( $bb_pills ) : ?>
					<ul class="matchup-pills">
						<?php foreach ( $bb_pills as $bb_pill ) : ?>
							<li>
								<?php if ( '' !== $bb_pill[1] ) : ?>
									<a class="matchup-pill" href="<?php echo esc_url( $bb_pill[1] ); ?>"><?php echo esc_html( $bb_pill[0] ); ?></a>
								<?php else : ?>
									<span class="matchup-pill"><?php echo esc_html( $bb_pill[0] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $bb_link ) : ?>
				<a class="matchup-link" href="<?php echo esc_url( $bb_link ); ?>">All <?php echo esc_html( $bb_short ); ?> games <span aria-hidden="true">&rarr;</span></a>
			<?php endif; ?>
		</div>
	</section>
	<?php
}
