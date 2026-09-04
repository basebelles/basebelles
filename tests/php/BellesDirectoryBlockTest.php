<?php
/**
 * Tests for the Belle directory block template (blocks/belles/render.php).
 *
 * The template is included and its output captured, so these assert on the markup the block
 * actually emits rather than on a reimplementation of it.
 *
 * @package Base*Belles
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BellesDirectoryBlockTest extends TestCase {

	protected function setUp(): void {
		Basebelles_Test_State::reset();
	}

	/**
	 * Seed one Belle.
	 *
	 * @param array $fields Any of name, location, current, historical, gravatar, status.
	 * @return int Post ID.
	 */
	private function add_belle( array $fields = array() ): int {
		$fields = array_merge(
			array(
				'name'       => 'Mika Epstein',
				'location'   => 'Cleveland, OH',
				'current'    => 'José Ramírez',
				'historical' => 'Kenny Lofton',
				'gravatar'   => false,
				'status'     => 'publish',
			),
			$fields
		);

		$meta = array(
			Basebelles_Belles::META_EMAIL_HASH => str_repeat( 'a', 64 ),
			Basebelles_Belles::META_LOCATION   => $fields['location'],
			Basebelles_Belles::META_HISTORICAL => $fields['historical'],
			Basebelles_Belles::META_GRAVATAR   => $fields['gravatar'] ? 1 : 0,
			Basebelles_Belles::META_CURRENT    => array(
				'name' => $fields['current'],
				'id'   => 0,
			),
		);

		return Basebelles_Test_State::add_post(
			array(
				'post_type'   => Basebelles_Belles::POST_TYPE,
				'post_status' => $fields['status'],
				'post_title'  => $fields['name'],
			),
			$meta
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array $block_attrs Block settings, e.g. align or anchor.
	 * @param bool  $in_editor Whether to render as an editor preview.
	 * @return string
	 */
	private function render( array $block_attrs = array(), bool $in_editor = false ): string {
		$block      = $block_attrs;
		$is_preview = $in_editor;

		ob_start();
		require BASEBELLES_PLUGIN_DIR . '/blocks/belles/render.php';

		return (string) ob_get_clean();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Structure
	 * -----------------------------------------------------------------------
	 */

	public function test_belles_render_as_a_list(): void {
		$this->add_belle();

		$html = $this->render();

		$this->assertStringContainsString( '<ul class="basebelles-belles">', $html );
		$this->assertSame( 1, preg_match_all( '#<li class="basebelles-belle">#', $html ) );
	}

	public function test_each_card_carries_the_name_and_location(): void {
		$this->add_belle();

		$html = $this->render();

		$this->assertStringContainsString( '<h3 class="basebelles-belle-name">Mika Epstein</h3>', $html );
		$this->assertStringContainsString( 'Cleveland, OH', $html );
	}

	public function test_favorites_use_definition_list_semantics(): void {
		$this->add_belle();

		$html = $this->render();

		$this->assertStringContainsString( '<dt>Current favorite</dt>', $html );
		$this->assertStringContainsString( '<dd>José Ramírez</dd>', $html );
		$this->assertStringContainsString( '<dt>All-time favorite</dt>', $html );
		$this->assertStringContainsString( '<dd>Kenny Lofton</dd>', $html );
	}

	/** Singular now that a Belle picks one current player, so the label must not say players. */
	public function test_the_current_favorite_label_is_singular(): void {
		$this->add_belle();

		$html = $this->render();

		$this->assertStringNotContainsString( 'Current favorites', $html );
	}

	public function test_a_belle_with_no_favorites_gets_no_favorites_block(): void {
		$this->add_belle(
			array(
				'current'    => '',
				'historical' => '',
			)
		);

		$html = $this->render();

		$this->assertStringNotContainsString( 'basebelles-belle-faves', $html );
		$this->assertStringContainsString( 'Mika Epstein', $html );
	}

	public function test_each_favorite_is_independently_optional(): void {
		$this->add_belle( array( 'historical' => '' ) );

		$html = $this->render();

		$this->assertStringContainsString( '<dt>Current favorite</dt>', $html );
		$this->assertStringNotContainsString( 'All-time favorite', $html );
	}

	public function test_a_belle_with_no_location_renders_without_one(): void {
		$this->add_belle( array( 'location' => '' ) );

		$html = $this->render();

		$this->assertStringNotContainsString( 'basebelles-belle-location', $html );
		$this->assertStringContainsString( 'Mika Epstein', $html );
	}

	public function test_alignment_and_anchor_reach_the_markup(): void {
		$this->add_belle();

		$html = $this->render(
			array(
				'align'  => 'wide',
				'anchor' => 'the-belles',
			)
		);

		$this->assertStringContainsString( 'class="basebelles-belles alignwide"', $html );
		$this->assertStringContainsString( 'id="the-belles"', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Avatars
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Gravatar coverage is low outside dev circles, so the monogram is the common case rather
	 * than the exception.
	 */
	public function test_a_belle_without_a_gravatar_gets_initials_and_no_image(): void {
		$this->add_belle();

		$html = $this->render();

		$this->assertStringContainsString( '<span class="basebelles-belle-monogram" aria-hidden="true">ME</span>', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	/** The monogram stays underneath, so a Gravatar that fails to load is never a blank circle. */
	public function test_a_gravatar_is_layered_over_the_monogram(): void {
		$this->add_belle( array( 'gravatar' => true ) );

		$html = $this->render();

		$this->assertStringContainsString( 'basebelles-belle-monogram', $html );
		$this->assertStringContainsString( 'class="basebelles-belle-photo"', $html );
		$this->assertStringContainsString( str_repeat( 'a', 64 ), $html );
	}

	/** d=blank keeps Gravatar from painting its own placeholder over the monogram. */
	public function test_the_gravatar_request_asks_for_a_transparent_default(): void {
		$this->add_belle( array( 'gravatar' => true ) );

		$this->assertStringContainsString( 'd=blank', $this->render() );
	}

	/** Decorative: the name is already the heading right beside it. */
	public function test_the_avatar_image_is_marked_decorative(): void {
		$this->add_belle( array( 'gravatar' => true ) );

		$this->assertStringContainsString( 'alt=""', $this->render() );
	}

	public function test_the_monogram_colour_is_passed_as_a_custom_property(): void {
		$this->add_belle();

		$this->assertStringContainsString( '--bb-belle-monogram-bg:', $this->render() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Moderation
	 * -----------------------------------------------------------------------
	 */

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function unapproved_statuses(): array {
		return array(
			'awaiting review' => array( 'pending' ),
			'spam'            => array( 'trash' ),
			'draft'           => array( 'draft' ),
		);
	}

	#[DataProvider( 'unapproved_statuses' )]
	public function test_only_approved_belles_are_listed( string $status ): void {
		$this->add_belle(
			array(
				'name'   => 'Unapproved Person',
				'status' => $status,
			)
		);

		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_approved_and_pending_belles_are_separated(): void {
		$this->add_belle( array( 'name' => 'Approved Belle' ) );
		$this->add_belle(
			array(
				'name'   => 'Pending Belle',
				'status' => 'pending',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Approved Belle', $html );
		$this->assertStringNotContainsString( 'Pending Belle', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Ordering and emptiness
	 * -----------------------------------------------------------------------
	 */

	public function test_belles_are_listed_alphabetically(): void {
		$this->add_belle( array( 'name' => 'Zoe Zither' ) );
		$this->add_belle( array( 'name' => 'Anna Apple' ) );
		$this->add_belle( array( 'name' => 'Molly Middle' ) );

		$html = $this->render();

		$this->assertSame(
			array( 'Anna Apple', 'Molly Middle', 'Zoe Zither' ),
			$this->names( $html )
		);
	}

	/**
	 * Names in document order.
	 *
	 * @param string $html Rendered markup.
	 * @return string[]
	 */
	private function names( string $html ): array {
		preg_match_all( '#<h3 class="basebelles-belle-name">(.*?)</h3>#s', $html, $found );

		return $found[1];
	}

	/** A visitor should see an empty page, not an editor warning. */
	public function test_an_empty_directory_renders_nothing_on_the_front_end(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	/** An editor placing the block needs to know it works and is simply waiting on approvals. */
	public function test_an_empty_directory_explains_itself_in_the_editor(): void {
		$html = $this->render( array(), true );

		$this->assertStringContainsString( 'placeholder', $html );
		$this->assertStringContainsString( 'No Belles have been approved yet', $html );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Escaping
	 * -----------------------------------------------------------------------
	 */

	public function test_the_name_is_escaped(): void {
		$this->add_belle( array( 'name' => 'Mika <script>alert(1)</script>' ) );

		$html = $this->render();

		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	public function test_the_location_is_escaped(): void {
		$this->add_belle( array( 'location' => 'Cleveland "OH" & <b>Ohio</b>' ) );

		$html = $this->render();

		$this->assertStringNotContainsString( '<b>Ohio</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;', $html );
	}

	public function test_the_favorites_are_escaped(): void {
		$this->add_belle(
			array(
				'current'    => '<em>Kwan</em>',
				'historical' => '<em>Lofton</em>',
			)
		);

		$html = $this->render();

		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringContainsString( '&lt;em&gt;', $html );
	}

	public function test_the_anchor_is_escaped(): void {
		$this->add_belle();

		$html = $this->render( array( 'anchor' => 'belles" onmouseover="alert(1)' ) );

		$this->assertStringNotContainsString( 'onmouseover="alert(1)"', $html );
	}

	/** An unexpected align value must not be able to break out of the class attribute. */
	public function test_the_alignment_is_reduced_to_a_safe_class(): void {
		$this->add_belle();

		$html = $this->render( array( 'align' => 'wide" onclick="x' ) );

		$this->assertStringContainsString( 'class="basebelles-belles alignwideonclickx"', $html );
	}
}
