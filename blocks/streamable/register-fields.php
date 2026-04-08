<?php
/**
 * Local ACF field group for the Streamable block.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_basebelles_streamable',
				'title'                 => 'Streamable video',
				'fields'                => array(
					array(
						'key'          => 'field_basebelles_streamable_url',
						'label'        => 'Streamable URL',
						'name'         => 'streamable_url',
						'type'         => 'url',
						'instructions' => 'Paste a Streamable link (for example https://streamable.com/m/… or https://www.mlb.com/video/…).',
						'required'     => 0,
						'placeholder'  => 'https://streamable.com/m/…',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/basebelles-streamable',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
			)
		);
	}
);
