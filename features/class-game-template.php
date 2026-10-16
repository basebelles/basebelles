<?php
/**
 * A "Game Post" template for posts in the games category.
 *
 * WordPress picks single-post templates by post type or post slug, never by category, so this
 * class does two things:
 * - Registers a `single-game` block template from the plugin, so it shows up under
 *   Appearance > Editor > Templates and can be edited there like any theme template. Until
 *   it's edited, it starts as a copy of the theme's Single Posts template.
 * - Puts `single-game` into the template hierarchy for posts in the games category, ahead of
 *   the generic single-post templates (but after a template made for one specific post).
 *
 * Needs WordPress 6.7+ (register_block_template()); on older versions game posts keep using
 * the normal single template.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Game_Template {

	/** Template slug, as in the hierarchy (single-game.php) and the Site Editor. */
	const SLUG = 'single-game';

	/** Category slug for game posts. Same as Basebelles_Category_Style::GAME_CATEGORY. */
	const GAME_CATEGORY = 'games';

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		// Loaded from Basebelles::init(), i.e. while init is running: register now if so.
		if ( did_action( 'init' ) ) {
			$this->register_template();
		} else {
			add_action( 'init', array( $this, 'register_template' ) );
		}

		add_filter( 'single_template_hierarchy', array( $this, 'filter_hierarchy' ) );
	}

	/**
	 * Register the Game Post template.
	 *
	 * @return void
	 */
	public function register_template() {
		register_block_template(
			'basebelles//' . self::SLUG,
			array(
				'title'       => 'Game Post',
				'description' => 'Posts in the Games category: game and series write-ups.',
				'post_types'  => array( 'post' ),
				'content'     => self::starting_content(),
			)
		);
	}

	/**
	 * What the template holds until it's edited in the Site Editor: the theme's Single Posts
	 * template, including any changes made to it there. Once the Game Post template has been
	 * saved, WordPress uses the saved copy and this is no longer read.
	 *
	 * @return string
	 */
	private static function starting_content() {
		$single = function_exists( 'get_block_template' ) ? get_block_template( get_stylesheet() . '//single' ) : null;

		if ( $single && ! empty( $single->content ) ) {
			return (string) $single->content;
		}

		return '<!-- wp:post-title /--><!-- wp:post-content /-->';
	}

	/**
	 * single_template_hierarchy filter: add the game template for game posts.
	 *
	 * @param string[] $templates Template file names, most specific first.
	 * @return string[]
	 */
	public function filter_hierarchy( $templates ) {
		if ( ! is_singular( 'post' ) ) {
			return $templates;
		}

		return self::hierarchy( (array) $templates, (int) get_queried_object_id() );
	}

	/**
	 * Insert single-game.php for a post in the games category: after single-post-{slug}.php
	 * (a template made for that one post still wins), before single-post.php and single.php.
	 *
	 * @param string[] $templates Template file names, most specific first.
	 * @param int      $post_id Post ID.
	 * @return string[]
	 */
	public static function hierarchy( array $templates, int $post_id ): array {
		if ( ! $post_id || ! self::is_game_post( $post_id ) ) {
			return $templates;
		}

		$file = self::SLUG . '.php';

		if ( in_array( $file, $templates, true ) ) {
			return $templates;
		}

		$position = array_search( 'single-post.php', $templates, true );

		if ( false === $position ) {
			$position = array_search( 'single.php', $templates, true );
		}

		if ( false === $position ) {
			$templates[] = $file;
			return $templates;
		}

		array_splice( $templates, (int) $position, 0, array( $file ) );

		return $templates;
	}

	/**
	 * Whether a post is in the games category.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_game_post( int $post_id ): bool {
		foreach ( (array) get_the_category( $post_id ) as $term ) {
			if ( is_object( $term ) && self::GAME_CATEGORY === $term->slug ) {
				return true;
			}
		}

		return false;
	}
}

new Basebelles_Game_Template();
