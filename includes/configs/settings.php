<?php
/**
 * Settings configuration.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$settings = [
	'account_menu' => [
		'title'    => esc_html__( 'Account Menu', 'account-menu-enhancer-for-hivepress' ),
		'_order'   => 150,

		'sections' => [
			'display'   => [
				'title'       => esc_html__( 'Icons', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Assign a Font Awesome icon and an optional colour to any account menu item. Leave the colour empty to inherit the menu text colour.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 10,

				'fields'      => [
					'amehp_icons'       => [
						'label'       => esc_html__( 'Menu Item Icons', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Choose a menu item, then pick its icon and an optional colour. Icons for custom items are set in the Custom Items section below.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'repeater',
						'caption'     => esc_html__( 'Add Icon', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 10,

						'fields'      => [
							'item'   => [
								'type'        => 'select',
								'options'     => 'amehp_menu_items',
								'placeholder' => esc_html__( 'Select Menu Item', 'account-menu-enhancer-for-hivepress' ),
								'required'    => true,
								'_order'      => 10,
							],

							'icon'   => [
								'type'        => 'select',
								'options'     => 'icons',
								'placeholder' => esc_html__( 'Select Icon', 'account-menu-enhancer-for-hivepress' ),
								'required'    => true,
								'_order'      => 20,
							],

							'colour' => [
								'type'       => 'color',
								'_order'     => 30,

								'attributes' => [
									'class'       => [ 'amehp-colour' ],
									'placeholder' => esc_html__( 'Colour', 'account-menu-enhancer-for-hivepress' ),
								],
							],
						],
					],

					'amehp_icon_colour' => [
						'label'       => esc_html__( 'Default Icon Colour', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'This colour is used for icons that have no colour of their own. Leave empty to inherit the menu text colour.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'color',
						'_order'      => 20,

						'attributes'  => [
							'class' => [ 'amehp-colour' ],
						],
					],
				],
			],

			'items'     => [
				'title'       => esc_html__( 'Custom Items', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Add your own links to the account menu. For each item, set a label and either choose a link from the dropdown or enter a custom URL (for example https://example.com/page or /page). The Order field controls the position, where lower numbers appear higher (built-in items range from 5 to 1000, with Sign Out at 1000).', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 20,

				'fields'      => [
					'amehp_custom_items' => [
						'label'   => esc_html__( 'Menu Items', 'account-menu-enhancer-for-hivepress' ),
						'type'    => 'repeater',
						'caption' => esc_html__( 'Add Item', 'account-menu-enhancer-for-hivepress' ),
						'_order'  => 10,

						'fields'  => [
							'label'  => [
								'type'       => 'text',
								'max_length' => 100,
								'required'   => true,
								'_order'     => 10,

								'attributes' => [
									'placeholder' => esc_html__( 'Label', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'link'   => [
								'type'        => 'select',
								'options'     => 'amehp_links',
								'placeholder' => esc_html__( 'Select Link', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 20,
							],

							'url'    => [
								'type'       => 'text',
								'max_length' => 2048,
								'_order'     => 30,

								'attributes' => [
									'placeholder' => esc_html__( 'Custom URL', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'icon'   => [
								'type'        => 'select',
								'options'     => 'icons',
								'placeholder' => esc_html__( 'Select Icon', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 40,
							],

							'colour' => [
								'type'       => 'color',
								'_order'     => 50,

								'attributes' => [
									'class'       => [ 'amehp-colour' ],
									'placeholder' => esc_html__( 'Colour', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'menus'  => [
								'type'        => 'select',
								'placeholder' => esc_html__( 'Both Menus', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 60,

								'options'     => [
									'hivepress'   => esc_html__( 'HivePress Menu Only', 'account-menu-enhancer-for-hivepress' ),
									'woocommerce' => esc_html__( 'WooCommerce Menu Only', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'order'  => [
								'type'       => 'number',
								'min_value'  => 0,
								'max_value'  => 10000,
								'_order'     => 70,

								'attributes' => [
									'placeholder' => esc_html__( 'Order', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'roles'  => [
								'type'        => 'select',
								'options'     => 'amehp_roles',
								'multiple'    => true,
								'placeholder' => esc_html__( 'All Roles', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 80,
							],
						],
					],
				],
			],

			'behaviour' => [
				'title'       => esc_html__( 'Behaviour', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Control how the account menus behave and which items appear.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 30,

				'fields'      => [
					'amehp_hidden_items' => [
						'label'       => esc_html__( 'Hidden Items', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Select the menu items that should be hidden from the account menus.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'select',
						'options'     => 'amehp_menu_items',
						'multiple'    => true,
						'_order'      => 100,
					],
				],
			],
		],
	],
];

// Add the WooCommerce settings if WooCommerce is active.
if ( class_exists( 'WooCommerce' ) ) {
	$settings['account_menu']['sections']['behaviour']['fields'] = array_merge(
		[
			'amehp_unify_account' => [
				'label'       => esc_html__( 'WooCommerce Integration', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Show WooCommerce account pages inside the HivePress account layout', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Renders the WooCommerce account pages (Dashboard, Addresses, Payment methods, Account details and Downloads) inside the HivePress account template, the same way HivePress already displays the Orders page, so every account page shares one sidebar menu.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_order'      => 10,
			],

			'amehp_merge_menus'   => [
				'label'       => esc_html__( 'Menu Merging', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Merge the HivePress and WooCommerce account menus', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Adds the WooCommerce account links to the HivePress menu, and the HivePress account links to the WooCommerce menu, so both menus list the same items.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_order'      => 20,
			],

			'amehp_wc_badges'     => [
				'label'       => esc_html__( 'Counters', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Show the HivePress counters in the WooCommerce menu', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Mirrors the HivePress menu counters (for example unread messages) next to the matching items in the WooCommerce account menu.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_parent'     => 'amehp_merge_menus',
				'_order'      => 30,
			],
		],
		$settings['account_menu']['sections']['behaviour']['fields']
	);
}

return $settings;
