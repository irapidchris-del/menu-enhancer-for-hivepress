<?php
/**
 * Isolated test suite.
 *
 * Usage: HIVEPRESS_PATH=/path/to/hivepress php tests/run-tests.php
 *
 * @package AccountMenuEnhancer\Tests
 */

// phpcs:ignoreFile

// Abort unless run from the command line.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function check( $name, $condition ) {
	global $passed, $failed;

	if ( $condition ) {
		$passed++;
		echo "PASS  {$name}\n";
	} else {
		$failed++;
		echo "FAIL  {$name}\n";
	}
}

/*
---------------------------------------------------------------------------
 Fixtures
---------------------------------------------------------------------------
*/

function amehp_fixture_hp_items() {
	return [
		'vendor_dashboard'   => [
			'label'  => 'Dashboard',
			'url'    => 'https://example.com/account/vendor/dashboard/',
			'_order' => 5,
		],
		'listings_edit'      => [
			'label'  => 'Listings',
			'route'  => 'listings_edit_page',
			'_order' => 10,
		],
		'messages_thread'    => [
			'label'  => 'Messages',
			'url'    => 'https://example.com/account/messages/',
			'meta'   => '3',
			'_order' => 20,
		],
		'orders_view'        => [
			'label'  => 'Orders',
			'url'    => 'https://example.com/my-account/orders/',
			'_order' => 40,
		],
		'user_edit_settings' => [
			'label'  => 'Settings',
			'url'    => 'https://example.com/account/settings/',
			'_order' => 50,
		],
		'user_logout'        => [
			'label'  => 'Sign Out',
			'url'    => 'https://example.com/account/logout/',
			'_order' => 1000,
		],
	];
}

function amehp_fixture_wc_items() {
	return [
		'dashboard'       => 'Dashboard',
		'orders'          => 'Orders',
		'downloads'       => 'Downloads',
		'edit-address'    => 'Addresses',
		'edit-account'    => 'Account details',
		'customer-logout' => 'Log out',
	];
}

function amehp_fixture_setup() {
	amehp_test_reset();

	amehp_test_state( 'hp_menu_items', amehp_fixture_hp_items() );
	amehp_test_state( 'wc_items', amehp_fixture_wc_items() );
	amehp_test_state(
		'route_urls',
		[
			'listings_edit_page' => 'https://example.com/account/listings/',
		]
	);
}

/*
---------------------------------------------------------------------------
 HivePress menu tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
$component = amehp_test_component();

$menu = $component->alter_hp_menu( [ 'items' => amehp_fixture_hp_items() ] );

check( 'HP menu: WooCommerce dashboard injected', isset( $menu['items']['dashboard'] ) && 'https://example.com/my-account/' === $menu['items']['dashboard']['url'] );
check( 'HP menu: WooCommerce downloads injected', isset( $menu['items']['downloads'] ) );
check( 'HP menu: orders endpoint left to HivePress core', ! isset( $menu['items']['orders'] ) && isset( $menu['items']['orders_view'] ) );
check( 'HP menu: customer-logout not duplicated', ! isset( $menu['items']['customer-logout'] ) );
check( 'HP menu: injected items carry an order', isset( $menu['items']['dashboard']['_order'] ) && 60 === $menu['items']['dashboard']['_order'] );

// Hidden items.
amehp_fixture_setup();
update_option( 'hp_amehp_hidden_items', [ 'hp:messages_thread', 'wc:orders', 'wc:downloads' ] );
$component = amehp_test_component();

$menu = $component->alter_hp_menu( [ 'items' => amehp_fixture_hp_items() ] );

check( 'HP menu: hidden HivePress item removed', ! isset( $menu['items']['messages_thread'] ) );
check( 'HP menu: hidden wc:orders removes the core orders_view item', ! isset( $menu['items']['orders_view'] ) );
check( 'HP menu: hidden WooCommerce endpoint not injected', ! isset( $menu['items']['downloads'] ) );

// Merge disabled.
amehp_fixture_setup();
update_option( 'hp_amehp_merge_menus', '' );
$component = amehp_test_component();

$menu = $component->alter_hp_menu( [ 'items' => amehp_fixture_hp_items() ] );

check( 'HP menu: merge off injects no WooCommerce items', ! isset( $menu['items']['dashboard'] ) && ! isset( $menu['items']['edit-account'] ) );

// Custom items.
amehp_fixture_setup();
update_option(
	'hp_amehp_custom_items',
	[
		[
			'label' => 'Support',
			'link'  => '',
			'url'   => 'https://example.com/support/',
			'menus' => 'both',
			'order' => 15,
		],
		[
			'label' => 'Woo Only',
			'url'   => '/woo-only/',
			'menus' => 'woocommerce',
			'order' => 20,
		],
		[
			'label' => 'Admin Only',
			'url'   => 'https://example.com/admin-only/',
			'menus' => 'both',
			'order' => 25,
			'roles' => [ 'administrator' ],
		],
		[
			'label' => 'No URL',
			'menus' => 'both',
			'order' => 30,
		],
	]
);
$component = amehp_test_component();

$menu = $component->alter_hp_menu( [ 'items' => amehp_fixture_hp_items() ] );

check( 'HP menu: custom item added with order', isset( $menu['items']['amehp_item_1'] ) && 15 === $menu['items']['amehp_item_1']['_order'] );
check( 'HP menu: WooCommerce-only custom item skipped', ! isset( $menu['items']['amehp_item_2'] ) );
check( 'HP menu: role-restricted custom item hidden from customers', ! isset( $menu['items']['amehp_item_3'] ) );
check( 'HP menu: custom item without a URL skipped', ! isset( $menu['items']['amehp_item_4'] ) );

amehp_test_state( 'user_roles', [ 'administrator' ] );
$menu = $component->alter_hp_menu( [ 'items' => amehp_fixture_hp_items() ] );
check( 'HP menu: administrators see role-restricted items', isset( $menu['items']['amehp_item_3'] ) );

/*
---------------------------------------------------------------------------
 WooCommerce menu tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
$component = amehp_test_component();

$wc_menu = $component->alter_wc_menu( amehp_fixture_wc_items() );
$keys    = array_keys( $wc_menu );

check( 'WC menu: HivePress items injected', isset( $wc_menu['listings_edit'] ) && 'Listings' === $wc_menu['listings_edit'] );
check( 'WC menu: HivePress order respected before WooCommerce items', array_search( 'listings_edit', $keys, true ) < array_search( 'dashboard', $keys, true ) );
check( 'WC menu: orders_view URL duplicate not injected', ! isset( $wc_menu['orders_view'] ) && isset( $wc_menu['orders'] ) );
check( 'WC menu: HivePress sign-out skipped when WooCommerce log out present', ! isset( $wc_menu['user_logout'] ) );
check( 'WC menu: WooCommerce log out pinned last', 'customer-logout' === end( $keys ) );

$url = $component->alter_wc_endpoint_url( 'https://example.com/my-account/listings_edit/', 'listings_edit' );
check( 'WC menu: injected item URL rewritten to the HivePress URL', 'https://example.com/account/listings/' === $url );

// Hidden endpoint plus hidden HivePress item.
amehp_fixture_setup();
update_option( 'hp_amehp_hidden_items', [ 'wc:downloads', 'hp:messages_thread' ] );
$component = amehp_test_component();

$wc_menu = $component->alter_wc_menu( amehp_fixture_wc_items() );

check( 'WC menu: hidden WooCommerce endpoint removed', ! isset( $wc_menu['downloads'] ) );
check( 'WC menu: hidden HivePress item not injected', ! isset( $wc_menu['messages_thread'] ) );

// Hiding wc:orders must also drop the mirrored HivePress orders_view item.
amehp_fixture_setup();
$hp_items                 = amehp_fixture_hp_items();
$hp_items['orders_view']  = [
	'label'  => 'Orders',
	'url'    => 'https://example.com/my-account/orders/',
	'_order' => 40,
];
amehp_test_state( 'hp_menu_items', $hp_items );
update_option( 'hp_amehp_hidden_items', [ 'wc:orders' ] );
$component = amehp_test_component();

$wc_menu = $component->alter_wc_menu( amehp_fixture_wc_items() );

check( 'WC menu: hiding wc:orders removes the WooCommerce Orders endpoint', ! isset( $wc_menu['orders'] ) );
check( 'WC menu: hiding wc:orders also removes the mirrored HivePress orders_view', ! isset( $wc_menu['orders_view'] ) );

// Custom item targeted at WooCommerce with a relative URL.
amehp_fixture_setup();
update_option(
	'hp_amehp_custom_items',
	[
		[
			'label' => 'Woo Only',
			'url'   => '/woo-only/',
			'menus' => 'woocommerce',
			'order' => 20,
		],
	]
);
$component = amehp_test_component();

$wc_menu = $component->alter_wc_menu( amehp_fixture_wc_items() );

check( 'WC menu: WooCommerce-only custom item injected', isset( $wc_menu['amehp_item_1'] ) );
check( 'WC menu: relative custom URL resolved against the site URL', 'https://example.com/woo-only/' === $component->alter_wc_endpoint_url( '', 'amehp_item_1' ) );

// Merge disabled keeps custom items but no HivePress items.
amehp_fixture_setup();
update_option( 'hp_amehp_merge_menus', '' );
update_option(
	'hp_amehp_custom_items',
	[
		[
			'label' => 'Support',
			'url'   => 'https://example.com/support/',
			'menus' => 'both',
			'order' => 20,
		],
	]
);
$component = amehp_test_component();

$wc_menu = $component->alter_wc_menu( amehp_fixture_wc_items() );

check( 'WC menu: merge off injects no HivePress items', ! isset( $wc_menu['listings_edit'] ) );
check( 'WC menu: merge off still injects custom items', isset( $wc_menu['amehp_item_1'] ) );

/*
---------------------------------------------------------------------------
 Custom URL resolution tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
amehp_test_state( 'permalinks', [ 12 => 'https://example.com/about/' ] );
amehp_test_state(
	'route_urls',
	[
		'user_view_page' => 'https://example.com/user/chris/',
	]
);
amehp_test_state( 'vendor_id', 55 );
$component = amehp_test_component();

$resolve = function ( $item ) use ( $component ) {
	return amehp_test_call( $component, 'get_custom_item_url', [ array_merge( [ 'link' => '', 'url' => '' ], $item ) ] );
};

check( 'URL: invalid custom URL rejected', '' === $resolve( [ 'url' => 'not a url' ] ) );
check( 'URL: page link resolved', 'https://example.com/about/' === $resolve( [ 'link' => 'page:12' ] ) );
check( 'URL: missing page rejected', '' === $resolve( [ 'link' => 'page:99' ] ) );
check( 'URL: WooCommerce endpoint link resolved', 'https://example.com/my-account/downloads/' === $resolve( [ 'link' => 'wc:downloads' ] ) );
check( 'URL: user profile route resolved for the current user', 'https://example.com/user/chris/' === $resolve( [ 'link' => 'route:user_view_page' ] ) );
check( 'URL: custom URL overrides the link', 'https://example.com/override/' === $resolve( [ 'link' => 'page:12', 'url' => 'https://example.com/override/' ] ) );

amehp_test_state( 'vendor_id', 0 );
check( 'URL: vendor profile route empty without a vendor', '' === $resolve( [ 'link' => 'route:vendor_view_page' ] ) );

// The user profile route must be built with the username, the way core does.
amehp_fixture_setup();
amehp_test_state( 'user_login', 'chris' );
$component = amehp_test_component();
$resolve   = function ( $item ) use ( $component ) {
	return amehp_test_call( $component, 'get_custom_item_url', [ array_merge( [ 'link' => '', 'url' => '' ], $item ) ] );
};

$user_url    = $resolve( [ 'link' => 'route:user_view_page' ] );
$user_params = amehp_test_state( 'route_params' );

check( 'URL: user profile route built from the username', 'https://example.com/user/chris/' === $user_url );
check( 'URL: user profile route uses the username param, not a user ID', isset( $user_params['user_view_page']['username'] ) && 'chris' === $user_params['user_view_page']['username'] && ! isset( $user_params['user_view_page']['user'] ) );

// Only web URL schemes are accepted for custom URLs.
check( 'URL: javascript scheme rejected', '' === $resolve( [ 'url' => 'javascript://%0aalert(1)' ] ) );
check( 'URL: ftp scheme rejected', '' === $resolve( [ 'url' => 'ftp://example.com/file' ] ) );
check( 'URL: https scheme accepted', 'https://example.com/page/' === $resolve( [ 'url' => 'https://example.com/page/' ] ) );
check( 'URL: http scheme accepted', 'http://example.com/page/' === $resolve( [ 'url' => 'http://example.com/page/' ] ) );

// Unpublished pages are dropped.
amehp_fixture_setup();
amehp_test_state( 'permalinks', [ 12 => 'https://example.com/about/', 13 => 'https://example.com/draft/' ] );
amehp_test_state(
	'posts',
	[
		12 => [ 'post_status' => 'publish' ],
		13 => [ 'post_status' => 'draft' ],
	]
);
$component = amehp_test_component();
$resolve   = function ( $item ) use ( $component ) {
	return amehp_test_call( $component, 'get_custom_item_url', [ array_merge( [ 'link' => '', 'url' => '' ], $item ) ] );
};

check( 'URL: published page link resolved', 'https://example.com/about/' === $resolve( [ 'link' => 'page:12' ] ) );
check( 'URL: unpublished page link dropped', '' === $resolve( [ 'link' => 'page:13' ] ) );

// A zero/invalid page id must not fall back to the current (global) page.
amehp_test_state( 'global_post', [ 'ID' => 99, 'post_status' => 'publish' ] );
amehp_test_state( 'permalinks', [ 12 => 'https://example.com/about/', 99 => 'https://example.com/current/' ] );
check( 'URL: page link with a zero id dropped, not resolved to the current page', '' === $resolve( [ 'link' => 'page:0' ] ) );
check( 'URL: page link with a non-numeric id dropped', '' === $resolve( [ 'link' => 'page:abc' ] ) );

/*
---------------------------------------------------------------------------
 Icon CSS tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
update_option(
	'hp_amehp_icons',
	[
		[
			'item'   => 'hp:listings_edit',
			'icon'   => 'home',
			'colour' => '#ff0000',
		],
		[
			'item'   => 'wc:orders',
			'icon'   => 'shopping-cart',
			'colour' => 'red',
		],
		[
			'item' => 'hp:messages_thread',
			'icon' => 'no-such-icon',
		],
	]
);
update_option(
	'hp_amehp_custom_items',
	[
		[
			'label'  => 'Support',
			'url'    => 'https://example.com/support/',
			'menus'  => 'both',
			'order'  => 15,
			'icon'   => 'heart',
			'colour' => '#00ff00',
		],
	]
);
update_option( 'hp_amehp_icon_colour', '#123abc' );
$component = amehp_test_component();

$css = amehp_test_call( $component, 'get_icon_css' );

check( 'CSS: HivePress selector emitted with the icon codepoint', false !== strpos( $css, '.hp-menu--user-account .hp-menu__item--listings-edit > a::before' ) && false !== strpos( $css, 'content:"\\f015"' ) );
check( 'CSS: WooCommerce selector emitted for the same item', false !== strpos( $css, '.woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--listings_edit > a::before' ) );
check( 'CSS: wc:orders also targets the HivePress orders_view item', false !== strpos( $css, '.hp-menu__item--orders-view > a' ) && false !== strpos( $css, 'content:"\\f07a"' ) );
check( 'CSS: item colour emitted as a custom property', false !== strpos( $css, '--amehp-icon-colour:#ff0000' ) );
check( 'CSS: invalid colour rejected', false === strpos( $css, 'red;' ) );
check( 'CSS: unknown icon slug skipped', false === strpos( $css, 'no-such-icon' ) && false === strpos( $css, 'messages-thread' ) );
check( 'CSS: custom item icon emitted', false !== strpos( $css, '.hp-menu__item--amehp-item-1 > a::before' ) && false !== strpos( $css, 'content:"\\f004"' ) );
check( 'CSS: default colour emitted on root', false !== strpos( $css, ':root{--amehp-icon-colour:#123abc;}' ) );
check( 'CSS: base rule uses the Font Awesome stack', false !== strpos( $css, '"Font Awesome 7 Free","Font Awesome 6 Free","Font Awesome 5 Free"' ) && false !== strpos( $css, 'font-weight:900' ) );
check( 'CSS: icon links kept left aligned for flex themes', false !== strpos( $css, '{justify-content:flex-start;}' ) );
check( 'CSS: counters pushed to the end for flex themes', false !== strpos( $css, '>small{margin-inline-start:auto;}' ) );

/*
---------------------------------------------------------------------------
 Appearance CSS tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
update_option( 'hp_amehp_menu_weight', '700' );
update_option( 'hp_amehp_hide_chevrons', '1' );
update_option( 'hp_amehp_hide_wc_header', '1' );
$component = amehp_test_component();

$appearance = amehp_test_call( $component, 'get_appearance_css' );

check( 'Appearance: menu weight applied to both account menus', false !== strpos( $appearance, '.hp-menu--user-account .hp-menu__item > a,.woocommerce-MyAccount-navigation ul li > a{font-weight:700;}' ) );
check( 'Appearance: chevrons hidden on the sidebar account menus only', false !== strpos( $appearance, '.widget_nav_menu .hp-menu__item::before,.woocommerce-MyAccount-navigation ul li::before{display:none;}' ) );
check( 'Appearance: chevron indent removed from the sidebar account menus', false !== strpos( $appearance, '.widget_nav_menu .hp-menu__item,.woocommerce-MyAccount-navigation ul li{padding-inline-start:0;}' ) );
check( 'Appearance: chevron rules do not target the bare user account menu (dropdown safe)', false === strpos( $appearance, '.hp-menu--user-account .hp-menu__item::before' ) );
check( 'Appearance: WooCommerce header hidden', false !== strpos( $appearance, 'body.woocommerce-account .header-hero--title{display:none;}' ) );

// Invalid or empty settings emit nothing.
amehp_fixture_setup();
update_option( 'hp_amehp_menu_weight', 'bold' );
$component = amehp_test_component();
check( 'Appearance: invalid weight rejected', false === strpos( amehp_test_call( $component, 'get_appearance_css' ), 'font-weight' ) );

amehp_fixture_setup();
$component = amehp_test_component();
check( 'Appearance: no settings emit no CSS', '' === amehp_test_call( $component, 'get_appearance_css' ) );

// Appearance-only settings (no icons) still enqueue inline CSS.
amehp_fixture_setup();
$GLOBALS['amehp_test']['inline_css'] = '';
update_option( 'hp_amehp_menu_weight', '600' );
$component = amehp_test_component();
$component->enqueue_frontend_assets();
check( 'Appearance: enqueue outputs appearance CSS without any icons', false !== strpos( (string) amehp_test_state( 'inline_css' ), 'font-weight:600;' ) );

/*
---------------------------------------------------------------------------
 Text colour CSS tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
update_option(
	'hp_amehp_icons',
	[
		// Text colour without an icon (icon is now optional).
		[
			'item'        => 'hp:listings_edit',
			'text_colour' => '#112233',
		],
		// Icon and text colour together.
		[
			'item'        => 'wc:orders',
			'icon'        => 'shopping-cart',
			'colour'      => '#ff0000',
			'text_colour' => '#00ff00',
		],
		// Invalid text colour is ignored.
		[
			'item'        => 'hp:messages_thread',
			'text_colour' => 'purple',
		],
	]
);
$component = amehp_test_component();

$text_css = amehp_test_call( $component, 'get_text_colour_css' );

check( 'Text colour: applied to a link without an icon', false !== strpos( $text_css, '.hp-menu--user-account .hp-menu__item--listings-edit > a' ) && false !== strpos( $text_css, 'color:#112233;' ) );
check( 'Text colour: applied alongside an icon colour', false !== strpos( $text_css, 'color:#00ff00;' ) );
check( 'Text colour: invalid value ignored', false === strpos( $text_css, 'purple' ) );

// The icon colour and text colour stay independent in the icon CSS.
$icon_css = amehp_test_call( $component, 'get_icon_css' );
check( 'Text colour: icon colour still emitted independently', false !== strpos( $icon_css, '--amehp-icon-colour:#ff0000' ) );

// Custom items support a text colour too.
amehp_fixture_setup();
update_option(
	'hp_amehp_custom_items',
	[
		[
			'label'       => 'Support',
			'url'         => 'https://example.com/support/',
			'menus'       => 'both',
			'order'       => 20,
			'text_colour' => '#abcdef',
		],
	]
);
$component = amehp_test_component();

$custom_text_css = amehp_test_call( $component, 'get_text_colour_css' );

check( 'Text colour: applied to a custom item link', false !== strpos( $custom_text_css, '.hp-menu__item--amehp-item-1 > a' ) && false !== strpos( $custom_text_css, 'color:#abcdef;' ) );

/*
---------------------------------------------------------------------------
 Counter tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
$component = amehp_test_component();

$badges = amehp_test_call( $component, 'get_badge_counts' );

check( 'Counters: HivePress meta values collected by item name', isset( $badges['messages_thread'] ) && '3' === $badges['messages_thread'] );
check( 'Counters: items without meta skipped', 1 === count( $badges ) );

update_option( 'hp_amehp_wc_badges', '' );
check( 'Counters: disabled setting returns nothing', [] === amehp_test_call( $component, 'get_badge_counts' ) );

/*
---------------------------------------------------------------------------
 Account layout unification tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
amehp_test_state( 'is_account', true );
amehp_test_state( 'wc_endpoint', 'edit-account' );
$component = amehp_test_component();

check( 'Unify: WooCommerce template swapped for the HivePress template', getenv( 'HIVEPRESS_PATH' ) . '/templates/woocommerce/myaccount/my-account.php' === $component->set_account_template( '/wc/my-account.php', 'myaccount/my-account.php' ) );
check( 'Unify: other WooCommerce templates untouched', '/wc/form-login.php' === $component->set_account_template( '/wc/form-login.php', 'myaccount/form-login.php' ) );

// Mirror the real user_account_page tree, where page_content sits inside
// the page container like in HivePress\Templates\Page.
$account_tree = [
	'blocks' => [
		'page_container' => [
			'type'   => 'page',
			'blocks' => [
				'page_columns' => [
					'type'   => 'container',
					'blocks' => [
						'page_sidebar' => [
							'type'   => 'container',
							'blocks' => [],
						],
						'page_content' => [
							'type'   => 'container',
							'blocks' => [],
						],
					],
				],
			],
		],
	],
];

$template = $component->alter_account_page( $account_tree );
$content  = \HivePress\Helpers\search_array_value( $template, [ 'blocks', 'page_content' ] );

check( 'Unify: account content block injected', isset( $content['blocks']['amehp_woocommerce_content'] ) && 'do_action' === $content['blocks']['amehp_woocommerce_content']['callback'] && [ 'woocommerce_account_content' ] === $content['blocks']['amehp_woocommerce_content']['params'] );
check( 'Unify: page container flattened like HivePress core does', 'container' === $template['blocks']['page_container']['type'] );

// Endpoints handled by HivePress core stay untouched.
amehp_test_state( 'wc_endpoint', 'orders' );
check( 'Unify: core-handled orders endpoint not swapped by the plugin', '/wc/my-account.php' === $component->set_account_template( '/wc/my-account.php', 'myaccount/my-account.php' ) );

// The account page itself maps to the dashboard endpoint.
amehp_test_state( 'wc_endpoint', '' );
check( 'Unify: account page root treated as the dashboard', getenv( 'HIVEPRESS_PATH' ) . '/templates/woocommerce/myaccount/my-account.php' === $component->set_account_template( '/wc/my-account.php', 'myaccount/my-account.php' ) );

// Outside the account area nothing changes.
amehp_test_state( 'is_account', false );
$template = $component->alter_account_page( $account_tree );
check( 'Unify: HivePress account pages untouched', $account_tree === $template );

// Setting disabled.
amehp_test_state( 'is_account', true );
amehp_test_state( 'wc_endpoint', 'edit-account' );
update_option( 'hp_amehp_unify_account', '' );
check( 'Unify: disabled setting keeps the WooCommerce template', '/wc/my-account.php' === $component->set_account_template( '/wc/my-account.php', 'myaccount/my-account.php' ) );

// Logged out users never get the swap.
update_option( 'hp_amehp_unify_account', '1' );
amehp_test_state( 'logged_in', false );
check( 'Unify: logged out visitors keep the WooCommerce template', '/wc/my-account.php' === $component->set_account_template( '/wc/my-account.php', 'myaccount/my-account.php' ) );

/*
---------------------------------------------------------------------------
 Settings option tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
amehp_test_state( 'is_admin', true );
amehp_test_state(
	'route_titles',
	[
		'bookings_view_page' => 'Bookings',
		'user_logout_page'   => 'Sign Out',
	]
);
update_option( 'hp_amehp_hidden_items', [ 'hp:legacy_item' ] );
$component = amehp_test_component();

$options = $component->get_menu_item_options();

check( 'Options: live HivePress items listed', isset( $options['hp:listings_edit'] ) && 'Listings' === $options['hp:listings_edit'] );
check( 'Options: core orders_view listed under its WooCommerce name', ! isset( $options['hp:orders_view'] ) && isset( $options['wc:orders'] ) );
check( 'Options: pinned extension items listed via route titles', isset( $options['hp:bookings_view'] ) && 'Bookings' === $options['hp:bookings_view'] );
check( 'Options: WooCommerce items suffixed', 'Orders (WooCommerce)' === $options['wc:orders'] );
check( 'Options: previously saved keys stay selectable', isset( $options['hp:legacy_item'] ) );

/*
---------------------------------------------------------------------------
 Migration tests
---------------------------------------------------------------------------
*/

amehp_fixture_setup();
amehp_test_state( 'is_admin', true );

require_once dirname( __DIR__ ) . '/account-menu-enhancer-for-hivepress.php';

update_option(
	'amehp_settings',
	[
		'enable_integration'        => 1,
		'woocommerce_items_to_hide' => [ 'downloads', 'customer-logout' ],
		'custom_menu_items'         => [
			[
				'label'    => 'Old URL Item',
				'type'     => 'url',
				'url'      => 'https://example.com/old/',
				'menu'     => 'both',
				'position' => 40,
				'roles'    => [ 'customer' ],
			],
			[
				'label'   => 'Old Page Item',
				'type'    => 'page',
				'page_id' => 12,
				'menu'    => 'hivepress',
			],
			[
				'label' => 'Old Route Item',
				'type'  => 'hivepress_route',
				'route' => 'user_view_page',
				'menu'  => 'woocommerce',
			],
		],
	]
);

amehp_maybe_migrate();

check( 'Migration: integration mapped to menu merging', '1' === get_option( 'hp_amehp_merge_menus' ) );
check( 'Migration: layout unification stays off for upgraded sites', '' === get_option( 'hp_amehp_unify_account' ) );
check( 'Migration: hidden endpoints prefixed', [ 'wc:downloads', 'wc:customer-logout' ] === get_option( 'hp_amehp_hidden_items' ) );

$migrated = get_option( 'hp_amehp_custom_items' );

check( 'Migration: URL item mapped', 'https://example.com/old/' === $migrated[0]['url'] && 40 === $migrated[0]['order'] && [ 'customer' ] === $migrated[0]['roles'] );
check( 'Migration: page item mapped to a page link', 'page:12' === $migrated[1]['link'] && 'hivepress' === $migrated[1]['menus'] );
check( 'Migration: route item mapped to a route link', 'route:user_view_page' === $migrated[2]['link'] && 'woocommerce' === $migrated[2]['menus'] );
check( 'Migration: version flag written', AMEHP_VERSION === get_option( 'amehp_version' ) );

$before = get_option( 'hp_amehp_custom_items' );
update_option( 'amehp_settings', [ 'enable_integration' => 0 ] );
amehp_maybe_migrate();
check( 'Migration: never runs twice', $before === get_option( 'hp_amehp_custom_items' ) && '1' === get_option( 'hp_amehp_merge_menus' ) );

/*
---------------------------------------------------------------------------
 Route title callable regressions (staging fatal, v2.0.1)
---------------------------------------------------------------------------
*/

amehp_fixture_setup();

$amehp_called = [];

amehp_test_state(
	'route_titles',
	[
		'listings_edit_page'      => 'Listings',
		'user_edit_settings_page' => 'Settings',

		// Titles that expect the front-end query context, like
		// Vendor->get_vendor_view_title() calling the_post() in wp-admin.
		'user_view_page'          => function () use ( &$amehp_called ) {
			$amehp_called[] = 'user_view_page';

			throw new \TypeError( 'array_reduce(): Argument #1 ($array) must be of type array, null given' );
		},
		'vendor_view_page'        => function () use ( &$amehp_called ) {
			$amehp_called[] = 'vendor_view_page';

			throw new \TypeError( 'array_reduce(): Argument #1 ($array) must be of type array, null given' );
		},
		'orders_edit_page'        => function () use ( &$amehp_called ) {
			$amehp_called[] = 'orders_edit_page';

			return 'Received Orders';
		},
	]
);

amehp_test_state(
	'route_urls',
	[
		'listings_edit_page'      => 'https://example.com/account/listings/',
		'user_edit_settings_page' => 'https://example.com/account/settings/',
		'orders_edit_page'        => 'https://example.com/account/vendor/orders/',
	]
);

$amehp = amehp_test_component();

$links = $amehp->get_link_options();

check( 'Regression: link options build without invoking any route title callable', [] === $amehp_called );
check( 'Regression: profile link listed with the plugin label', isset( $links['route:vendor_view_page'] ) && 'My Vendor Profile' === $links['route:vendor_view_page'] );
check( 'Regression: user profile link listed with the plugin label', isset( $links['route:user_view_page'] ) && 'My Profile' === $links['route:user_view_page'] );
check( 'Regression: callable-titled pinned route falls back to the plugin label', isset( $links['route:orders_edit_page'] ) && 'Received Orders' === $links['route:orders_edit_page'] );
check( 'Regression: string-titled pinned route keeps the route title', isset( $links['route:listings_edit_page'] ) && 'Listings' === $links['route:listings_edit_page'] );
check( 'Regression: missing routes are not listed', ! isset( $links['route:memberships_view_page'] ) );

$items = $amehp->get_menu_item_options();

check( 'Regression: item options build without invoking any route title callable', [] === $amehp_called );
check( 'Regression: pinned item with a callable title uses the plugin label', isset( $items['hp:orders_edit'] ) && 'Received Orders' === $items['hp:orders_edit'] );

// The canonical menu build survives a third-party context error.
amehp_fixture_setup();
amehp_test_state( 'menu_throws', true );

$amehp = amehp_test_component();

$wc_menu = $amehp->alter_wc_menu( amehp_fixture_wc_items() );

check( 'Regression: throwing menu build falls back without a fatal', is_array( $wc_menu ) && isset( $wc_menu['dashboard'] ) );
check( 'Regression: throwing menu build yields an empty base menu', [] === amehp_test_call( $amehp, 'get_base_hp_items' ) );

// A throwing WooCommerce menu build must also fall back and reset the flag.
amehp_fixture_setup();
amehp_test_state( 'wc_menu_throws', true );

$amehp = amehp_test_component();

check( 'Regression: throwing WooCommerce base menu yields an empty menu', [] === amehp_test_call( $amehp, 'get_base_wc_items' ) );

$suppressed = new ReflectionProperty( $amehp, 'suppressed' );
$suppressed->setAccessible( true );
check( 'Regression: throwing WooCommerce base menu resets the suppressed flag', false === $suppressed->getValue( $amehp ) );

/*
---------------------------------------------------------------------------
 Link option preservation
---------------------------------------------------------------------------
*/

// Saved custom-item links must stay selectable after their target disappears,
// so that saving the settings does not fail core validation.
amehp_fixture_setup();
update_option(
	'hp_amehp_custom_items',
	[
		[
			'label' => 'Old Booking',
			'link'  => 'route:bookings_view_page',
			'menus' => 'both',
			'order' => 20,
		],
		[
			'label' => 'Old Subscriptions',
			'link'  => 'wc:subscriptions',
			'menus' => 'both',
			'order' => 25,
		],
	]
);
$amehp = amehp_test_component();

$links = $amehp->get_link_options();

// Neither target is in the live option list (the route has no extension and
// "subscriptions" is not a fixture endpoint), so only the preservation path
// can keep them selectable.
check( 'Links: saved route link kept selectable after its route disappears', isset( $links['route:bookings_view_page'] ) );
check( 'Links: saved WooCommerce link kept selectable after its endpoint disappears', isset( $links['wc:subscriptions'] ) );

/*
---------------------------------------------------------------------------
 Migration race regression
---------------------------------------------------------------------------
*/

// HivePress seeds the plugin option defaults before admin_init, so the
// migration must still import the legacy settings rather than bail out.
amehp_fixture_setup();
amehp_test_state( 'is_admin', true );

// Emulate HivePress init_settings() seeding the checkbox defaults first.
update_option( 'hp_amehp_merge_menus', '1' );
update_option( 'hp_amehp_unify_account', '1' );

update_option( 'amehp_version', '' );
update_option(
	'amehp_settings',
	[
		'enable_integration'        => 0,
		'woocommerce_items_to_hide' => [ 'downloads' ],
		'custom_menu_items'         => [
			[
				'label'    => 'Support',
				'type'     => 'url',
				'url'      => 'https://example.com/support/',
				'menu'     => 'both',
				'position' => 30,
			],
		],
	]
);

amehp_maybe_migrate();

check( 'Migration race: legacy integration flag still imported over the seeded default', '' === get_option( 'hp_amehp_merge_menus' ) );
check( 'Migration race: layout preserved for upgraded sites over the seeded default', '' === get_option( 'hp_amehp_unify_account' ) );
check( 'Migration race: hidden items still imported', [ 'wc:downloads' ] === get_option( 'hp_amehp_hidden_items' ) );

$raced = get_option( 'hp_amehp_custom_items' );
check( 'Migration race: custom items still imported', is_array( $raced ) && isset( $raced[0]['url'] ) && 'https://example.com/support/' === $raced[0]['url'] );

/*
---------------------------------------------------------------------------
 Summary
---------------------------------------------------------------------------
*/

echo "\n{$passed} passed, {$failed} failed\n";

exit( $failed ? 1 : 0 );
