<?php
/**
 * Tests for Basebelles_Category_Style.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

class CategoryStyleTest extends TestCase {

	private const UNCATEGORIZED = 1;
	private const GAMES         = 5;
	private const OFF_DAY       = 6;
	private const NEWS          = 7;

	private const POST_ID = 42;

	private const FIGURE = '<figure class="wp-block-post-featured-image"><a href="https://example.test/post/"><img src="a.jpg" alt="" /></a></figure>';

	/** @var Basebelles_Category_Style */
	private $style;

	protected function setUp(): void {
		Basebelles_Test_State::reset();
		Basebelles_Category_Style::reset_state();

		Basebelles_Test_State::$options['default_category'] = self::UNCATEGORIZED;

		$this->style = new Basebelles_Category_Style();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Helpers
	 * -----------------------------------------------------------------------
	 */

	private static function term( int $id, string $name, string $slug ): object {
		return (object) array(
			'term_id' => $id,
			'name'    => $name,
			'slug'    => $slug,
		);
	}

	private static function uncategorized(): object {
		return self::term( self::UNCATEGORIZED, 'Uncategorized', 'uncategorized' );
	}

	private static function games(): object {
		return self::term( self::GAMES, 'Games & Series', 'games' );
	}

	private static function off_day(): object {
		return self::term( self::OFF_DAY, 'Off Day', 'off-day' );
	}

	private static function news(): object {
		return self::term( self::NEWS, 'News', 'news' );
	}

	private static function set_style( int $term_id, $color, bool $tint = false ): void {
		Basebelles_Test_State::$object_fields[ 'category_' . $term_id ] = array(
			'bb_category_color' => $color,
			'bb_category_tint'  => $tint,
		);
	}

	private static function set_categories( array $terms ): void {
		Basebelles_Test_State::$categories[ self::POST_ID ] = $terms;
	}

	/**
	 * Give the post a team term (and optionally a venue), with the Blue Jays in the team list.
	 */
	private static function set_team( string $slug, string $name, string $venue = '' ): void {
		Basebelles_Test_State::$team_info = array(
			'blue-jays' => array(
				'name'          => 'Toronto Blue Jays',
				'taxonomy_term' => 'toronto-blue-jays',
				'short_name'    => 'Blue Jays',
			),
		);

		Basebelles_Test_State::$post_terms[ self::POST_ID ]['team'] = array( self::term( 20, $name, $slug ) );

		if ( '' !== $venue ) {
			Basebelles_Test_State::$post_terms[ self::POST_ID ]['venue-type'] = array( self::term( 30, ucfirst( $venue ), $venue ) );
		}
	}

	private static function instance( int $post_id ): object {
		return (object) array( 'context' => array( 'postId' => $post_id ) );
	}

	private static function home_query( string $class_name = 'bb-home-posts' ): array {
		return array(
			'blockName' => 'core/query',
			'attrs'     => array( 'className' => $class_name ),
		);
	}

	private function render_inside_home_posts( string $content, int $post_id ): string {
		$query = self::home_query();

		$this->style->enter_query( $query );
		$html = $this->style->render_featured_image( $content, array( 'blockName' => 'core/post-featured-image' ), self::instance( $post_id ) );
		$this->style->leave_query( '', $query );

		return $html;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Display-category resolution
	 * -----------------------------------------------------------------------
	 */

	public function test_colored_category_wins_over_an_uncolored_one(): void {
		self::set_style( self::OFF_DAY, '#00385d', true );
		self::set_categories( array( self::news(), self::off_day() ) );

		$this->assertSame(
			array(
				'name'  => 'Off Day',
				'slug'  => 'off-day',
				'color' => '#00385d',
				'tint'  => true,
			),
			Basebelles_Category_Style::category_for_post( self::POST_ID )
		);
	}

	public function test_first_colored_category_wins_when_several_have_colors(): void {
		self::set_style( self::GAMES, '#84172c' );
		self::set_style( self::OFF_DAY, '#00385d' );
		self::set_categories( array( self::games(), self::off_day() ) );

		$this->assertSame( 'games', Basebelles_Category_Style::category_for_post( self::POST_ID )['slug'] );
	}

	public function test_falls_back_to_first_category_and_wine_red_when_none_have_colors(): void {
		self::set_categories( array( self::news(), self::games() ) );

		$category = Basebelles_Category_Style::category_for_post( self::POST_ID );

		$this->assertSame( 'news', $category['slug'] );
		$this->assertSame( Basebelles_Category_Style::FALLBACK_COLOR, $category['color'] );
		$this->assertFalse( $category['tint'] );
	}

	public function test_uncategorized_is_skipped_when_the_post_has_another_category(): void {
		self::set_style( self::UNCATEGORIZED, '#000000' );
		self::set_categories( array( self::uncategorized(), self::news() ) );

		$category = Basebelles_Category_Style::category_for_post( self::POST_ID );

		$this->assertSame( 'news', $category['slug'] );
		$this->assertSame( Basebelles_Category_Style::FALLBACK_COLOR, $category['color'] );
	}

	public function test_uncategorized_is_used_when_it_is_the_only_category(): void {
		self::set_categories( array( self::uncategorized() ) );

		$this->assertSame( 'uncategorized', Basebelles_Category_Style::category_for_post( self::POST_ID )['slug'] );
	}

	public function test_post_without_categories_has_no_display_category(): void {
		$this->assertNull( Basebelles_Category_Style::category_for_post( self::POST_ID ) );
	}

	public function test_invalid_color_is_treated_as_unset(): void {
		self::set_style( self::GAMES, 'red;}body{display:none' );
		self::set_categories( array( self::games() ) );

		$this->assertSame( Basebelles_Category_Style::FALLBACK_COLOR, Basebelles_Category_Style::category_for_post( self::POST_ID )['color'] );
	}

	/*
	 * -----------------------------------------------------------------------
	 * post_class
	 * -----------------------------------------------------------------------
	 */

	public function test_post_class_adds_the_category_class(): void {
		self::set_style( self::GAMES, '#84172c' );
		self::set_categories( array( self::games() ) );

		$classes = $this->style->post_class( array( 'post' ), array(), self::POST_ID );

		$this->assertContains( 'bb-cat-games', $classes );
		$this->assertNotContains( 'bb-cat-tint', $classes );
	}

	public function test_post_class_adds_tint_when_the_category_has_it_on(): void {
		self::set_style( self::OFF_DAY, '#00385d', true );
		self::set_categories( array( self::off_day() ) );

		$classes = $this->style->post_class( array( 'post' ), array(), self::POST_ID );

		$this->assertContains( 'bb-cat-off-day', $classes );
		$this->assertContains( 'bb-cat-tint', $classes );
	}

	public function test_post_class_is_unchanged_without_categories(): void {
		$this->assertSame( array( 'post' ), $this->style->post_class( array( 'post' ), array(), self::POST_ID ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Category color rules (wp_head)
	 * -----------------------------------------------------------------------
	 */

	public function test_color_rules_cover_only_colored_categories(): void {
		self::set_style( self::GAMES, '#84172c' );
		self::set_style( self::OFF_DAY, '#00385d', true );
		Basebelles_Test_State::$terms['category'] = array( self::games(), self::off_day(), self::news() );

		ob_start();
		$this->style->print_category_colors();
		$html = ob_get_clean();

		$this->assertStringContainsString( '.bb-cat-games{--bb-cat-color:#84172c}', $html );
		$this->assertStringContainsString( '.bb-cat-off-day{--bb-cat-color:#00385d}', $html );
		$this->assertStringNotContainsString( 'bb-cat-news', $html );
	}

	public function test_color_rules_drop_unsafe_colors(): void {
		self::set_style( self::GAMES, '#84172c}</style><script>alert(1)</script>' );
		Basebelles_Test_State::$terms['category'] = array( self::games() );

		ob_start();
		$this->style->print_category_colors();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Featured-image pill
	 * -----------------------------------------------------------------------
	 */

	public function test_pill_is_added_inside_the_figure_and_escaped(): void {
		self::set_style( self::GAMES, '#84172c' );
		self::set_categories( array( self::games() ) );

		$html = $this->render_inside_home_posts( self::FIGURE, self::POST_ID );

		$this->assertStringEndsWith( '<span class="bb-cat-pill bb-cat-pill--category">Games &amp; Series</span></figure>', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Team label
	 * -----------------------------------------------------------------------
	 */

	public function test_team_uses_short_name_and_vs_for_home_games(): void {
		self::set_team( 'toronto-blue-jays', 'Toronto Blue Jays', 'home' );

		$this->assertSame(
			array(
				'name'  => 'Blue Jays',
				'slug'  => 'toronto-blue-jays',
				'venue' => 'home',
				'label' => 'vs Blue Jays',
			),
			Basebelles_Category_Style::team_for_post( self::POST_ID )
		);
	}

	public function test_team_uses_at_sign_for_away_games(): void {
		self::set_team( 'toronto-blue-jays', 'Toronto Blue Jays', 'away' );

		$this->assertSame( '@ Blue Jays', Basebelles_Category_Style::team_for_post( self::POST_ID )['label'] );
	}

	public function test_team_without_a_venue_is_just_the_name(): void {
		self::set_team( 'toronto-blue-jays', 'Toronto Blue Jays' );

		$this->assertSame( 'Blue Jays', Basebelles_Category_Style::team_for_post( self::POST_ID )['label'] );
	}

	public function test_team_falls_back_to_the_term_name_when_not_in_the_team_list(): void {
		self::set_team( 'cleveland-north', 'Cleveland North', 'home' );

		$this->assertSame( 'vs Cleveland North', Basebelles_Category_Style::team_for_post( self::POST_ID )['label'] );
	}

	public function test_post_without_a_team_has_no_team(): void {
		$this->assertNull( Basebelles_Category_Style::team_for_post( self::POST_ID ) );
	}

	public function test_game_post_gets_the_team_label_after_the_category(): void {
		self::set_categories( array( self::games() ) );
		self::set_team( 'toronto-blue-jays', 'Toronto Blue Jays', 'away' );

		$html = $this->render_inside_home_posts( self::FIGURE, self::POST_ID );

		$this->assertStringEndsWith(
			'<span class="bb-cat-pill bb-cat-pill--category">Games &amp; Series</span>'
			. '<span class="bb-cat-pill bb-cat-pill--team">@ Blue Jays</span></figure>',
			$html
		);
	}

	public function test_non_game_post_gets_no_team_label_even_with_a_team(): void {
		self::set_categories( array( self::news() ) );
		self::set_team( 'toronto-blue-jays', 'Toronto Blue Jays', 'home' );

		$html = $this->render_inside_home_posts( self::FIGURE, self::POST_ID );

		$this->assertStringNotContainsString( 'bb-cat-pill--team', $html );
	}

	public function test_team_label_is_escaped(): void {
		self::set_categories( array( self::games() ) );
		self::set_team( 'bad-team', '<i>Bad</i> & Co', 'home' );

		$html = $this->render_inside_home_posts( self::FIGURE, self::POST_ID );

		$this->assertStringContainsString( 'vs &lt;i&gt;Bad&lt;/i&gt; &amp; Co', $html );
		$this->assertStringNotContainsString( '<i>', $html );
	}

	public function test_pill_name_cannot_inject_markup(): void {
		self::set_categories( array( self::term( 9, '<b>Bold</b>', 'bold' ) ) );

		$html = $this->render_inside_home_posts( self::FIGURE, self::POST_ID );

		$this->assertStringContainsString( '&lt;b&gt;Bold&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_no_pill_outside_the_home_posts_query(): void {
		self::set_categories( array( self::games() ) );

		$html = $this->style->render_featured_image( self::FIGURE, array(), self::instance( self::POST_ID ) );

		$this->assertSame( self::FIGURE, $html );
	}

	public function test_no_pill_in_a_query_with_a_different_class(): void {
		self::set_categories( array( self::games() ) );
		$query = self::home_query( 'some-other-list' );

		$this->style->enter_query( $query );
		$html = $this->style->render_featured_image( self::FIGURE, array(), self::instance( self::POST_ID ) );
		$this->style->leave_query( '', $query );

		$this->assertSame( self::FIGURE, $html );
	}

	public function test_pill_stops_after_the_home_posts_query_ends(): void {
		self::set_categories( array( self::games() ) );

		$this->render_inside_home_posts( self::FIGURE, self::POST_ID );
		$html = $this->style->render_featured_image( self::FIGURE, array(), self::instance( self::POST_ID ) );

		$this->assertSame( self::FIGURE, $html );
	}

	public function test_post_without_a_featured_image_gets_no_pill(): void {
		self::set_categories( array( self::games() ) );

		$this->assertSame( '', $this->render_inside_home_posts( '', self::POST_ID ) );
	}

	public function test_post_without_categories_gets_no_pill(): void {
		$this->assertSame( self::FIGURE, $this->render_inside_home_posts( self::FIGURE, self::POST_ID ) );
	}

	public function test_query_class_match_needs_the_whole_class_name(): void {
		self::set_categories( array( self::games() ) );
		$query = self::home_query( 'bb-home-posts-old extra' );

		$this->style->enter_query( $query );
		$html = $this->style->render_featured_image( self::FIGURE, array(), self::instance( self::POST_ID ) );
		$this->style->leave_query( '', $query );

		$this->assertSame( self::FIGURE, $html );
	}
}
