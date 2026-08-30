/**
 * QA harness for which items the preview draws in which panel, and in what
 * order.
 *
 * The panel claims to show the menu the site will actually render, so a wrong
 * answer here is the same silent failure as a wrong key: the owner arranges
 * something that does not match what visitors see. Two settings decide the
 * shape - the WooCommerce integration, which combines the two account menus
 * into one, and Hidden Items - and both are applied here exactly as the front
 * end applies them.
 *
 * Run once per site shape, because the catalogue itself changes: with
 * WooCommerce absent there are no wc: entries to route at all. See run-js.js.
 *
 * Env:
 *   AMEHP_JS_WC=absent   the site has no WooCommerce, so no wc: menu items.
 *
 * @package AccountMenuEnhancer\Tests
 */

'use strict';

const { logic } = require( './load-logic' );
const { ok, is, finish, section } = require( './harness' );

const HAS_WC = 'absent' !== process.env.AMEHP_JS_WC;

/**
 * The site's real account menu, as the Menu Item Styling dropdown hands it over.
 */
const CATALOGUE = [
	{ value: 'hp:listings_edit', label: 'My Listings' },
	{ value: 'hp:messages', label: 'Messages' },
	{ value: 'hp:settings', label: 'Settings' },
].concat(
	HAS_WC
		? [
			{ value: 'wc:orders', label: 'Orders' },
			{ value: 'wc:downloads', label: 'Downloads' },
		]
		: []
);

/**
 * The keys one panel would draw, so a check reads as a menu rather than as a
 * list of objects.
 *
 * @param {Object} hidden Lookup of hidden item keys.
 * @param {string} which Which menu.
 * @param {boolean} combined Whether the menus are combined.
 * @param {Array} hpWcKeys Keys the HivePress menu carries in their own right.
 * @return {Array}
 */
function panel( hidden, which, combined, hpWcKeys ) {
	return logic.catalogueItems( CATALOGUE, hidden, which, combined, hpWcKeys ).map( function ( entry ) {
		return entry.value;
	} );
}

process.stdout.write( '=== Account Menu Enhancer preview logic: menus and ordering (WooCommerce ' + ( HAS_WC ? 'on' : 'ABSENT' ) + ') ===\n' );

/* ===================== G. telling the two menus apart ===================== */
section( '[G] isWooItem' );

ok( logic.isWooItem( 'wc:orders' ), 'G1 a wc: key is a WooCommerce endpoint' );
ok( ! logic.isWooItem( 'hp:messages' ), 'G2 an hp: key is not' );
ok( ! logic.isWooItem( 'amehp_item_ab12cd34' ), 'G3 nor is a custom item' );
ok( ! logic.isWooItem( 'listings_wc:edit' ), 'G4 and the prefix is only a prefix at the start of the key' );

/* ===================== H. which panel an item belongs in ===================== */
section( '[H] catalogueItems - routing the site menu into the panels' );

is( panel( {}, 'combined', true ), CATALOGUE.map( ( e ) => e.value ), 'H1 the combined panel takes every item the site has' );
is( panel( {}, 'hivepress', false ), [ 'hp:listings_edit', 'hp:messages', 'hp:settings' ], 'H2 with the menus separate the HivePress panel takes the HivePress items' );

if ( HAS_WC ) {
	is( panel( {}, 'woocommerce', false ), [ 'wc:orders', 'wc:downloads' ], 'H3 and the WooCommerce panel takes the WooCommerce endpoints' );
	is( panel( {}, 'combined', true ).length, 5, 'H4 combining the menus is what puts all five in one panel' );
	is( panel( {}, 'hivepress', false ).length, 3, 'H5 while separate menus leave the HivePress panel three' );
} else {
	is( panel( {}, 'woocommerce', false ), [], 'H3 with no WooCommerce on the site its panel has nothing to draw' );
	is( panel( {}, 'combined', true ), panel( {}, 'hivepress', false ), 'H4 so combining the menus changes nothing about what is shown' );
	is( panel( {}, 'hivepress', false ).length, 3, 'H5 and the HivePress panel takes all three items' );
}

is( panel( {}, 'hivepress', false ), [ 'hp:listings_edit', 'hp:messages', 'hp:settings' ], 'H6 the catalogue order is preserved, because the sort happens later' );

/* ===================== I. hidden items ===================== */
section( '[I] hidden items are hidden in every panel' );

is(
	panel( { 'hp:messages': true }, 'hivepress', false ),
	[ 'hp:listings_edit', 'hp:settings' ],
	'I1 a hidden HivePress item is dropped from the HivePress panel'
);

is(
	panel( { 'hp:messages': true }, 'combined', true ).indexOf( 'hp:messages' ),
	-1,
	'I2 and from the combined panel, where the WooCommerce rule would otherwise have let it through'
);

is(
	panel( { 'hp:listings_edit': true, 'hp:messages': true, 'hp:settings': true }, 'hivepress', false ),
	[],
	'I3 hiding everything leaves the panel empty rather than falling back to a full menu'
);

ok( ! logic.includesCatalogueEntry( 'hp:messages', 'combined', true, { 'hp:messages': true } ), 'I4 hidden is checked before the menu rules, so nothing can override it' );
ok( logic.includesCatalogueEntry( 'hp:messages', 'combined', true, {} ), 'I5 an empty hidden list hides nothing' );
ok( logic.includesCatalogueEntry( 'hp:messages', 'hivepress', false, undefined ), 'I6 and a missing hidden list is not an error' );

if ( HAS_WC ) {
	is(
		panel( { 'wc:orders': true }, 'woocommerce', false ),
		[ 'wc:downloads' ],
		'I7 a hidden WooCommerce endpoint is dropped from the WooCommerce panel'
	);

	ok( ! logic.includesCatalogueEntry( 'wc:orders', 'hivepress', false, {} ), 'I8 and with the menus separate a WooCommerce endpoint never reaches the HivePress panel' );
} else {
	is( panel( { 'wc:orders': true }, 'woocommerce', false ), [], 'I7 hiding an endpoint the site does not have changes nothing' );
	ok( ! logic.includesCatalogueEntry( 'wc:orders', 'hivepress', false, {} ), 'I8 and the routing rule still refuses a wc: key in the HivePress panel' );
}

/* ===================== I2. the items core adds under a WooCommerce name ===================== */
section( '[I2] the HivePress menu\'s own WooCommerce-named items' );

/*
 * HivePress core adds "Placed Orders" to its OWN account menu as soon as the
 * member has an order, whether or not the integration is on, and this screen
 * lists it under wc:orders because that is the same destination as the
 * WooCommerce row. Reading the prefix alone dropped it from the HivePress
 * panel, so the owner could not drag it and it rendered below Sign Out on the
 * real menu. Reported from a live site on 2026-08-30. The server says which
 * keys these are; see get_hp_menu_wc_keys().
 */
if ( HAS_WC ) {
	is(
		panel( {}, 'hivepress', false, [ 'wc:orders' ] ),
		[ 'hp:listings_edit', 'hp:messages', 'hp:settings', 'wc:orders' ],
		'I2a a WooCommerce-named item the HivePress menu carries itself is shown in the HivePress panel'
	);
	is(
		panel( {}, 'woocommerce', false, [ 'wc:orders' ] ),
		[ 'wc:orders', 'wc:downloads' ],
		'I2b and still in the WooCommerce panel, because it really is in both menus'
	);
	is(
		panel( {}, 'hivepress', false, [ 'wc:orders' ] ).indexOf( 'wc:downloads' ),
		-1,
		'I2c while an endpoint the plugin merges in itself stays out, which is what the integration switch means'
	);
	is(
		panel( { 'wc:orders': true }, 'hivepress', false, [ 'wc:orders' ] ).indexOf( 'wc:orders' ),
		-1,
		'I2d hiding it still hides it, in this panel as in every other'
	);
	is(
		panel( {}, 'hivepress', false, [] ),
		[ 'hp:listings_edit', 'hp:messages', 'hp:settings' ],
		'I2e and an empty list leaves the old prefix rule exactly as it was'
	);
} else {
	is( panel( {}, 'hivepress', false, [ 'wc:orders' ] ).length, 3, 'I2a with no WooCommerce there is no such item to admit' );
	is( panel( {}, 'woocommerce', false, [ 'wc:orders' ] ), [], 'I2b and nothing for its panel to draw' );
	ok( ! logic.includesCatalogueEntry( 'wc:orders', 'hivepress', false, {}, [] ), 'I2c an empty list still refuses a wc: key' );
	ok( logic.includesCatalogueEntry( 'wc:orders', 'hivepress', false, {}, [ 'wc:orders' ] ), 'I2d and a named one is admitted whatever the site has installed' );
	ok( ! logic.includesCatalogueEntry( 'wc:orders', 'hivepress', false, { 'wc:orders': true }, [ 'wc:orders' ] ), 'I2e hidden still beats every other rule' );
}

/* ===================== J. custom items pick their own menu ===================== */
section( '[J] includesCustomItem - the row\'s own Menus field' );

ok( logic.includesCustomItem( '', 'hivepress', false ), 'J1 an item set to Both Menus appears in the HivePress panel' );
ok( logic.includesCustomItem( '', 'woocommerce', false ), 'J2 and in the WooCommerce one' );
ok( logic.includesCustomItem( 'hivepress', 'hivepress', false ), 'J3 an item set to one menu appears in that menu' );
ok( ! logic.includesCustomItem( 'hivepress', 'woocommerce', false ), 'J4 and not in the other one' );
ok( ! logic.includesCustomItem( 'woocommerce', 'hivepress', false ), 'J5 which holds in both directions' );
ok( logic.includesCustomItem( 'woocommerce', 'combined', true ), 'J6 with the menus combined there is one menu, so every custom item is in it' );
ok( logic.includesCustomItem( 'hivepress', 'combined', true ), 'J7 whichever menu the row names' );

/* ===================== K. the order the panel draws ===================== */
section( '[K] sortItems - the arrangement kept, the rest slotted in by menu order' );

/*
 * THIS IS apply_menu_order() IN JAVASCRIPT and every case below has a twin in
 * tests/logic-tests.php section B and tests/migration-tests.php section G. If
 * the two ever disagree the preview is lying to the owner about their own menu,
 * which is the one thing this panel exists to prevent.
 */

is(
	logic.sortItems(
		[
			{ key: 'hp:c', order: 30 },
			{ key: 'hp:a', order: 10 },
			{ key: 'hp:b', order: 20 },
		],
		[]
	).map( ( i ) => i.key ),
	[ 'hp:a', 'hp:b', 'hp:c' ],
	'K1 with no arrangement stored, items sort by the real menu order'
);

is(
	logic.sortItems(
		[
			{ key: 'hp:a', order: 10 },
			{ key: 'hp:b', order: 20 },
			{ key: 'hp:c', order: 30 },
		],
		[ 'hp:c' ]
	).map( ( i ) => i.key ),
	[ 'hp:a', 'hp:b', 'hp:c' ],
	'K2 an item the owner never placed keeps its own menu order rather than being pushed below the one they did'
);

is(
	logic.sortItems(
		[
			{ key: 'hp:a', order: 10 },
			{ key: 'hp:b', order: 20 },
		],
		[ 'hp:b', 'hp:a' ]
	).map( ( i ) => i.key ),
	[ 'hp:b', 'hp:a' ],
	'K3 and the arrangement beats the numeric order, which is the whole point of dragging'
);

is(
	logic.sortItems(
		[
			{ key: 'hp:a', order: 100 },
			{ key: 'hp:b', order: 100 },
			{ key: 'hp:c', order: 100 },
		],
		[]
	).map( ( i ) => i.key ),
	[ 'hp:a', 'hp:b', 'hp:c' ],
	'K4 items sharing an order number keep the order they were collected in'
);

is(
	logic.sortItems(
		[
			{ key: 'amehp_item_ab12cd34', order: 100 },
			{ key: 'hp:a', order: 10 },
		],
		[ 'amehp_item_ab12cd34' ]
	).map( ( i ) => i.key ),
	[ 'hp:a', 'amehp_item_ab12cd34' ],
	'K5 an item at menu position 10 is drawn above an arranged item sitting at 100, because that is where the site puts it'
);

is(
	logic.sortItems( [ { key: 'hp:a', order: 10 } ], [ 'hp:gone' ] ).map( ( i ) => i.key ),
	[ 'hp:a' ],
	'K6 an arrangement naming an item the site no longer has does not disturb the rest'
);

/*
 * The reported bug, drawn in the panel. The same menu and the same arrangement
 * as migration-tests.php G1-G4, so a change to one side that is not made to the
 * other fails here.
 */
const ARRANGED = [ 'hp:user_account', 'hp:messages_thread', 'hp:listings_edit', 'hp:user_edit_settings', 'hp:user_logout' ];

is(
	logic.sortItems(
		[
			{ key: 'hp:user_account', order: 10 },
			{ key: 'hp:listings_edit', order: 20 },
			{ key: 'hp:messages_thread', order: 30 },
			{ key: 'wc:orders', order: 40 },
			{ key: 'hp:user_edit_settings', order: 50 },
			{ key: 'hp:user_logout', order: 1000 },
		],
		ARRANGED
	).map( ( i ) => i.key ),
	[ 'hp:user_account', 'hp:messages_thread', 'hp:listings_edit', 'wc:orders', 'hp:user_edit_settings', 'hp:user_logout' ],
	'K7 "Placed Orders" is drawn where the site renders it, not below Sign Out'
);

is(
	logic
		.sortItems(
			[
				{ key: 'hp:user_account', order: 10 },
				{ key: 'hp:listings_edit', order: 20 },
				{ key: 'hp:messages_thread', order: 30 },
				{ key: 'wc:orders', order: 40 },
				{ key: 'hp:user_edit_settings', order: 50 },
				{ key: 'hp:user_logout', order: 1000 },
			],
			ARRANGED
		)
		.map( ( i ) => i.key )
		.filter( ( key ) => -1 !== ARRANGED.indexOf( key ) ),
	ARRANGED,
	'K8 and the arrangement itself is drawn exactly as it was saved'
);

is(
	logic.sortItems(
		[
			{ key: 'hp:user_account', order: 10 },
			{ key: 'wc:subscriptions', order: 42 },
			{ key: 'wc:orders', order: 40 },
			{ key: 'hp:user_edit_settings', order: 50 },
		],
		[ 'hp:user_account', 'hp:user_edit_settings' ]
	).map( ( i ) => i.key ),
	[ 'hp:user_account', 'wc:orders', 'wc:subscriptions', 'hp:user_edit_settings' ],
	'K9 two unplaced items keep their own order and both land inside the arrangement'
);

finish();
