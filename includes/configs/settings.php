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
				'title'       => esc_html__( 'Appearance', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Assign a Font Awesome icon and an optional colour to any account menu item, and control how the account menus look. Leave the colour empty to inherit the menu text colour. If your site replaces or subsets Font Awesome, make sure any icons you choose here are included in it, otherwise they will not display.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 10,

				'fields'      => [
					'amehp_icons'         => [
						'label'       => esc_html__( 'Menu Item Styling', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Choose a menu item, then optionally give it an icon, an icon colour and a text colour. Styling for custom items is set in the Custom Items section below.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'repeater',
						'caption'     => esc_html__( 'Add Item', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 10,

						'fields'      => [
							'item'        => [
								'type'        => 'select',
								'options'     => 'amehp_menu_items',
								'placeholder' => esc_html__( 'Select Menu Item', 'account-menu-enhancer-for-hivepress' ),
								'required'    => true,
								'_order'      => 10,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Menu Item', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'icon'        => [
								'type'        => 'select',
								'options'     => 'icons',
								'placeholder' => esc_html__( 'Select Icon', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 20,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Icon', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'colour'      => [
								'type'       => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
								'_order'     => 30,

								'attributes' => [
									'class'       => [ 'amehp-colour' ],
									'placeholder' => esc_html__( 'Icon Colour', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'text_colour' => [
								'type'       => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
								'_order'     => 40,

								'attributes' => [
									'class'       => [ 'amehp-colour' ],
									'placeholder' => esc_html__( 'Text Colour', 'account-menu-enhancer-for-hivepress' ),
								],
							],
						],
					],

					'amehp_icon_colour'   => [
						'label'       => esc_html__( 'Icon Colour', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Applies one colour to every menu item icon at once, so you do not have to colour them one by one. Leave it empty to inherit the menu text colour. Set a colour on an individual item above to override it there.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
						'_order'      => 20,

						'attributes'  => [
							'class' => [ 'amehp-colour' ],
						],
					],

					'amehp_menu_weight'   => [
						'label'       => esc_html__( 'Menu Item Weight', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Sets the font weight of the account menu items. Choose Default to keep the theme style.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'select',
						'placeholder' => esc_html__( 'Default', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 30,

						'options'     => [
							'400' => esc_html__( 'Normal', 'account-menu-enhancer-for-hivepress' ),
							'500' => esc_html__( 'Medium', 'account-menu-enhancer-for-hivepress' ),
							'600' => esc_html__( 'Semi-bold', 'account-menu-enhancer-for-hivepress' ),
							'700' => esc_html__( 'Bold', 'account-menu-enhancer-for-hivepress' ),
						],
					],

					'amehp_hide_chevrons' => [
						'label'       => esc_html__( 'Menu Chevrons', 'account-menu-enhancer-for-hivepress' ),
						'caption'     => esc_html__( 'Hide the theme navigation menu arrows', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Hides the default arrow markers that some themes add before the account menu items, so only your chosen icons show. Other menus, such as those in the footer, are not affected.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 40,
					],
				],
			],

			'items'     => [
				'title'       => esc_html__( 'Custom Items', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Add your own links to the account menu. For each item, set a label and either choose a link from the dropdown or enter a custom URL (for example https://example.com/page or /page). The Order field controls the position, where lower numbers appear higher (built-in items range from 5 to 1000, with Sign Out at 1000).', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 20,

				'fields'      => [
					'amehp_custom_items' => [
						'label'       => esc_html__( 'Menu Items', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Add one row per link. The label is required, and so is either a link from the dropdown or a custom URL. Everything else is optional: an icon and colours, which menus the item appears in, its position, and the user roles that can see it. Administrators always see every item, so check a role restriction with a non-administrator account.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'repeater',
						'caption'     => esc_html__( 'Add Item', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 10,

						'fields'      => [
							'label'       => [
								'type'       => 'text',
								'max_length' => 100,
								'required'   => true,
								'_order'     => 10,

								'attributes' => [
									'placeholder' => esc_html__( 'Label', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'link'        => [
								'type'        => 'select',
								'options'     => 'amehp_links',
								'placeholder' => esc_html__( 'Select Link', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 20,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Link', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'url'         => [
								'type'       => 'text',
								'max_length' => 2048,
								'_order'     => 30,

								'attributes' => [
									'placeholder' => esc_html__( 'Custom URL', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'icon'        => [
								'type'        => 'select',
								'options'     => 'icons',
								'placeholder' => esc_html__( 'Select Icon', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 40,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Icon', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'colour'      => [
								'type'       => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
								'_order'     => 50,

								'attributes' => [
									'class'       => [ 'amehp-colour' ],
									'placeholder' => esc_html__( 'Icon Colour', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'text_colour' => [
								'type'       => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
								'_order'     => 55,

								'attributes' => [
									'class'       => [ 'amehp-colour' ],
									'placeholder' => esc_html__( 'Text Colour', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'menus'       => [
								'type'        => 'select',
								'placeholder' => esc_html__( 'Both Menus', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 60,

								'options'     => [
									'hivepress'   => esc_html__( 'HivePress Menu Only', 'account-menu-enhancer-for-hivepress' ),
									'woocommerce' => esc_html__( 'WooCommerce Menu Only', 'account-menu-enhancer-for-hivepress' ),
								],

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Menus', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'order'       => [
								'type'       => 'number',
								'min_value'  => 0,
								'max_value'  => 10000,
								'_order'     => 70,

								'attributes' => [
									'placeholder' => esc_html__( 'Order', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'roles'       => [
								'type'        => 'select',
								'options'     => 'amehp_roles',
								'multiple'    => true,
								'placeholder' => esc_html__( 'All Roles', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 80,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Roles', 'account-menu-enhancer-for-hivepress' ),
								],
							],
						],
					],
				],
			],

			'behaviour' => [
				'title'       => esc_html__( 'Behaviour', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Control how the account menus behave and which items appear.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 5,

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

			'removal'   => [
				'title'       => esc_html__( 'Removing the Plugin', 'account-menu-enhancer-for-hivepress' ),

				// The section description carries the warning about WordPress's own wording,
				// because the delete screen prints "will also delete its data" for any plugin
				// that ships an uninstall.php, whatever that file actually does
				// (wp-admin/plugins.php:376-380), and a site owner reading it has no way to
				// tell that it does not apply here.
				'description' => esc_html__( 'Your menu settings are kept if you delete this plugin, so you can reinstall it and pick up where you left off. WordPress shows its own warning on the delete screen saying the data goes too, but that warning is the same for every plugin and does not apply here unless you tick the box below. Switching the plugin off never removes anything.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 30,

				'fields'      => [
					'amehp_delete_data' => [
						'label'       => esc_html__( 'Delete All Data', 'account-menu-enhancer-for-hivepress' ),
						'caption'     => esc_html__( 'Delete everything when this plugin is deleted', 'account-menu-enhancer-for-hivepress' ),

						// Spelled out rather than summarised as "all data": the owner is being
						// asked to authorise something irreversible that nothing will confirm at
						// the time, so they have to be able to see exactly what goes.
						'description' => esc_html__( 'Leave this unticked unless you are certain. With it ticked, deleting the plugin also removes every icon and colour you have chosen, your custom menu items, the list of items you have hidden, and every other setting on this page. It cannot be undone and nothing asks you to confirm at the time, so copy down anything you want to keep first. Deleting the plugin with this unticked keeps all of it.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 10,
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
			'amehp_unify_account'  => [
				'label'       => esc_html__( 'WooCommerce Integration', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Show WooCommerce account pages inside the HivePress account layout', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Renders the WooCommerce account pages (Dashboard, Addresses, Payment methods, Account details and Downloads) inside the HivePress account template, the same way HivePress already displays the Orders page, so every account page shares one sidebar menu.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_order'      => 10,
			],

			'amehp_merge_menus'    => [
				'label'       => esc_html__( 'Menu Merging', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Merge the HivePress and WooCommerce account menus', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Adds the WooCommerce account links to the HivePress menu, and the HivePress account links to the WooCommerce menu, so both menus list the same items.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_order'      => 20,
			],

			'amehp_wc_badges'      => [
				'label'       => esc_html__( 'Counters', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Show the HivePress counters in the WooCommerce menu', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Mirrors the HivePress menu counters (for example unread messages) next to the matching items in the WooCommerce account menu.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_parent'     => 'amehp_merge_menus',
				'_order'      => 30,
			],

			'amehp_hide_wc_header' => [
				'label'       => esc_html__( 'WooCommerce Page Header', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Hide the page header on WooCommerce account pages', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Hides the large page title header on the WooCommerce account pages so they match the HivePress account pages.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'_order'      => 40,
			],
		],
		$settings['account_menu']['sections']['behaviour']['fields']
	);
}

return $settings;
