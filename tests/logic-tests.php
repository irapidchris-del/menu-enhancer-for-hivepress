<?php
/**
 * Logic QA harness for Account Menu Enhancer for HivePress (current working tree).
 * Stubs WordPress/HivePress, then drives the real component code.
 *
 * These tests exist for the places where a wrong answer is SILENT: the plugin
 * carries on working and the owner's account menu is quietly wrong. Ordering,
 * hiding, the WooCommerce merge, the placeholder-page fallbacks and the icon
 * font choice are all in that category. The migrations, which are the same
 * shape of failure but cannot be re-run by hand, are in migration-tests.php.
 *
 * Env: see stubs.php.
 *
 * @package AccountMenuEnhancer\Tests
 */

require __DIR__ . '/stubs.php';

require AMEHP_TEST_PLUGIN_FILE;
require AMEHP_TEST_PLUGIN_DIR . '/includes/components/class-amehp-menu-enhancer.php';
require AMEHP_TEST_PLUGIN_DIR . '/includes/components/class-amehp-persistent-menu.php';

$MENU    = new HivePress\Components\Amehp_Menu_Enhancer();
$PERSIST = new HivePress\Components\Amehp_Persistent_Menu();

/**
 * Clears the site state and every per-request cache the two components hold.
 *
 * The caches are the point: get_base_hp_items() and get_base_wc_items() memoise
 * for the whole request on purpose, so a test that changed the base menu without
 * clearing them would be asserting against the previous test's menu.
 */
function amehp_test_reset() {
	global $MENU, $PERSIST;

	amehp_test_reset_globals();

	$GLOBALS['_components'] = [
		'amehp_menu_enhancer'    => $MENU,
		'amehp_persistent_menu'  => $PERSIST,
	];

	foreach (
		[
			'hp_items'          => null,
			'wc_items'          => null,
			'wc_urls'           => [],
			'needs_fontawesome' => false,
			'suppressed'        => false,
			'building_hp_items' => false,
			'seen_items'        => null,
			'seen_items_compacted' => false,
		] as $name => $value
	) {
		set_priv( $MENU, $name, $value );
	}

	foreach (
		[
			'default_items'   => null,
			'items'           => null,
			'items_selection' => null,
			'probing'         => false,
			'vendor'          => null,
			'native_items'    => [],
		] as $name => $value
	) {
		set_priv( $PERSIST, $name, $value );
	}
}

/**
 * Sorts menu items the way HivePress core sorts them after the filters, so an
 * assertion reads the order the site would actually render.
 *
 * @param array $items Menu items.
 * @return array Item names, top to bottom.
 */
function rendered_order( $items ) {
	return array_keys( HivePress\Helpers\sort_array( $items ) );
}

/**
 * Builds a base HivePress account menu.
 *
 * @param array $orders Item name mapped to its native "_order".
 * @return array
 */
function base_menu( $orders ) {
	$items = [];

	foreach ( $orders as $name => $order ) {
		$items[ $name ] = [
			'label'  => strtoupper( $name ),
			'url'    => 'http://example.test/' . $name,
			'_order' => $order,
		];
	}

	return $items;
}

echo '=== Account Menu Enhancer QA (WooCommerce ' . ( AMEHP_TEST_WC ? 'on' : 'ABSENT' ) .
	', Persistent Account Menu ' . ( AMEHP_TEST_PAM ? 'ACTIVE' : 'gone' ) .
	', Bookings ' . ( AMEHP_TEST_BOOKINGS ? 'on' : 'ABSENT' ) .
	', Memberships ' . ( AMEHP_TEST_MEMBERSHIPS ? 'on' : 'ABSENT' ) . ") ===\n";

/* ===================== A. reading the stored order ===================== */
echo "\n[A] get_menu_order / get_menu_order_positions\n";

amehp_test_reset();
ok( [] === call_priv( $MENU, 'get_menu_order' ), 'A1 absent option reads as no arrangement' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order'] = '';
ok( [] === call_priv( $MENU, 'get_menu_order' ), 'A2 empty option reads as no arrangement' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order'] = ' , ,  ';
ok( [] === call_priv( $MENU, 'get_menu_order' ), 'A3 a list of nothing but separators reads as no arrangement' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order'] = ' hp:listings_edit , wc:downloads ,amehp_item_ab12cd34 ';
ok(
	[ 'hp:listings_edit', 'wc:downloads', 'amehp_item_ab12cd34' ] === call_priv( $MENU, 'get_menu_order' ),
	'A4 the three key shapes survive, whitespace trimmed'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order'] = 'hp:ok,bad key!,<script>,wc:downloads';
ok(
	[ 'hp:ok', 'wc:downloads' ] === call_priv( $MENU, 'get_menu_order' ),
	'A5 a key that is not a key is dropped, the rest of the list still applies'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order'] = 'hp:a,hp:a,hp:b';
ok( [ 'hp:a', 'hp:b' ] === call_priv( $MENU, 'get_menu_order' ), 'A6 a repeated key is counted once' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order'] = 'wc:orders,wc:subscriptions,hp:listings_edit';
$positions                                  = call_priv( $MENU, 'get_menu_order_positions' );
ok(
	0 === $positions['orders'] && 0 === $positions['orders_view'],
	'A7 "Orders (WooCommerce)" places both names core gives that list'
);
ok(
	1 === $positions['subscriptions'] && 1 === $positions['subscriptions_view'],
	'A8 the same for Subscriptions'
);
ok( 2 === $positions['listings_edit'], 'A9 the hp: prefix is mapped off to the menu item name' );

/* ===================== B. applying the stored order ===================== */
echo "\n[B] apply_menu_order\n";

amehp_test_reset();
$items = base_menu(
	[
		'a' => 10,
		'b' => 20,
		'c' => 30,
	]
);
$GLOBALS['_options']['hp_amehp_menu_order'] = 'hp:c,hp:a,hp:b';
ok(
	[ 'c', 'a', 'b' ] === rendered_order( call_priv( $MENU, 'apply_menu_order', [ $items ] ) ),
	'B1 the stored arrangement is what the menu renders'
);

amehp_test_reset();
$items = base_menu(
	[
		'a' => 10,
		'b' => 20,
		'c' => 30,
		'd' => 5,
	]
);
$GLOBALS['_options']['hp_amehp_menu_order'] = 'hp:c,hp:a';
ok(
	[ 'c', 'a', 'd', 'b' ] === rendered_order( call_priv( $MENU, 'apply_menu_order', [ $items ] ) ),
	'B2 an item the owner never placed keeps its native order and follows the placed block'
);

amehp_test_reset();
$items = base_menu(
	[
		'a' => 10,
		'b' => 20,
		'c' => 30,
		'd' => 5,
	]
);
$GLOBALS['_options']['hp_amehp_menu_order'] = 'hp:gone_extension,hp:c,hp:a,wc:nothing';
ok(
	[ 'c', 'a', 'd', 'b' ] === rendered_order( call_priv( $MENU, 'apply_menu_order', [ $items ] ) ),
	'B3 a stale key for an item that no longer exists is ignored, not fatal, and does not shift the rest'
);

amehp_test_reset();
$items = base_menu(
	[
		'a' => 10,
		'b' => 20,
		'c' => 30,
		'd' => 5,
	]
);
ok(
	[ 'd', 'a', 'b', 'c' ] === rendered_order( call_priv( $MENU, 'apply_menu_order', [ $items ] ) ),
	'B4 no stored arrangement leaves the native ordering exactly as it was'
);

amehp_test_reset();
$items                                      = base_menu(
	[
		'a' => 10,
		'b' => 20,
		'c' => 30,
		'd' => 5,
	]
);
$GLOBALS['_options']['hp_amehp_menu_order'] = '';
$result                                     = call_priv( $MENU, 'apply_menu_order', [ $items ] );
ok( [ 'd', 'a', 'b', 'c' ] === rendered_order( $result ), 'B5 an empty stored arrangement changes nothing' );
ok( 5 === $result['d']['_order'], 'B6 and it does not rewrite the native "_order" values either' );

amehp_test_reset();
$items                                      = base_menu(
	[
		'a' => 10,
		'b' => 20,
	]
);
$items['not_an_array']                      = 'a string, as a third-party filter can leave behind';
$GLOBALS['_options']['hp_amehp_menu_order'] = 'hp:b,hp:a';
$result                                     = call_priv( $MENU, 'apply_menu_order', [ $items ] );
ok( 'a string, as a third-party filter can leave behind' === $result['not_an_array'], 'B7 a non-array item is left alone rather than crashed on' );

/* ===================== C. hiding ===================== */
echo "\n[C] hidden items\n";

amehp_test_reset();
$GLOBALS['_hp_menu_items']                   = base_menu(
	[
		'a' => 10,
		'b' => 20,
		'c' => 30,
	]
);
$GLOBALS['_options']['hp_amehp_hidden_items'] = [ 'hp:b' ];
$menu                                         = $MENU->alter_hp_menu( [ 'items' => $GLOBALS['_hp_menu_items'] ] );
ok( ! isset( $menu['items']['b'] ), 'C1 a hidden item is gone from the HivePress menu at the constructor stage' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_hidden_items'] = [ 'hp:b' ];
$items                                        = $MENU->alter_hp_menu_items(
	base_menu(
		[
			'a' => 10,
			'b' => 20,
			'c' => 30,
		]
	)
);
ok( ! isset( $items['b'] ), 'C2 and gone at the /items stage too, which is the one that catches late-registered items' );
ok( isset( $items['a'] ) && isset( $items['c'] ), 'C3 nothing else is removed with it' );

if ( AMEHP_TEST_WC ) {
	amehp_test_reset();
	$GLOBALS['_options']['hp_amehp_hidden_items'] = [ 'wc:orders' ];
	$menu                                         = $MENU->alter_hp_menu(
		[
			'items' => base_menu(
				[
					'orders_view' => 10,
					'a'           => 20,
				]
			),
		]
	);
	ok(
		! isset( $menu['items']['orders_view'] ),
		'C4 hiding "Orders (WooCommerce)" also removes the item HivePress core adds under its own name'
	);
}

/* ===================== D. the hidden item's slot ===================== */
echo "\n[D] a hidden item keeps its place in the stored arrangement\n";

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_menu_order']   = 'hp:a,hp:b,hp:c';
$GLOBALS['_options']['hp_amehp_hidden_items'] = [ 'hp:b' ];
$items                                        = $MENU->alter_hp_menu_items(
	base_menu(
		[
			'a' => 10,
			'b' => 20,
			'c' => 30,
		]
	)
);
ok( [ 'a', 'c' ] === rendered_order( $items ), 'D1 the two visible items render in their stored order' );
ok(
	'hp:a,hp:b,hp:c' === $GLOBALS['_options']['hp_amehp_menu_order'],
	'D2 rendering a reduced menu never rewrites the stored arrangement'
);

$GLOBALS['_options']['hp_amehp_hidden_items'] = [];
set_priv( $MENU, 'hp_items', null );
$items = $MENU->alter_hp_menu_items(
	base_menu(
		[
			'a' => 10,
			'b' => 20,
			'c' => 30,
		]
	)
);
ok( [ 'a', 'b', 'c' ] === rendered_order( $items ), 'D3 unhiding it puts it back in the middle, not at the bottom' );

if ( AMEHP_TEST_WC ) {

	// The same invariant for the other reason an item can be off screen: the
	// menus are not combined, so the WooCommerce rows are not in this menu.
	amehp_test_reset();
	$GLOBALS['_wc_menu_items']                    = [ 'downloads' => 'Downloads' ];
	$GLOBALS['_options']['hp_amehp_menu_order']   = 'hp:a,wc:downloads,hp:b';
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '';
	$menu                                         = $MENU->alter_hp_menu(
		[
			'items' => base_menu(
				[
					'a' => 10,
					'b' => 20,
				]
			),
		]
	);
	$items                                        = $MENU->alter_hp_menu_items( $menu['items'] );
	ok( [ 'a', 'b' ] === rendered_order( $items ), 'D4 with the menus separate the HivePress items keep their stored order' );

	amehp_test_reset();
	$GLOBALS['_wc_menu_items']                      = [ 'downloads' => 'Downloads' ];
	$GLOBALS['_options']['hp_amehp_menu_order']     = 'hp:a,wc:downloads,hp:b';
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	$menu                                           = $MENU->alter_hp_menu(
		[
			'items' => base_menu(
				[
					'a' => 10,
					'b' => 20,
				]
			),
		]
	);
	$items                                          = $MENU->alter_hp_menu_items( $menu['items'] );
	ok(
		[ 'a', 'downloads', 'b' ] === rendered_order( $items ),
		'D5 combining the menus drops the WooCommerce row back into the slot it was arranged into'
	);
}

/* ===================== E. the WooCommerce integration switch ===================== */
echo "\n[E] WooCommerce integration\n";

amehp_test_reset();
ok( true === call_priv( $MENU, 'is_wc_integration_enabled' ), 'E1 a fresh install has the integration on' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_wc_integration'] = '';
ok( false === call_priv( $MENU, 'is_wc_integration_enabled' ), 'E2 the switch off means off' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_merge_menus']  = '1';
$GLOBALS['_options']['hp_amehp_unify_account'] = '';
ok(
	true === call_priv( $MENU, 'is_wc_integration_enabled' ),
	'E3 before the migration has run, either legacy checkbox still counts as on'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_merge_menus']   = '';
$GLOBALS['_options']['hp_amehp_unify_account'] = '';
ok( false === call_priv( $MENU, 'is_wc_integration_enabled' ), 'E4 both legacy checkboxes off reads as off' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_wc_integration'] = '';
$GLOBALS['_options']['hp_amehp_merge_menus']    = '1';
ok(
	false === call_priv( $MENU, 'is_wc_integration_enabled' ),
	'E5 once the new switch exists the legacy pair is not consulted at all'
);

amehp_test_reset();
$GLOBALS['_wc_menu_items']                      = [ 'downloads' => 'Downloads' ];
$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
$menu                                           = $MENU->alter_hp_menu( [ 'items' => base_menu( [ 'a' => 10 ] ) ] );
ok(
	AMEHP_TEST_WC === isset( $menu['items']['downloads'] ),
	AMEHP_TEST_WC
		? 'E6 with the integration on the WooCommerce rows join the HivePress menu'
		: 'E6 with WooCommerce absent nothing is merged however the switch is set'
);

amehp_test_reset();
$GLOBALS['_wc_menu_items']                      = [ 'downloads' => 'Downloads' ];
$GLOBALS['_options']['hp_amehp_wc_integration'] = '';
$menu                                           = $MENU->alter_hp_menu( [ 'items' => base_menu( [ 'a' => 10 ] ) ] );
ok( ! isset( $menu['items']['downloads'] ), 'E7 with the integration off the two menus stay separate' );

if ( AMEHP_TEST_WC ) {
	amehp_test_reset();
	$GLOBALS['_wc_menu_items']                      = [
		'orders'          => 'Orders',
		'subscriptions'   => 'Subscriptions',
		'customer-logout' => 'Log out',
		'downloads'       => 'Downloads',
	];
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	$menu                                           = $MENU->alter_hp_menu( [ 'items' => base_menu( [ 'a' => 10 ] ) ] );
	ok(
		! isset( $menu['items']['orders'] ) && ! isset( $menu['items']['subscriptions'] ) && ! isset( $menu['items']['customer-logout'] ),
		'E8 the endpoints HivePress core already links, and the duplicate sign-out, are not merged again'
	);
	ok( isset( $menu['items']['downloads'] ), 'E9 the rest are' );

	amehp_test_reset();
	$GLOBALS['_wc_menu_items']                      = [ 'downloads' => 'Downloads' ];
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	$menu                                           = $MENU->alter_hp_menu(
		[
			'items' => [
				'existing' => [
					'label' => 'Existing',
					'url'   => 'http://example.test/my-account/downloads/',
				],
			],
		]
	);
	ok( ! isset( $menu['items']['downloads'] ), 'E10 an endpoint already in the menu under another name is not added twice' );

	/*
	 * The account LAYOUT follows the same one switch as the menu merge.
	 *
	 * Version 3.0.0 replaced the separate "Menu Merging" and "WooCommerce
	 * Integration" checkboxes with a single one, and the two accessors named
	 * after the old pair survived as aliases of the new one for two more
	 * releases. These check the outcome rather than the accessor, so the
	 * aliases can go without anything here needing a word changed: if the
	 * layout path ever stops following the same switch as the merge, E11 and
	 * E12 disagree with E6 and E7.
	 */
	$amehp_wc_template = 'wc/myaccount/my-account.php';
	$amehp_hp_template = hivepress()->get_path() . '/templates/woocommerce/myaccount/my-account.php';

	amehp_test_reset();
	$GLOBALS['_current_user_id']                    = 7;
	$GLOBALS['_is_account_page']                    = true;
	$GLOBALS['_wc_endpoint']                        = 'edit-account';
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	ok(
		$amehp_hp_template === $MENU->set_account_template( $amehp_wc_template, 'myaccount/my-account.php' ),
		'E11 with the integration on a WooCommerce account page renders in the HivePress layout'
	);

	amehp_test_reset();
	$GLOBALS['_current_user_id']                    = 7;
	$GLOBALS['_is_account_page']                    = true;
	$GLOBALS['_wc_endpoint']                        = 'edit-account';
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '';
	ok(
		$amehp_wc_template === $MENU->set_account_template( $amehp_wc_template, 'myaccount/my-account.php' ),
		'E12 with it off the page keeps WooCommerce\'s own template'
	);

	// The legacy pair still steers the layout in the window before the
	// migration runs, exactly as it steers the merge in E3.
	amehp_test_reset();
	$GLOBALS['_current_user_id']                   = 7;
	$GLOBALS['_is_account_page']                   = true;
	$GLOBALS['_wc_endpoint']                       = 'edit-account';
	$GLOBALS['_options']['hp_amehp_merge_menus']   = '1';
	$GLOBALS['_options']['hp_amehp_unify_account'] = '';
	ok(
		$amehp_hp_template === $MENU->set_account_template( $amehp_wc_template, 'myaccount/my-account.php' ),
		'E13 and before the migration either legacy checkbox still switches the layout on'
	);
}

/* ===================== F. the WooCommerce menu ===================== */
if ( AMEHP_TEST_WC ) {
	echo "\n[F] the WooCommerce account menu\n";

	amehp_test_reset();
	$GLOBALS['_hp_menu_items']                      = base_menu(
		[
			'listings_edit' => 10,
			'user_logout'   => 900,
		]
	);
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	$rows                                           = $MENU->alter_wc_menu(
		[
			'downloads'       => 'Downloads',
			'customer-logout' => 'Log out',
		]
	);
	ok( isset( $rows['listings_edit'] ), 'F1 with the integration on the HivePress items join the WooCommerce menu' );
	ok( ! isset( $rows['user_logout'] ), 'F2 and the HivePress sign-out is dropped as a duplicate of the WooCommerce one' );
	ok( 'customer-logout' === array_key_last( $rows ), 'F3 sign-out is pinned to the bottom' );

	amehp_test_reset();
	$GLOBALS['_hp_menu_items']                      = base_menu( [ 'listings_edit' => 10 ] );
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '';
	$rows                                           = $MENU->alter_wc_menu( [ 'downloads' => 'Downloads' ] );
	ok(
		[ 'downloads' ] === array_keys( $rows ),
		'F4 with the integration off the WooCommerce menu is left as WooCommerce built it'
	);

	amehp_test_reset();
	$GLOBALS['_hp_menu_items']                      = base_menu( [ 'listings_edit' => 10 ] );
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	$GLOBALS['_options']['hp_amehp_hidden_items']   = [ 'wc:downloads', 'hp:listings_edit' ];
	$rows                                           = $MENU->alter_wc_menu(
		[
			'downloads'  => 'Downloads',
			'edit-address' => 'Addresses',
		]
	);
	ok( ! isset( $rows['downloads'] ), 'F5 a hidden WooCommerce endpoint is gone from the WooCommerce menu' );
	ok( ! isset( $rows['listings_edit'] ), 'F6 a hidden HivePress item is not merged into it either' );

	amehp_test_reset();
	$GLOBALS['_hp_menu_items']                      = base_menu( [ 'listings_edit' => 10 ] );
	$GLOBALS['_options']['hp_amehp_wc_integration'] = '1';
	$GLOBALS['_options']['hp_amehp_menu_order']     = 'hp:listings_edit,wc:downloads';
	$rows                                           = $MENU->alter_wc_menu( [ 'downloads' => 'Downloads' ] );
	ok(
		[ 'listings_edit', 'downloads' ] === array_keys( $rows ),
		'F7 the owner\'s arrangement is applied to the WooCommerce menu as well'
	);
}

/* ===================== G. custom items ===================== */
echo "\n[G] custom menu items\n";

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'With an id',
		'url'   => '/help',
		'uid'   => 'ab12cd34ef56',
	],
	[
		'label' => 'Without one',
		'url'   => '/about',
	],
];
$custom                                       = call_priv( $MENU, 'get_custom_items' );
ok( isset( $custom['amehp_item_ab12cd34ef56'] ), 'G1 a row with a stable id is keyed by it' );
ok( isset( $custom['amehp_item_2'] ), 'G2 a row without one falls back to its position, exactly as before 3.3.0' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[ 'label' => '' ],
	[
		'label' => 'Second row',
		'url'   => '/second',
	],
];
$custom                                       = call_priv( $MENU, 'get_custom_items' );
ok( [ 'amehp_item_2' ] === array_keys( $custom ), 'G3 a row with no label is skipped but still counted, so the positional keys do not shift' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'Relative',
		'url'   => '/help',
	],
	[
		'label' => 'Absolute',
		'url'   => 'https://example.org/x',
	],
	[
		'label' => 'Dangerous',
		'url'   => 'javascript:alert(1)',
	],
	[
		'label' => 'Missing page',
		'link'  => 'page:404',
	],
];
$custom                                       = call_priv( $MENU, 'get_custom_items' );
ok( 'http://example.test/help' === call_priv( $MENU, 'get_custom_item_url', [ $custom['amehp_item_1'] ] ), 'G4 a relative path is resolved against the site' );
ok( 'https://example.org/x' === call_priv( $MENU, 'get_custom_item_url', [ $custom['amehp_item_2'] ] ), 'G5 an absolute web URL is used as typed' );
ok( '' === call_priv( $MENU, 'get_custom_item_url', [ $custom['amehp_item_3'] ] ), 'G6 a non-web scheme resolves to nothing, so the item is never rendered' );
ok( '' === call_priv( $MENU, 'get_custom_item_url', [ $custom['amehp_item_4'] ] ), 'G7 a link to a page that is gone resolves to nothing' );

amehp_test_reset();
$GLOBALS['_current_user_id']                  = 5;
$GLOBALS['_user_roles']                       = [ 'subscriber' ];
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'Vendors only',
		'url'   => '/v',
		'roles' => [ 'hp_vendor' ],
	],
	[
		'label' => 'Everyone',
		'url'   => '/e',
	],
];
$menu                                         = $MENU->alter_hp_menu( [ 'items' => [] ] );
ok( ! isset( $menu['items']['amehp_item_1'] ), 'G8 a role-restricted item is hidden from a user without the role' );
ok( isset( $menu['items']['amehp_item_2'] ), 'G9 an unrestricted item is shown to everybody' );

$GLOBALS['_user_roles'] = [ 'administrator' ];
$menu                   = $MENU->alter_hp_menu( [ 'items' => [] ] );
ok( isset( $menu['items']['amehp_item_1'] ), 'G10 an administrator always sees it' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'HivePress only',
		'url'   => '/h',
		'menus' => 'hivepress',
	],
	[
		'label' => 'WooCommerce only',
		'url'   => '/w',
		'menus' => 'woocommerce',
	],
];
$menu                                         = $MENU->alter_hp_menu( [ 'items' => [] ] );
ok(
	isset( $menu['items']['amehp_item_1'] ) && ! isset( $menu['items']['amehp_item_2'] ),
	'G11 an item assigned to one menu only appears in that menu'
);

/* ===================== H. icon CSS ===================== */
echo "\n[H] icon emission\n";

amehp_test_reset();
ok( '' === $MENU->get_stroke_width( '' ), 'H1 the normal weight emits no stroke at all' );
ok( '0.5px' === $MENU->get_stroke_width( 'semibold' ), 'H2 semibold is a half pixel stroke' );
ok( '1px' === $MENU->get_stroke_width( 'bold' ), 'H3 bold is a one pixel stroke' );
ok( '' === $MENU->get_stroke_width( 'heavy' ), 'H4 an unknown weight falls back to normal rather than guessing' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item' => 'hp:listings_edit',
		'icon' => 'heart',
	],
];
$css                                   = call_priv( $MENU, 'get_icon_css' );
ok( false !== strpos( $css, '.hp-menu--user-account .hp-menu__item--listings-edit > a::before' ), 'H5 the selector matches how HivePress builds its item classes' );
ok( false !== strpos( $css, 'content:"\\f004";' ), 'H6 the codepoint comes from the shipped codes config' );
ok( false !== strpos( $css, '"Font Awesome 5 Free"' ) && false === strpos( $css, 'Brands' ), 'H7 a solid icon emits the solid family and no brands rule' );
ok( false === get_priv( $MENU, 'needs_fontawesome' ), 'H8 a bundled solid icon does not pull in the full library' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item' => 'hp:listings_edit',
		'icon' => 'github',
	],
];
$css                                   = call_priv( $MENU, 'get_icon_css' );
ok( false !== strpos( $css, 'content:"\\f09b";' ), 'H9 a brand icon emits its own codepoint' );
ok( false !== strpos( $css, '"Font Awesome 7 Brands","Font Awesome 6 Brands","Font Awesome 5 Brands"' ), 'H10 and the brands family, which is a different font from the solid one' );
ok( false !== strpos( $css, 'font-weight:400' ), 'H11 at the brands weight rather than the solid 900' );
ok( true === get_priv( $MENU, 'needs_fontawesome' ), 'H12 a brand icon flags that the full library is needed' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item' => 'hp:listings_edit',
		'icon' => 'not-a-real-icon',
	],
];
ok( '' === call_priv( $MENU, 'get_icon_css' ), 'H13 an icon name that is in no font is rejected rather than emitted as an empty box' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item'   => 'hp:listings_edit',
		'icon'   => 'heart',
		'weight' => 'bold',
		'colour' => '#ff0000',
	],
];
$css                                   = call_priv( $MENU, 'get_icon_css' );
ok( false !== strpos( $css, '-webkit-text-stroke:1px currentColor' ), 'H14 a per-item bold weight emits the one pixel stroke' );
ok( false !== strpos( $css, '--amehp-icon-colour:#ff0000' ), 'H15 the item colour is emitted as the icon custom property' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item'   => 'hp:listings_edit',
		'icon'   => 'heart',
		'colour' => 'red',
	],
];
$css                                   = call_priv( $MENU, 'get_icon_css' );
ok( false === strpos( $css, 'amehp-icon-colour:red' ), 'H16 a colour that is not a hex value is dropped, never written into the stylesheet' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icon_weight'] = 'semibold';
$css                                         = call_priv( $MENU, 'get_appearance_css' );
ok( false !== strpos( $css, '-webkit-text-stroke:0.5px currentColor' ), 'H17 the global icon weight maps to the same stroke width' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_icon_weight'] = 'normal';
$css                                         = call_priv( $MENU, 'get_appearance_css' );
ok( false === strpos( $css, '-webkit-text-stroke' ), 'H18 the normal global weight emits no stroke rule' );

amehp_test_reset();
$GLOBALS['_current_user_id']           = 5;
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item' => 'hp:listings_edit',
		'icon' => 'github',
	],
];
$MENU->enqueue_frontend_assets();
ok( in_array( 'freestylr-fontawesome', $GLOBALS['_styles_enq'], true ), 'H19 a brand icon loads the full Font Awesome library' );

amehp_test_reset();
$GLOBALS['_current_user_id']           = 5;
$GLOBALS['_options']['hp_amehp_icons'] = [
	[
		'item' => 'hp:listings_edit',
		'icon' => 'heart',
	],
];
$MENU->enqueue_frontend_assets();
ok( ! in_array( 'freestylr-fontawesome', $GLOBALS['_styles_enq'], true ), 'H20 a bundled solid icon does not' );

amehp_test_reset();
$GLOBALS['_styles_reg']['freestylr-fontawesome'] = 'a sibling plugin got there first';
$MENU->enqueue_fontawesome();
ok(
	'a sibling plugin got there first' === $GLOBALS['_styles_reg']['freestylr-fontawesome']
		&& in_array( 'freestylr-fontawesome', $GLOBALS['_styles_enq'], true ),
	'H21 the shared handle is enqueued, not re-registered, when a sibling already claimed it'
);

/* ===================== I. placeholder pages ===================== */
echo "\n[I] placeholder pages\n";

amehp_test_reset();
$items  = call_priv( $PERSIST, 'get_items' );
$notice = $items['listings_edit']['notice'];
ok( 'f03a' === $notice['icon'], 'I1 with nothing set the page keeps its built-in icon' );
ok( ! isset( $notice['icon_name'] ), 'I2 and no chosen icon is recorded' );
ok( 0 === strpos( $notice['text'], "You haven't added any listings yet" ), 'I3 and its built-in wording' );
ok( 'Add listing' === $notice['button']['label'] && 'listing_submit_page' === $notice['button']['route'], 'I4 and its built-in button' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_page_icon_listings_edit']    = 'github';
$GLOBALS['_options']['hp_amehp_page_text_listings_edit']    = 'Nothing here yet.';
$GLOBALS['_options']['hp_amehp_button_label_listings_edit'] = 'Get started';
$GLOBALS['_options']['hp_amehp_button_url_listings_edit']   = 'https://example.org/start';
$items                                                      = call_priv( $PERSIST, 'get_items' );
$notice                                                     = $items['listings_edit']['notice'];
ok( 'github' === $notice['icon_name'], 'I5 a chosen icon is carried by name alongside the built-in codepoint' );
ok( 'Nothing here yet.' === $notice['text'], 'I6 chosen wording replaces the default' );
ok( 'Get started' === $notice['button']['label'], 'I7 a chosen button label replaces the default' );
ok( 'https://example.org/start' === $notice['button']['url'] && ! isset( $notice['button']['route'] ), 'I8 a chosen button URL replaces the default route outright' );

$rendered = call_priv( $PERSIST, 'render_notice', [ $notice ] );
ok( false !== strpos( $rendered, 'amehp-empty__icon--brand' ), 'I9 the rendered notice marks a brand icon so the right font is used' );
ok( false !== strpos( $rendered, 'data-icon="&#xf09b;"' ), 'I10 with the brand codepoint, not the page default' );
ok( false !== strpos( $rendered, 'https://example.org/start' ), 'I11 and the chosen button URL' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_page_text_listings_edit']    = '   ';
$GLOBALS['_options']['hp_amehp_button_label_listings_edit'] = '';
$GLOBALS['_options']['hp_amehp_button_url_listings_edit']   = '';
$items                                                      = call_priv( $PERSIST, 'get_items' );
$notice                                                     = $items['listings_edit']['notice'];
ok( 0 === strpos( $notice['text'], "You haven't added any listings yet" ), 'I12 a blank page text means "keep what this page shows now", never a blank page' );
ok( 'Add listing' === $notice['button']['label'], 'I13 a blank button label keeps the stock label' );
ok( 'listing_submit_page' === $notice['button']['route'] && ! isset( $notice['button']['url'] ), 'I14 a blank button URL keeps the stock link' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_page_icon_listings_edit'] = 'Bad Icon!';
$items                                                   = call_priv( $PERSIST, 'get_items' );
ok( ! isset( $items['listings_edit']['notice']['icon_name'] ), 'I15 an icon name that is not an icon name is refused before it reaches the page' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_page_icon_listings_edit'] = 'not-a-real-icon';
$items                                                   = call_priv( $PERSIST, 'get_items' );
$icon                                                    = call_priv( $PERSIST, 'get_notice_icon', [ $items['listings_edit']['notice'] ] );
ok( 'f03a' === $icon['code'] && false === $icon['brand'], 'I16 a well formed name for an icon that does not exist falls back to the page default' );

amehp_test_reset();
$GLOBALS['_options']['hp_hppam_button_label_listings_edit'] = 'From the old plugin';
$items                                                      = call_priv( $PERSIST, 'get_items' );
ok(
	'From the old plugin' === $items['listings_edit']['notice']['button']['label'],
	'I17 a value saved under the old Persistent Account Menu key is honoured before the migration runs'
);

amehp_test_reset();
$GLOBALS['_options']['hp_hppam_button_label_listings_edit'] = 'From the old plugin';
$GLOBALS['_options']['hp_amehp_button_label_listings_edit'] = 'From the new one';
$items                                                      = call_priv( $PERSIST, 'get_items' );
ok(
	'From the new one' === $items['listings_edit']['notice']['button']['label'],
	'I18 and the new key wins once it exists'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_persistent_items'] = [ 'listings_edit' ];
$items                                            = call_priv( $PERSIST, 'get_items' );
ok( [ 'listings_edit' ] === array_keys( $items ), 'I19 an unticked page is left entirely alone, keeping the stock behaviour' );

/* ===================== J. items forced into the menu ===================== */
echo "\n[J] forcing the managed items\n";

amehp_test_reset();
$GLOBALS['_current_user_id'] = 5;
$menu                        = $PERSIST->alter_account_menu( [ 'items' => [] ] );
ok( isset( $menu['items']['listings_edit'] ), 'J1 a managed item with a live route is forced into the menu' );
ok(
	AMEHP_TEST_BOOKINGS === isset( $menu['items']['bookings_view'] ),
	AMEHP_TEST_BOOKINGS
		? 'J2 Bookings is forced in where the extension is installed'
		: 'J2 Bookings is not forced in where the extension is absent'
);
ok(
	AMEHP_TEST_MEMBERSHIPS === isset( $menu['items']['memberships_view'] ),
	AMEHP_TEST_MEMBERSHIPS
		? 'J3 Memberships is forced in where the extension is installed'
		: 'J3 Memberships is not forced in where the extension is absent'
);
ok( ! isset( $menu['items']['orders_edit'] ), 'J4 a vendor-only item is not forced on somebody who is not a vendor' );

amehp_test_reset();
$GLOBALS['_current_user_id'] = 5;
$GLOBALS['_vendor_first_id'] = 42;
$menu                        = $PERSIST->alter_account_menu( [ 'items' => [] ] );
ok( isset( $menu['items']['orders_edit'] ), 'J5 and is forced on somebody who is' );

amehp_test_reset();
$GLOBALS['_current_user_id'] = 0;
$menu                        = $PERSIST->alter_account_menu( [ 'items' => [] ] );
ok( [] === $menu['items'], 'J6 nothing is forced for a signed-out visitor' );

amehp_test_reset();
$GLOBALS['_current_user_id'] = 5;
$menu                        = $PERSIST->alter_account_menu(
	[
		'items' => [
			'listings_edit' => [
				'route'  => 'listings_edit_page',
				'_order' => 999,
			],
		],
	]
);
ok( 999 === $menu['items']['listings_edit']['_order'], 'J7 an item the site added natively is left exactly as it was' );

amehp_test_reset();
$options = $PERSIST->get_item_options();
ok(
	AMEHP_TEST_BOOKINGS === isset( $options['bookings_view'] ),
	'J8 the settings screen offers only the pages whose extension is installed'
);

/* ===================== K. standing the old plugin down ===================== */
echo "\n[K] Persistent Account Menu takeover\n";

amehp_test_reset();
amehp_take_over_persistent_menu();
$removed = array_map(
	function ( $entry ) {
		return $entry[0];
	},
	$GLOBALS['_removed']
);
ok(
	AMEHP_TEST_PAM === in_array( 'hivepress/v1/menus/user_account', $removed, true ),
	AMEHP_TEST_PAM
		? 'K1 the old plugin\'s feature hooks are lifted while it is still active'
		: 'K1 nothing is unhooked when the old plugin is not installed'
);
ok(
	AMEHP_TEST_PAM === in_array( 'admin_init', $removed, true ),
	'K2 including its own reconciliation, so it cannot write the shared options'
);

/* ===================== L. custom item identity in the stored order ===================== */
echo "\n[L] custom items are ordered by their id, not by their row position\n";

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'Custom',
		'url'   => '/c',
		'uid'   => 'ab12cd34ef56',
	],
];
$GLOBALS['_options']['hp_amehp_menu_order']   = 'amehp_item_ab12cd34ef56,hp:a';
$menu                                         = $MENU->alter_hp_menu( [ 'items' => base_menu( [ 'a' => 10 ] ) ] );
$items                                        = $MENU->alter_hp_menu_items( $menu['items'] );
ok(
	[ 'amehp_item_ab12cd34ef56', 'a' ] === rendered_order( $items ),
	'L1 an arrangement naming the item by its stable id moves it'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'Custom',
		'url'   => '/c',
		'uid'   => 'ab12cd34ef56',
	],
];

// The key that identified this row before 3.3.0. The row now answers to its
// uid, so a stored order still naming it positionally must simply not match -
// leaving the item unplaced at the end - rather than moving whatever row
// happens to sit in position one today.
$GLOBALS['_options']['hp_amehp_menu_order'] = 'amehp_item_1,hp:a';
$menu                                       = $MENU->alter_hp_menu( [ 'items' => base_menu( [ 'a' => 10 ] ) ] );
$items                                      = $MENU->alter_hp_menu_items( $menu['items'] );
ok(
	[ 'a', 'amehp_item_ab12cd34ef56' ] === rendered_order( $items ),
	'L2 a stale positional key is ignored, never matched against the row that now sits in that position'
);

/* ===================== M. junk in the stored options ===================== */
echo "\n[M] a stored option that the settings screen could not have written\n";

amehp_test_reset();

// An option can be written by WP-CLI, a migration, another plugin or a restored
// database, none of which go through the settings field. Every caller of
// get_hidden_keys() hands these straight to strpos(), so a non-string here is a
// PHP 8 TypeError on a filter that runs for every account menu - which takes
// the whole front end down rather than getting one item wrong.
$GLOBALS['_options']['hp_amehp_hidden_items'] = [ 'hp:b', [ 'nested' ], 42, null ];

try {
	$items  = $MENU->alter_hp_menu_items(
		base_menu(
			[
				'a' => 10,
				'b' => 20,
			]
		)
	);
	$fatal  = false;
	$hidden = ! isset( $items['b'] ) && isset( $items['a'] );
} catch ( \Throwable $throwable ) {
	$fatal  = true;
	$hidden = false;
}

ok( ! $fatal, 'M1 a non-string in the hidden list does not take the account menu down' );
ok( $hidden, 'M2 and the real keys in the same list still hide their items' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_hidden_items'] = 'not an array at all';
ok( [] === call_priv( $MENU, 'get_hidden_keys' ), 'M3 a hidden list that is not a list reads as nothing hidden' );

/* ===================== N. URLs keep their percent-encoding ===================== */
echo "\n[N] percent-encoded URLs\n";

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'Encoded',
		'url'   => 'https://example.org/a%20b?x=1&y=2',
	],
	[
		'label' => 'Encoded path',
		'url'   => '/a%20b',
	],
];
$custom                                       = call_priv( $MENU, 'get_custom_items' );
ok(
	'https://example.org/a%20b?x=1&y=2' === call_priv( $MENU, 'get_custom_item_url', [ $custom['amehp_item_1'] ] ),
	'N1 a percent-encoded custom item URL survives intact'
);
ok(
	'http://example.test/a%20b' === call_priv( $MENU, 'get_custom_item_url', [ $custom['amehp_item_2'] ] ),
	'N2 so does a percent-encoded relative path'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_button_url_listings_edit'] = 'https://example.org/a%20b?x=1';
$items                                                    = call_priv( $PERSIST, 'get_items' );
$rendered                                                 = call_priv( $PERSIST, 'render_notice', [ $items['listings_edit']['notice'] ] );
ok(
	false !== strpos( $rendered, 'https://example.org/a%20b?x=1' ),
	'N3 a percent-encoded placeholder button URL reaches the page intact'
);

// The field types are what decide this on the way IN. sanitize_text_field()
// strips percent-encoded octets, so "/a%20b" would be stored as "/ab" with
// nothing to say why; the URL field sanitises with esc_url_raw() and keeps them.
amehp_test_reset();
$settings = $PERSIST->alter_settings(
	[
		'account_menu' => [
			'sections' => [],
		],
	]
);
$button   = $settings['account_menu']['sections']['persistent_buttons']['fields']['amehp_button_url_listings_edit'];
ok( 'url' === $button['type'], 'N4 the Button URL setting is a URL field, not a text field' );

$config = require AMEHP_TEST_PLUGIN_DIR . '/includes/configs/settings.php';
ok(
	'url' === $config['account_menu']['sections']['items']['fields']['amehp_custom_items']['fields']['url']['type'],
	'N5 the custom item URL setting is a URL field too'
);

/* ===================== O2. reconciling a newly offered page ===================== */
echo "\n[O2] reconcile_items\n";

amehp_test_reset();
$GLOBALS['_option_writes'] = [];
$PERSIST->reconcile_items();
ok( [] === $GLOBALS['_option_writes'], 'O2.1 before the setting has ever been saved there is nothing to reconcile and nothing is written' );

amehp_test_reset();
$offered                                          = array_keys( $PERSIST->get_item_options() );
$GLOBALS['_options']['hp_amehp_persistent_items'] = [ 'listings_edit' ];
$PERSIST->reconcile_items();
ok( $offered === get_option( 'hp_amehp_persistent_known_items' ), 'O2.2 the first run records every choice already on offer' );
ok(
	[ 'listings_edit' ] === get_option( 'hp_amehp_persistent_items' ),
	'O2.3 and switches nothing on, so a page the owner unticked stays unticked'
);

$before = $GLOBALS['_options'];
$PERSIST->reconcile_items();
ok( $before === $GLOBALS['_options'], 'O2.4 with nothing new on offer a second run changes nothing' );

amehp_test_reset();
$offered                                                = array_keys( $PERSIST->get_item_options() );
$GLOBALS['_options']['hp_amehp_persistent_items']       = [ 'listings_edit' ];
$GLOBALS['_options']['hp_amehp_persistent_known_items'] = [ 'listings_edit' ];
$PERSIST->reconcile_items();
$enabled                                                = get_option( 'hp_amehp_persistent_items' );
ok( in_array( 'listings_edit', $enabled, true ), 'O2.5 a page that was already ticked stays ticked' );
ok(
	count( $enabled ) === count( $offered ),
	'O2.6 and a page that has only just been offered, because an extension was activated, is switched on rather than reading as deliberately off'
);

/*
 * The managed list has to follow reconcile_items() WITHIN the same request.
 *
 * get_items() caches its build, because it is called up to sixteen times on
 * one page view and each build makes about sixty-six option reads. The stored
 * selection is the one input that can move underneath that cache, because
 * reconcile_items() writes it on admin_init while the routes are still being
 * built, so the cache is keyed on it. These two checks are what fails if a
 * later change caches the selection as well.
 */
amehp_test_reset();
$GLOBALS['_options']['hp_amehp_persistent_items']       = [ 'listings_edit' ];
$GLOBALS['_options']['hp_amehp_persistent_known_items'] = [ 'listings_edit' ];
$before                                                 = array_keys( call_priv( $PERSIST, 'get_items' ) );
$PERSIST->reconcile_items();
$after                                                  = array_keys( call_priv( $PERSIST, 'get_items' ) );
ok( [ 'listings_edit' ] === $before, 'O2.7 before reconciling, only the ticked page is managed' );
ok( count( $after ) > count( $before ), 'O2.8 and a page reconcile_items() switches on is managed for the rest of the same request' );

// The developer filter is applied on every call, never cached with the build:
// a callback is entitled to answer differently for the page being rendered
// than for the one being probed.
amehp_test_reset();
$GLOBALS['_filter_runs'] = 0;
add_filter(
	'amehp/persistent_items',
	function ( $items ) {
		++$GLOBALS['_filter_runs'];

		unset( $items['listings_edit'] );

		return $items;
	}
);
$first  = call_priv( $PERSIST, 'get_items' );
$second = call_priv( $PERSIST, 'get_items' );
ok( 2 === $GLOBALS['_filter_runs'], 'O2.9 the amehp/persistent_items filter runs on every call, not once per request' );
ok( ! isset( $first['listings_edit'] ) && ! isset( $second['listings_edit'] ), 'O2.10 and what it removes stays removed on the second call' );
ok( isset( call_priv( $PERSIST, 'get_default_items' )['listings_edit'] ), 'O2.11 while what the filter returned was not stored back over the build' );

/* ===================== P. the record of items this site renders ===================== */
echo "\n[P] record_seen_items / get_seen_items\n";

/*
 * WHY THIS SECTION EXISTS.
 *
 * hp_amehp_seen_items is written from a filter that runs on every account menu
 * build, for every signed-in visitor, and read back on the same path. Both
 * halves are silent when wrong: a missed record means an item the owner cannot
 * hide or place, and a write on every page view means an autoloaded option
 * rewritten by every visitor. The checks below pin the write-only-when-changed
 * rule and the read-time cleaning, so a caching or hoisting change to either
 * has something to fail against.
 */

amehp_test_reset();
$GLOBALS['_option_writes'] = [];
$MENU->alter_hp_menu_items(
	base_menu(
		[
			'a' => 10,
			'b' => 20,
		]
	)
);
ok( in_array( 'hp_amehp_seen_items', $GLOBALS['_option_writes'], true ), 'P1 a menu item nobody has recorded before is written down' );

$seen = call_priv( $MENU, 'get_seen_items' );
ok( isset( $seen['a'], $seen['b'] ), 'P2 and both items come back on the next read, in the same request' );
ok( 10 === $seen['a']['order'] && 20 === $seen['b']['order'], 'P3 with the position the site rendered them in' );

// A settled site does no writes at all: the same menu again changes nothing.
$GLOBALS['_option_writes'] = [];
$MENU->alter_hp_menu_items(
	base_menu(
		[
			'a' => 10,
			'b' => 20,
		]
	)
);
ok( [] === $GLOBALS['_option_writes'], 'P4 building the same menu again writes nothing' );

// A position that has genuinely moved is re-recorded, so the live preview does
// not go on showing an order the site stopped rendering.
$GLOBALS['_option_writes'] = [];
$MENU->alter_hp_menu_items(
	base_menu(
		[
			'a' => 10,
			'b' => 90,
		]
	)
);
ok( in_array( 'hp_amehp_seen_items', $GLOBALS['_option_writes'], true ), 'P5 a position that has changed is written again' );
ok( 90 === call_priv( $MENU, 'get_seen_items' )['b']['order'], 'P6 and the new position is what reads back' );

// Deactivating an extension takes its item off the settings screen.
amehp_test_reset();
$GLOBALS['_options']['hp_amehp_seen_items'] = [
	'gone'  => [
		'label' => 'Gone',
		'route' => 'a_route_no_extension_registers',
		'order' => 10,
	],
	'stays' => [
		'label' => 'Stays',
		'route' => 'listings_edit_page',
		'order' => 20,
	],
];
$seen                                       = call_priv( $MENU, 'get_seen_items' );
ok( ! isset( $seen['gone'] ), 'P7 an item whose route no longer resolves is dropped on read' );
ok( isset( $seen['stays'] ), 'P8 and one whose route still resolves is kept' );

/*
 * A record with NO route survives, and that is the whole trap in the pruning
 * rule: the WooCommerce endpoints merged into the account menu have no
 * HivePress route, so "drop everything route-less" would take Downloads,
 * Addresses, Account details, Orders and Subscriptions off the settings screen.
 *
 * WHAT P9 ASSERTED UNTIL 3.3.9. Only this line, and the comment above it went
 * on to say that a custom item's record outliving the item was the accepted
 * cost of keeping route-less records - custom items being route-less too, and
 * the route test only firing inside `if ( $route && ... )`. That cost turned
 * out to be unbounded: every custom item ever created left a record nothing
 * could remove, and on the development install the option had reached 3,384
 * bytes, sixth-largest of the autoloaded options, holding three dead records
 * against one live item. 3.3.9 narrows the prune to this plugin's own
 * "amehp_item_" keys and asks get_custom_items() whether the item still
 * exists, so P9's own assertion is unchanged and P9a to P9c below pin the part
 * that did change.
 */
amehp_test_reset();
$GLOBALS['_options']['hp_amehp_seen_items'] = [
	'downloads' => [
		'label' => 'Downloads',
		'route' => '',
		'order' => 63,
	],
];
ok( isset( call_priv( $MENU, 'get_seen_items' )['downloads'] ), 'P9 a record with no route at all is kept, because the merged WooCommerce rows have none' );

/*
 * The narrowed prune, with all three kinds of route-less record present at
 * once: a WooCommerce row, a live custom item, and two dead custom items - one
 * whose id belongs to no row any more, and one under the positional name the
 * 3.3.0 id migration superseded. Both dead shapes were found side by side on
 * the development install, the second of them naming the SAME item as the
 * live record next to it.
 */
amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'uid'   => '6b7aca0b5757',
		'label' => 'Help',
	],
];

/*
 * The one WooCommerce row is stored exactly as record_seen_items() would have
 * written it from base_menu( [ 'downloads' => 63 ] ), so the menu built below
 * introduces nothing new and the prune is the ONLY thing that can trigger a
 * write. Recorded against a base_menu() label, which is the item name in
 * capitals.
 */
$GLOBALS['_options']['hp_amehp_seen_items'] = [
	'downloads'               => [
		'label' => 'DOWNLOADS',
		'route' => '',
		'order' => 63,
	],
	'amehp_item_6b7aca0b5757' => [
		'label' => 'Help',
		'route' => '',
		'order' => 100,
	],
	'amehp_item_qmlo3qhjsjaf' => [
		'label' => 'Deleted item',
		'route' => '',
		'order' => 101,
	],
	'amehp_item_1'            => [
		'label' => 'Help',
		'route' => '',
		'order' => 100,
	],
];

$seen = call_priv( $MENU, 'get_seen_items' );
ok( isset( $seen['downloads'] ), 'P9a the WooCommerce row is untouched by the custom item prune' );
ok( isset( $seen['amehp_item_6b7aca0b5757'] ), 'P9b a custom item that still exists keeps its record' );
ok(
	! isset( $seen['amehp_item_qmlo3qhjsjaf'] ) && ! isset( $seen['amehp_item_1'] ),
	'P9c a record for a deleted custom item, and a positional name the id migration superseded, are both dropped'
);

// The shrunken list has to reach the database, or a settled site stores the
// dead records for ever: nothing else changes, so nothing else triggers a write.
$GLOBALS['_option_writes'] = [];
$MENU->alter_hp_menu_items( base_menu( [ 'downloads' => 63 ] ) );
ok( in_array( 'hp_amehp_seen_items', $GLOBALS['_option_writes'], true ), 'P9d and the pruned list is written back rather than waiting for an unrelated change' );

$stored = $GLOBALS['_options']['hp_amehp_seen_items'];
ok(
	! isset( $stored['amehp_item_qmlo3qhjsjaf'] ) && ! isset( $stored['amehp_item_1'] ),
	'P9e so the stored option loses the dead records'
);
ok(
	isset( $stored['downloads'], $stored['amehp_item_6b7aca0b5757'] ),
	'P9f and keeps the WooCommerce row and the live custom item'
);

// Safe to run again: there is nothing left to prune, so the next build writes
// nothing at all.
$GLOBALS['_option_writes'] = [];
$MENU->alter_hp_menu_items( base_menu( [ 'downloads' => 63 ] ) );
ok( [] === $GLOBALS['_option_writes'], 'P9g and running it again writes nothing, so the compaction cannot loop' );

// A site that has never added a custom item has nothing to prune and nothing to
// write, and never even asks what the custom items are.
amehp_test_reset();
$GLOBALS['_options']['hp_amehp_seen_items'] = [
	'downloads' => [
		'label' => 'DOWNLOADS',
		'route' => '',
		'order' => 63,
	],
];
$GLOBALS['_option_writes'] = [];
$MENU->alter_hp_menu_items( base_menu( [ 'downloads' => 63 ] ) );
ok( [] === $GLOBALS['_option_writes'], 'P9h a site with no custom items at all is left completely alone' );

// The stored value comes from a front-end filter anything may have hooked, so
// it is cleaned again on the way out rather than trusted as it went in.
amehp_test_reset();
$GLOBALS['_options']['hp_amehp_seen_items'] = [
	'tagged' => [
		'label' => '<b>Bold</b> label',
		'route' => '',
		'order' => 10,
	],
	'long'   => [
		'label' => str_repeat( 'x', 250 ),
		'route' => '',
		'order' => 20,
	],
	'blank'  => [
		'label' => '',
		'route' => '',
		'order' => 30,
	],
];
$seen                                       = call_priv( $MENU, 'get_seen_items' );
ok( 'Bold label' === $seen['tagged']['label'], 'P10 markup is stripped out of a stored label on read' );
ok( 100 === strlen( $seen['long']['label'] ), 'P11 an over-long label is cut to 100 characters' );
ok( ! isset( $seen['blank'] ), 'P12 and a record with no label at all is dropped' );

/* ===================== O. version drift ===================== */
echo "\n[O] version drift\n";

$header = get_file_data( AMEHP_TEST_PLUGIN_FILE, [ 'Version' => 'Version' ] );
ok( AMEHP_VERSION === $header['Version'], 'O1 the Version header and the AMEHP_VERSION constant agree' );

amehp_test_finish();
