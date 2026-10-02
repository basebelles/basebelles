<?php
/**
 * Per-category styling for the homepage posts list.
 *
 * Each category can have a color and a "tint photo" switch (ACF: group_bb_category_style).
 * This class:
 * - Picks one "display category" per post.
 * - Adds bb-cat-{slug} / bb-cat-tint to the post's classes.
 * - Prints one small <style> block mapping each colored category to --bb-cat-color.
 * - Adds a category label pill to featured images inside the .bb-home-posts Query Loop,
 *   plus a team label ("vs Blue Jays" / "@ Blue Jays") for game posts.
 *
 * All visual styling lives in basebelles.css, scoped to .bb-home-posts.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Category_Style {

	/** Wine red, used when a category has no color. Matches --bb-wine-red. */
	const FALLBACK_COLOR = '#84172c';

	/** Category slug for game posts, which also get a team label. */
	const GAME_CATEGORY = 'games';

	/** Class on the Query block that turns the pill on. */
	const QUERY_CLASS = 'bb-home-posts';

	/**
	 * How many .bb-home-posts Query blocks are currently rendering (more than one only if nested).
	 *
	 * @var int
	 */
	private static $home_posts_depth = 0;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );
		add_action( 'wp_head', array( $this, 'print_category_colors' ) );

		// Track when we're inside the .bb-home-posts Query Loop, so the pill only shows there.
		add_filter( 'render_block_data', array( $this, 'enter_query' ) );
		add_filter( 'render_block_core/query', array( $this, 'leave_query' ), 10, 2 );

		add_filter( 'render_block_core/post-featured-image', array( $this, 'render_featured_image' ), 10, 3 );
	}

	/**
	 * Resolve the one category a post is displayed with.
	 *
	 * 1. Uncategorized (the default category) is ignored if the post has any other category.
	 * 2. The first remaining category with a color wins.
	 * 3. Otherwise the first remaining category, with the fallback color.
	 *
	 * @param int $post_id Post ID.
	 * @return array{name: string, slug: string, color: string, tint: bool}|null Null if the post has no categories.
	 */
	public static function category_for_post( $post_id ) {
		$terms = get_the_category( (int) $post_id );

		if ( empty( $terms ) ) {
			return null;
		}

		$default_id = (int) get_option( 'default_category' );
		$candidates = array_values(
			array_filter(
				$terms,
				static function ( $term ) use ( $default_id ) {
					return (int) $term->term_id !== $default_id;
				}
			)
		);

		if ( empty( $candidates ) ) {
			$candidates = array_values( $terms );
		}

		foreach ( $candidates as $term ) {
			$color = self::get_color( $term );

			if ( $color ) {
				return self::describe( $term, $color );
			}
		}

		return self::describe( $candidates[0], self::FALLBACK_COLOR );
	}

	/**
	 * The opponent a game post is about, for the bottom label on the image.
	 *
	 * Uses the post's team term (the opponent; game posts only have one). The name is the
	 * team's short_name from team-info/list.json ("Blue Jays"), falling back to the term
	 * name. The venue-type term adds "vs" (home) or "@" (away).
	 *
	 * @param int $post_id Post ID.
	 * @return array{name: string, slug: string, venue: string, label: string}|null Null if the post has no team.
	 */
	public static function team_for_post( $post_id ) {
		$teams = get_the_terms( (int) $post_id, 'team' );

		if ( empty( $teams ) || is_wp_error( $teams ) ) {
			return null;
		}

		$term = reset( $teams );
		$name = (string) $term->name;

		if ( class_exists( 'Basebelles_API' ) ) {
			$info = Basebelles_API::get_instance()->get_team_by_taxonomy_slug( $term->slug );

			if ( ! empty( $info['short_name'] ) ) {
				$name = (string) $info['short_name'];
			}
		}

		$venue  = '';
		$venues = get_the_terms( (int) $post_id, 'venue-type' );

		if ( ! empty( $venues ) && ! is_wp_error( $venues ) ) {
			$venue_slugs = wp_list_pluck( $venues, 'slug' );

			if ( in_array( 'home', $venue_slugs, true ) ) {
				$venue = 'home';
			} elseif ( in_array( 'away', $venue_slugs, true ) ) {
				$venue = 'away';
			}
		}

		$prefixes = array(
			'home' => 'vs',
			'away' => '@',
		);

		return array(
			'name'  => $name,
			'slug'  => (string) $term->slug,
			'venue' => $venue,
			'label' => isset( $prefixes[ $venue ] ) ? $prefixes[ $venue ] . ' ' . $name : $name,
		);
	}

	/**
	 * A category's color, sanitized, or null if none is set.
	 *
	 * @param object $term Category term.
	 * @return string|null
	 */
	public static function get_color( $term ) {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		$color = sanitize_hex_color( (string) get_field( 'bb_category_color', 'category_' . (int) $term->term_id ) );

		return $color ? $color : null;
	}

	/**
	 * Whether a category has Tint Photo switched on.
	 *
	 * @param object $term Category term.
	 * @return bool
	 */
	public static function get_tint( $term ) {
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}

		return (bool) get_field( 'bb_category_tint', 'category_' . (int) $term->term_id );
	}

	/**
	 * The CSS class for a category slug.
	 *
	 * @param string $slug Category slug.
	 * @return string
	 */
	public static function class_for( $slug ) {
		return sanitize_html_class( 'bb-cat-' . $slug );
	}

	/**
	 * Add bb-cat-{slug} (and bb-cat-tint) to a post's classes.
	 *
	 * @param string[] $classes   Post classes.
	 * @param string[] $css_class Extra classes passed to get_post_class().
	 * @param int      $post_id   Post ID.
	 * @return string[]
	 */
	public function post_class( $classes, $css_class = array(), $post_id = 0 ) {
		$category = self::category_for_post( $post_id );

		if ( null === $category ) {
			return $classes;
		}

		$classes[] = self::class_for( $category['slug'] );

		if ( $category['tint'] ) {
			$classes[] = 'bb-cat-tint';
		}

		return $classes;
	}

	/**
	 * Print one rule per colored category: .bb-cat-{slug}{--bb-cat-color:#hex}
	 *
	 * Set on the post's <li>, so the image ring, pill and title all inherit it.
	 * Categories without a color get no rule and fall back to wine red in the CSS.
	 *
	 * @return void
	 */
	public function print_category_colors() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$rules = '';

		foreach ( $terms as $term ) {
			$color = self::get_color( $term );

			if ( $color ) {
				$rules .= '.' . self::class_for( $term->slug ) . '{--bb-cat-color:' . $color . '}';
			}
		}

		if ( '' !== $rules ) {
			// Class names go through sanitize_html_class() and colors through sanitize_hex_color().
			echo '<style id="bb-category-colors">' . $rules . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Note when a .bb-home-posts Query block starts rendering.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return array Unchanged.
	 */
	public function enter_query( $parsed_block ) {
		if ( self::is_home_posts_query( $parsed_block ) ) {
			++self::$home_posts_depth;
		}

		return $parsed_block;
	}

	/**
	 * Note when a .bb-home-posts Query block finishes rendering.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @return string Unchanged.
	 */
	public function leave_query( $block_content, $block ) {
		if ( self::is_home_posts_query( $block ) && self::$home_posts_depth > 0 ) {
			--self::$home_posts_depth;
		}

		return $block_content;
	}

	/**
	 * Add the category pill to a featured image inside the .bb-home-posts Query Loop.
	 *
	 * @param string $block_content Rendered block.
	 * @param array  $block         Parsed block.
	 * @param object $instance      WP_Block instance; context['postId'] is the post being rendered.
	 * @return string
	 */
	public function render_featured_image( $block_content, $block, $instance = null ) {
		if ( self::$home_posts_depth < 1 || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

		if ( ! $post_id ) {
			return $block_content;
		}

		$category = self::category_for_post( $post_id );
		$close    = strrpos( $block_content, '</figure>' );

		if ( null === $category || false === $close ) {
			return $block_content;
		}

		$pills = '<span class="bb-cat-pill bb-cat-pill--category">' . esc_html( $category['name'] ) . '</span>';

		if ( self::GAME_CATEGORY === $category['slug'] ) {
			$team = self::team_for_post( $post_id );

			if ( null !== $team ) {
				$pills .= '<span class="bb-cat-pill bb-cat-pill--team">' . esc_html( $team['label'] ) . '</span>';
			}
		}

		return substr_replace( $block_content, $pills, $close, 0 );
	}

	/**
	 * Whether a parsed block is a Query block with the bb-home-posts class.
	 *
	 * @param array $block Parsed block.
	 * @return bool
	 */
	private static function is_home_posts_query( $block ) {
		if ( ! is_array( $block ) || 'core/query' !== ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		$class_name = (string) ( $block['attrs']['className'] ?? '' );

		return in_array( self::QUERY_CLASS, preg_split( '/\s+/', $class_name ), true );
	}

	/**
	 * Build the display-category array.
	 *
	 * @param object $term  Category term.
	 * @param string $color Hex color.
	 * @return array{name: string, slug: string, color: string, tint: bool}
	 */
	private static function describe( $term, $color ) {
		return array(
			'name'  => (string) $term->name,
			'slug'  => (string) $term->slug,
			'color' => $color,
			'tint'  => self::get_tint( $term ),
		);
	}

	/**
	 * Reset the render-tracking state. Tests only.
	 *
	 * @return void
	 */
	public static function reset_state() {
		self::$home_posts_depth = 0;
	}
}

new Basebelles_Category_Style();
