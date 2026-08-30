<?php
/**
 * Settings configuration.
 *
 * The Persistent Menu Items and Placeholder Pages sections are added to this
 * tab by the Amehp_Persistent_Menu component, because their fields depend on
 * which HivePress extensions are active. Section order across the whole tab:
 * Behaviour (5), Appearance (10), Custom Items (20), Persistent Menu Items
 * (30), Placeholder Pages (40), Removing the Plugin (1000). The Live preview
 * panel is not a section from this config at all: the menu enhancer component
 * registers it on admin_init and moves it to the front of the list.
 *
 * @package AccountMenuEnhancer\Configs
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$amehp_settings = [
	'account_menu' => [
		'title'    => esc_html__( 'Account Menu', 'account-menu-enhancer-for-hivepress' ),
		'_order'   => 150,

		'sections' => [
			'display'   => [
				'title'       => esc_html__( 'Appearance', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Control how the account menus look: icons, colours, sizing and fonts. The icon dropdowns include the Font Awesome 6 and 7 names and brand icons (for example stripe-s and paypal); the full Font Awesome library is loaded automatically when a chosen icon needs it. If you subset Font Awesome yourself, make sure your chosen icons are included.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 10,

				'fields'      => [
					// The recolour-all option sits ABOVE the per-item styling
					// on purpose: owners were finding it only after colouring
					// every item by hand.
					'amehp_icon_colour'          => [
						'label'       => esc_html__( 'Icon Colour', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Colours every menu icon at once. Leave it empty to inherit the menu text colour. A colour set on an individual item below overrides it there.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
						'_order'      => 10,

						'attributes'  => [
							'class' => [ 'amehp-colour' ],
						],
					],

					'amehp_icon_background'      => [
						'label'       => esc_html__( 'Icon Background Colour', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Draws a round colour chip behind every menu icon. Leave it empty for no background.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => class_exists( '\HivePress\Fields\Color' ) ? 'color' : 'text',
						'_order'      => 15,

						'attributes'  => [
							'class' => [ 'amehp-colour' ],
						],
					],

					'amehp_icon_size'            => [
						'label'       => esc_html__( 'Icon Size (px)', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'The size of the menu icons. Leave it empty to scale with your theme text size.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 8,
						'max_value'   => 48,
						'_order'      => 20,
					],

					'amehp_icon_weight'          => [
						'label'       => esc_html__( 'Icon Weight', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Thickens every menu icon. Choose Normal to keep the standard Font Awesome weight. A weight set on an individual item below overrides it there.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'select',
						'placeholder' => esc_html__( 'Normal', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 25,

						'options'     => [
							'semibold' => esc_html__( 'Semi-bold', 'account-menu-enhancer-for-hivepress' ),
							'bold'     => esc_html__( 'Bold', 'account-menu-enhancer-for-hivepress' ),
						],
					],

					'amehp_icons'                => [
						'label'       => esc_html__( 'Menu Item Styling', 'account-menu-enhancer-for-hivepress' ),

						/*
						 * The middle sentence used to read "and drag the handle
						 * to reorder the cards", which stopped being true in
						 * 3.3.4 when removeDragHandles() took the handles off
						 * this plugin's repeater cards: the menu is arranged in
						 * the Live preview panel, and a second set of handles
						 * offered a way to order the menu that no longer ordered
						 * it. Corrected in 3.3.9, which also says where the
						 * ordering really happens rather than only removing the
						 * claim - an owner who read the old sentence went looking
						 * for a handle that is not there.
						 */
						'description' => esc_html__( 'Choose a menu item, then optionally give it an icon, an icon weight and colours. Click a card header to collapse or expand it. The order of the cards makes no difference to the menu; arrange the menu itself in the Live preview panel. Styling for custom items is set in the Custom Items section.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'repeater',
						'caption'     => esc_html__( 'Add Item', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 30,

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
								'options'     => 'amehp_icons',

								// Core only arms the icon-preview dropdown for
								// its own "icons" list (components/class-form.php
								// :85), so the attribute is set by hand here.
								'placeholder' => esc_html__( 'Select Icon', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 20,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Icon', 'account-menu-enhancer-for-hivepress' ),
									'data-template'    => 'icon',
								],
							],

							'weight'      => [
								'type'        => 'select',
								'placeholder' => esc_html__( 'Normal Weight', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 25,

								'options'     => [
									'semibold' => esc_html__( 'Semi-bold Icon', 'account-menu-enhancer-for-hivepress' ),
									'bold'     => esc_html__( 'Bold Icon', 'account-menu-enhancer-for-hivepress' ),
								],

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Icon Weight', 'account-menu-enhancer-for-hivepress' ),
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

					'amehp_menu_weight'          => [
						'label'       => esc_html__( 'Menu Item Weight', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'The font weight of the menu item wording. Choose Default to keep the theme style.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'select',
						'placeholder' => esc_html__( 'Default', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 40,

						'options'     => [
							'400' => esc_html__( 'Normal', 'account-menu-enhancer-for-hivepress' ),
							'500' => esc_html__( 'Medium', 'account-menu-enhancer-for-hivepress' ),
							'600' => esc_html__( 'Semi-bold', 'account-menu-enhancer-for-hivepress' ),
							'700' => esc_html__( 'Bold', 'account-menu-enhancer-for-hivepress' ),
						],
					],

					'amehp_sidebar_heading_font' => [
						'label'       => esc_html__( 'Sidebar Menu Font', 'account-menu-enhancer-for-hivepress' ),
						'caption'     => esc_html__( 'Use the theme Heading Font for the sidebar account menus', 'account-menu-enhancer-for-hivepress' ),
						// The Google Fonts note is here because a privacy-minded
						// owner would want to know before ticking, not after:
						// with this on, the settings screen fetches the font
						// from Google so the live preview can show it, and
						// that is the only third-party request wp-admin makes
						// on this tab.
						'description' => esc_html__( 'Applies the Heading Font from Appearance > Customise > Fonts to the HivePress and WooCommerce sidebar account menus, matching the account dropdown. Requires an official HivePress theme. With this switched on, this settings screen loads that font from Google so the preview can show it.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 50,
					],

					'amehp_hide_chevrons'        => [
						'label'       => esc_html__( 'Menu Chevrons', 'account-menu-enhancer-for-hivepress' ),
						'caption'     => esc_html__( 'Hide the theme navigation menu arrows', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Hides the arrow markers some themes add before the account menu items, so only your chosen icons show. Other menus are not affected.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'checkbox',
						'_order'      => 60,
					],
				],
			],

			'items'     => [
				'title'       => esc_html__( 'Custom Items', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Add your own links to the account menus. Each item needs a label and either a link from the dropdown or a custom URL. To decide where an item sits in the menu, drag it in the Live preview panel above, alongside the built-in items.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 20,

				'fields'      => [
					'amehp_custom_items' => [
						'label'       => esc_html__( 'Menu Items', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'Everything except the label and link is optional. Administrators always see every item, so check a role restriction with a non-administrator account.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'repeater',
						'caption'     => esc_html__( 'Add Item', 'account-menu-enhancer-for-hivepress' ),
						'_order'      => 10,

						'fields'      => [

							/*
							 * The row's own id, hidden and never edited.
							 *
							 * It is what the stored menu order refers to this
							 * item by. Before 3.3.0 that was the row's
							 * position, so deleting one custom item handed its
							 * saved place in the menu to the next one down.
							 * backend.js stamps an id onto a newly added row
							 * and amehp_migrate_v330_settings() onto every row
							 * that predates this; the cell itself is hidden by
							 * backend.css.
							 *
							 * No `class` attribute here, deliberately. The
							 * hidden field boots through Field::boot() rather
							 * than Text::boot() (hivepress/includes/fields
							 * /class-hidden.php), and a class passed in
							 * attributes does not survive it: the input
							 * renders as `hp-field hp-field--hidden` and
							 * nothing else, measured on 2026-08-30. The
							 * stylesheet and the script therefore find this
							 * field by its name ending in [uid], which is
							 * what the repeater guarantees.
							 */
							'uid'         => [
								'type'       => 'hidden',
								'max_length' => 32,
								'_order'     => 5,
							],

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

							/*
							 * A URL field, not a text field. A text field
							 * sanitises with sanitize_text_field(), which
							 * strips percent-encoded octets: "/a%20b?x=1"
							 * stored as "/ab?x=1", silently. Fields\URL uses
							 * esc_url_raw(), which keeps them.
							 *
							 * display_type stays "text" so the control is a
							 * plain box: <input type="url"> would demand a
							 * scheme and reject the relative paths
							 * get_custom_item_url() resolves against
							 * home_url(). See the same note on the Button URL
							 * field in class-amehp-persistent-menu.php.
							 */
							'url'         => [
								'type'         => 'url',
								'display_type' => 'text',
								'max_length'   => 2048,
								'_order'       => 30,

								'attributes'   => [
									'placeholder' => esc_html__( 'Custom URL', 'account-menu-enhancer-for-hivepress' ),
								],
							],

							'icon'        => [
								'type'        => 'select',
								'options'     => 'amehp_icons',

								// Core only arms the icon-preview dropdown for
								// its own "icons" list (components/class-form.php
								// :85), so the attribute is set by hand here.
								'placeholder' => esc_html__( 'Select Icon', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 40,

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Icon', 'account-menu-enhancer-for-hivepress' ),
									'data-template'    => 'icon',
								],
							],

							'weight'      => [
								'type'        => 'select',
								'placeholder' => esc_html__( 'Normal Weight', 'account-menu-enhancer-for-hivepress' ),
								'_order'      => 45,

								'options'     => [
									'semibold' => esc_html__( 'Semi-bold Icon', 'account-menu-enhancer-for-hivepress' ),
									'bold'     => esc_html__( 'Bold Icon', 'account-menu-enhancer-for-hivepress' ),
								],

								'attributes'  => [
									'data-amehp-label' => esc_html__( 'Icon Weight', 'account-menu-enhancer-for-hivepress' ),
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

							/*
							 * The Order number box was retired in 3.2.0.
							 *
							 * Dragging an item in the live preview says the
							 * same thing and says it in front of the menu it
							 * changes, so keeping a number box as well left
							 * two controls describing one outcome - and when
							 * two controls describe one outcome they disagree
							 * eventually and nobody can tell which won.
							 *
							 * A number already stored in a row is still read
							 * (get_custom_items()), so a site that upgrades
							 * and changes nothing renders exactly as before:
							 * the stored number stays the item's position
							 * until the owner drags it, and dragging then
							 * writes an explicit order that supersedes it.
							 * That is why no migration writes an order here -
							 * the correct order is the one already being
							 * rendered, and seeding a computed copy of it from
							 * wp-admin, where the account menu is only
							 * partly knowable, could only make it worse.
							 */
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
					'amehp_icon_spacing' => [
						'label'       => esc_html__( 'Icon Spacing (px)', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'The gap between a menu icon and its wording. Leave it empty to scale with your theme text size. Icons sit in a fixed-width column, so the visible gap is slightly wider than the number you type.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'number',
						'min_value'   => 0,
						'max_value'   => 60,
						'_order'      => 90,
					],

					'amehp_hidden_items' => [
						'label'       => esc_html__( 'Hidden Items', 'account-menu-enhancer-for-hivepress' ),
						'description' => esc_html__( 'The menu items hidden from both account menus.', 'account-menu-enhancer-for-hivepress' ),
						'type'        => 'select',
						'options'     => 'amehp_menu_items',
						'multiple'    => true,
						'_order'      => 100,
					],

					/*
					 * The menu order, written by dragging the items in the live
					 * preview panel.
					 *
					 * A field rather than a control of its own because only a
					 * registered field is in the settings group options.php
					 * will accept, and only a field posts and validates with
					 * the rest of the tab. It is hidden, and its row is taken
					 * off the screen by the preview script, because the panel
					 * is where the reordering is done and a second copy of the
					 * order as a box of comma-separated keys would be a
					 * control nobody should edit by hand. The stored value is
					 * re-checked key by key when it is read, in
					 * get_menu_order(), rather than trusted from here.
					 */
					'amehp_menu_order'   => [
						'label'      => esc_html__( 'Menu Order', 'account-menu-enhancer-for-hivepress' ),
						'type'       => 'hidden',
						'max_length' => 10000,
						'_order'     => 110,

						'attributes' => [
							'class' => [ 'amehp-menu-order' ],
						],
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
				'description' => esc_html__( 'Your settings are kept if you delete this plugin, so you can reinstall it and pick up where you left off. WordPress shows its own data-deletion warning on the delete screen, but it only applies here if you tick the box below. Switching the plugin off never removes anything.', 'account-menu-enhancer-for-hivepress' ),
				'_order'      => 1000,

				'fields'      => [
					'amehp_delete_data' => [
						'label'       => esc_html__( 'Delete All Data', 'account-menu-enhancer-for-hivepress' ),
						'caption'     => esc_html__( 'Delete everything when this plugin is deleted', 'account-menu-enhancer-for-hivepress' ),

						// Spelled out rather than summarised as "all data": the owner is being
						// asked to authorise something irreversible that nothing will confirm at
						// the time, so they have to be able to see exactly what goes.
						'description' => esc_html__( 'Leave this unticked unless you are certain. With it ticked, deleting the plugin also removes your icons and colours, custom menu items, hidden items, persistent menu choices, button wording and every other setting on this page. It cannot be undone and nothing asks you to confirm, so copy down anything you want to keep first.', 'account-menu-enhancer-for-hivepress' ),
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
	$amehp_settings['account_menu']['sections']['behaviour']['fields'] = array_merge(
		[
			// One switch since version 3.0.0, replacing the separate
			// "WooCommerce Integration" and "Menu Merging" checkboxes that
			// confused owners. amehp_maybe_migrate() carries the old pair
			// forward, and the component falls back to them until it runs.
			'amehp_wc_integration'  => [
				'label'       => esc_html__( 'WooCommerce Integration', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Combine the HivePress and WooCommerce account menus', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Shows every HivePress and WooCommerce account link in each menu (the account dropdown and both sidebar menus), and renders the WooCommerce account pages inside the HivePress account layout so they all share one sidebar.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_order'      => 10,
			],

			'amehp_wc_badges'       => [
				'label'       => esc_html__( 'Counters', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Show the HivePress counters in the WooCommerce menu', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Mirrors the HivePress menu counters (for example unread messages) into the WooCommerce account menu.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'default'     => true,
				'_parent'     => 'amehp_wc_integration',
				'_order'      => 20,
			],

			'amehp_hide_wc_header'  => [
				'label'       => esc_html__( 'WooCommerce Page Header', 'account-menu-enhancer-for-hivepress' ),
				'caption'     => esc_html__( 'Hide the page header on WooCommerce account pages', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'Hides the large page title header on the WooCommerce account pages so they match the HivePress account pages.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'checkbox',
				'_order'      => 30,
			],

			/*
			 * An ADDITION to "Hidden Items", never a replacement.
			 *
			 * That list hides from both account menus, and it keeps that
			 * meaning and every value already stored in it. This one takes an
			 * item out of the WooCommerce account menu alone, so members keep
			 * it in the HivePress account menu.
			 *
			 * The label has to say so on its own. The tooltip is a hover, which
			 * a mobile administrator never sees, and a hiding list that looks as
			 * though it might hide from everywhere is worse than no setting at
			 * all: an owner would have to test it on a live menu to find out
			 * what it does. Hence "Also", and hence naming the menu in the label
			 * rather than only in the description.
			 *
			 * It lives in this block, so it appears only where there is a second
			 * menu to hide anything from; the order number puts it directly
			 * beneath "Hidden Items" wherever it does appear.
			 */
			'amehp_hidden_wc_items' => [
				'label'       => esc_html__( 'Also Hidden from the WooCommerce Menu', 'account-menu-enhancer-for-hivepress' ),
				'description' => esc_html__( 'The menu items hidden from the WooCommerce account menu only. They stay in the HivePress account menu, so your members can still reach them there. Anything chosen in Hidden Items above is already hidden from both menus.', 'account-menu-enhancer-for-hivepress' ),
				'type'        => 'select',
				'options'     => 'amehp_wc_menu_items',
				'multiple'    => true,
				'_order'      => 105,
			],
		],
		$amehp_settings['account_menu']['sections']['behaviour']['fields']
	);
}

return $amehp_settings;
