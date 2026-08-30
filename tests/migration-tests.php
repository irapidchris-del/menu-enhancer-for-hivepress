<?php
/**
 * Migration QA harness for Account Menu Enhancer for HivePress.
 * Stubs WordPress/HivePress, then drives the real migration functions.
 *
 * These have their own file because they are the highest-value target in the
 * plugin and the hardest to check any other way: each one runs ONCE, on an
 * admin request, against options nobody is looking at, and there is no way to
 * run one again by hand afterwards. A migration that writes the wrong thing
 * does not fail - it succeeds at the wrong answer, records itself as done, and
 * the owner finds their menu rearranged with nothing to point at.
 *
 * Every migration is checked for four things: that it produces the right
 * result, that running it twice changes nothing further, that it never
 * overwrites a value the newer key already holds, and that it does not reorder
 * anything.
 *
 * Env: see stubs.php.
 *
 * @package AccountMenuEnhancer\Tests
 */

require __DIR__ . '/stubs.php';

require AMEHP_TEST_PLUGIN_FILE;

/**
 * Clears the site state before a scenario.
 */
function amehp_test_reset() {
	amehp_test_reset_globals();

	$GLOBALS['_option_writes'] = [];
}

/**
 * Takes a copy of the whole option store, so a "nothing changed" assertion can
 * be made on the store as a whole rather than on the keys somebody remembered
 * to check.
 *
 * @return array
 */
function options_snapshot() {
	return $GLOBALS['_options'];
}

echo "=== Account Menu Enhancer migration QA ===\n";

/* ===================== A. the version record ===================== */
echo "\n[A] amehp_maybe_migrate\n";

amehp_test_reset();
$GLOBALS['_can'] = [];
amehp_maybe_migrate();
ok( ! array_key_exists( 'amehp_version', $GLOBALS['_options'] ), 'A1 a user who cannot manage options never triggers a migration' );

amehp_test_reset();
amehp_maybe_migrate();
ok( AMEHP_VERSION === get_option( 'amehp_version' ), 'A2 the version record is the version that just ran, not the last one that had a migration' );

amehp_test_reset();
$GLOBALS['_options']['amehp_version'] = AMEHP_VERSION;
$GLOBALS['_options']['hp_hppam_items'] = [ 'listings_edit' ];
amehp_maybe_migrate();
ok(
	! array_key_exists( 'hp_amehp_persistent_items', $GLOBALS['_options'] ),
	'A3 an install already on this version does no work at all'
);

amehp_test_reset();
$GLOBALS['_options']['amehp_version']  = '3.0.0';
$GLOBALS['_options']['hp_amehp_merge_menus'] = '1';
amehp_maybe_migrate();
ok(
	! array_key_exists( 'hp_amehp_wc_integration', $GLOBALS['_options'] ),
	'A4 a migration already past is not re-run just because a later one is due'
);

amehp_test_reset();
amehp_maybe_migrate();
$before = options_snapshot();
amehp_maybe_migrate();
ok( $before === options_snapshot(), 'A5 the whole pipeline is idempotent on a fresh install' );

/* ===================== B. the version 1 settings ===================== */
echo "\n[B] amehp_migrate_v1_settings\n";

amehp_test_reset();
amehp_migrate_v1_settings();
ok( [] === options_snapshot(), 'B1 with no legacy blob there is nothing to migrate and nothing is written' );

amehp_test_reset();
$GLOBALS['_options']['amehp_settings'] = [
	'enable_integration'        => 1,
	'woocommerce_items_to_hide' => [ 'downloads', 'edit-address' ],
	'custom_menu_items'         => [
		[
			'label'    => 'Help',
			'type'     => 'page',
			'page_id'  => 12,
			'menu'     => 'hivepress',
			'position' => 40,
		],
		[
			'label' => 'Blog',
			'url'   => 'https://example.org/blog',
		],
		[ 'label' => '' ],
	],
];
amehp_migrate_v1_settings();
ok( '1' === get_option( 'hp_amehp_merge_menus' ), 'B2 the integration toggle carries over' );
ok( '' === get_option( 'hp_amehp_unify_account' ), 'B3 an upgraded site keeps its existing page layout rather than being switched to the new one' );
ok( [ 'wc:downloads', 'wc:edit-address' ] === get_option( 'hp_amehp_hidden_items' ), 'B4 hidden WooCommerce items are re-keyed to the new prefix form' );

$custom = get_option( 'hp_amehp_custom_items' );
ok( 2 === count( $custom ), 'B5 a row with no label is dropped, since it could never have rendered' );
ok( 'page:12' === $custom[0]['link'] && 'hivepress' === $custom[0]['menus'] && 40 === $custom[0]['order'], 'B6 a page link, its menu and its position all carry over' );
ok( 'https://example.org/blog' === $custom[1]['url'] && 'both' === $custom[1]['menus'], 'B7 a plain URL item defaults to both menus' );

/* ===================== C. the 3.0.0 WooCommerce merge ===================== */
echo "\n[C] amehp_migrate_v3_settings: the two checkboxes become one\n";

amehp_test_reset();
amehp_migrate_v3_settings();
ok(
	! array_key_exists( 'hp_amehp_wc_integration', $GLOBALS['_options'] ),
	'C1 with neither old checkbox stored, nothing is written and a fresh install keeps its plain default'
);

foreach (
	[
		[ '1', '', '1', 'C2 merging on, unifying off' ],
		[ '', '1', '1', 'C3 merging off, unifying on' ],
		[ '1', '1', '1', 'C4 both on' ],
		[ '', '', '', 'C5 both off' ],
	] as $case
) {
	list( $merge, $unify, $expected, $label ) = $case;

	amehp_test_reset();
	$GLOBALS['_options']['hp_amehp_merge_menus']   = $merge;
	$GLOBALS['_options']['hp_amehp_unify_account'] = $unify;
	amehp_migrate_v3_settings();
	ok( $expected === get_option( 'hp_amehp_wc_integration' ), $label . ' -> the merged switch is ' . ( $expected ? 'on' : 'off' ) );
}

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_merge_menus'] = '1';
amehp_migrate_v3_settings();
ok( '1' === get_option( 'hp_amehp_wc_integration' ), 'C6 only one of the pair stored is still enough to decide' );
ok( '1' === get_option( 'hp_amehp_merge_menus' ), 'C7 the legacy value is kept, so rolling back stays possible' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_merge_menus']   = '1';
$GLOBALS['_options']['hp_amehp_unify_account'] = '';
amehp_migrate_v3_settings();
$before = options_snapshot();
amehp_migrate_v3_settings();
ok( $before === options_snapshot(), 'C8 running it twice changes nothing further' );

/* ===================== D. the Persistent Account Menu keys ===================== */
echo "\n[D] amehp_migrate_v3_settings: the hp_hppam_* keys\n";

amehp_test_reset();
$GLOBALS['_options']['hp_hppam_items']                      = [ 'listings_edit', 'bookings_view' ];
$GLOBALS['_options']['hp_hppam_known_items']                = [ 'listings_edit', 'bookings_view', 'payouts_view' ];
$GLOBALS['_options']['hp_hppam_button_label_listings_edit'] = 'Add your first listing';
$GLOBALS['_options']['hp_hppam_button_url_listings_edit']   = 'https://example.org/add';
amehp_migrate_v3_settings();
ok( [ 'listings_edit', 'bookings_view' ] === get_option( 'hp_amehp_persistent_items' ), 'D1 the ticked item list carries over' );
ok( [ 'listings_edit', 'bookings_view', 'payouts_view' ] === get_option( 'hp_amehp_persistent_known_items' ), 'D2 and the record of what has ever been offered, so nothing the owner unticked is switched back on' );
ok( 'Add your first listing' === get_option( 'hp_amehp_button_label_listings_edit' ), 'D3 a per-page button label carries over' );
ok( 'https://example.org/add' === get_option( 'hp_amehp_button_url_listings_edit' ), 'D4 and its URL' );
ok( 'Add your first listing' === get_option( 'hp_hppam_button_label_listings_edit' ), 'D5 the old key is left in place rather than moved' );

amehp_test_reset();
$GLOBALS['_options']['hp_hppam_button_label_listings_edit'] = 'The old wording';
$GLOBALS['_options']['hp_amehp_button_label_listings_edit'] = 'The wording I have since typed';
amehp_migrate_v3_settings();
ok(
	'The wording I have since typed' === get_option( 'hp_amehp_button_label_listings_edit' ),
	'D6 a value the new key already holds is never overwritten by the old one'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_button_label_listings_edit'] = '';
$GLOBALS['_options']['hp_hppam_button_label_listings_edit'] = 'The old wording';
amehp_migrate_v3_settings();
ok(
	'' === get_option( 'hp_amehp_button_label_listings_edit' ),
	'D7 including a value the owner deliberately cleared, which is a choice and not an absence'
);

amehp_test_reset();
$GLOBALS['_options']['hp_hppam_items']                      = [ 'listings_edit' ];
$GLOBALS['_options']['hp_hppam_button_url_payouts_view']    = 'https://example.org/p';
$GLOBALS['_options']['hp_hppam_button_label_messages_thread'] = 'Say hello';
amehp_migrate_v3_settings();
$before = options_snapshot();
amehp_migrate_v3_settings();
ok( $before === options_snapshot(), 'D8 running it twice changes nothing further' );

amehp_test_reset();
$GLOBALS['_options']['hp_hppam_button_label_not_a_managed_item'] = 'Nowhere';
amehp_migrate_v3_settings();
ok(
	! array_key_exists( 'hp_amehp_button_label_not_a_managed_item', $GLOBALS['_options'] ),
	'D9 only the fixed managed set is copied, so a stray key is not carried into the new namespace'
);

/* ===================== E. the 3.3.0 stable ids ===================== */
echo "\n[E] amehp_migrate_v330_settings\n";

amehp_test_reset();
amehp_migrate_v330_settings();
ok( [] === options_snapshot(), 'E1 with no custom items there is nothing to stamp and nothing is written' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = 'not an array';
amehp_migrate_v330_settings();
ok( 'not an array' === get_option( 'hp_amehp_custom_items' ), 'E2 a corrupted option is left exactly as found rather than replaced' );

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'One',
		'url'   => '/one',
	],
	[
		'label' => 'Two',
		'url'   => '/two',
	],
	[
		'label' => 'Three',
		'url'   => '/three',
	],
];
$GLOBALS['_options']['hp_amehp_menu_order']   = 'hp:listings_edit,amehp_item_2,wc:downloads,amehp_item_1,amehp_item_3';
amehp_migrate_v330_settings();

$rows = get_option( 'hp_amehp_custom_items' );
ok( 3 === count( $rows ), 'E3 every row is still there' );
ok( 'One' === $rows[0]['label'] && 'Two' === $rows[1]['label'] && 'Three' === $rows[2]['label'], 'E4 in the order they were stored, because a migration must not reorder anything' );

$uids = [ $rows[0]['uid'], $rows[1]['uid'], $rows[2]['uid'] ];
ok( 3 === count( array_unique( $uids ) ), 'E5 every row gets an id of its own' );
ok( 3 === count( array_filter( $uids, function ( $uid ) { return (bool) preg_match( '/^[A-Za-z0-9]{6,32}$/', $uid ); } ) ), 'E6 in the shape get_custom_items() will accept' );

$order = explode( ',', get_option( 'hp_amehp_menu_order' ) );
ok(
	[ 'hp:listings_edit', 'amehp_item_' . $uids[1], 'wc:downloads', 'amehp_item_' . $uids[0], 'amehp_item_' . $uids[2] ] === $order,
	'E7 the saved arrangement is repointed at the new names, every entry still in its own slot'
);
ok( 'hp:listings_edit' === $order[0] && 'wc:downloads' === $order[2], 'E8 and the entries that are not custom items are untouched' );

$before = options_snapshot();
amehp_migrate_v330_settings();
ok( $before === options_snapshot(), 'E9 running it twice changes nothing further: the ids are not re-rolled and the order is not rewritten again' );

// The load-bearing agreement: the names the migration writes into the order are
// the names get_custom_items() will answer to. If these two ever disagree, the
// arrangement points at items that do not exist and the menu silently reverts.
require AMEHP_TEST_PLUGIN_DIR . '/includes/components/class-amehp-menu-enhancer.php';

$GLOBALS['_components']['amehp_menu_enhancer'] = new HivePress\Components\Amehp_Menu_Enhancer();

$keys = array_keys( call_priv( $GLOBALS['_components']['amehp_menu_enhancer'], 'get_custom_items' ) );
ok(
	[ 'amehp_item_' . $uids[0], 'amehp_item_' . $uids[1], 'amehp_item_' . $uids[2] ] === $keys,
	'E10 and the component answers to exactly the names the migration wrote into the order'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[
		'label' => 'Already stamped',
		'uid'   => 'ab12cd34ef56',
	],
	[ 'label' => 'Not yet' ],
];
$GLOBALS['_options']['hp_amehp_menu_order']   = 'amehp_item_ab12cd34ef56,amehp_item_2';
amehp_migrate_v330_settings();

$rows = get_option( 'hp_amehp_custom_items' );
ok( 'ab12cd34ef56' === $rows[0]['uid'], 'E11 a row that already has an id keeps it' );
ok( 'ab12cd34ef56' !== $rows[1]['uid'] && preg_match( '/^[A-Za-z0-9]{6,32}$/', $rows[1]['uid'] ), 'E12 and the row beside it gets one of its own' );
ok(
	'amehp_item_ab12cd34ef56,amehp_item_' . $rows[1]['uid'] === get_option( 'hp_amehp_menu_order' ),
	'E13 only the entry that was renamed is rewritten'
);

amehp_test_reset();

// A row with no label is skipped by get_custom_items() but still COUNTED by it,
// so the migration has to count it too or every positional name after it would
// be repointed at the wrong row.
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[ 'label' => '' ],
	[
		'label' => 'The second row',
		'url'   => '/two',
	],
];
$GLOBALS['_options']['hp_amehp_menu_order']   = 'amehp_item_2';
amehp_migrate_v330_settings();

$rows = get_option( 'hp_amehp_custom_items' );
ok(
	'amehp_item_' . $rows[1]['uid'] === get_option( 'hp_amehp_menu_order' ),
	'E14 an unlabelled row is counted when working out the old positional names, exactly as get_custom_items() counts it'
);

$GLOBALS['_components']['amehp_menu_enhancer'] = new HivePress\Components\Amehp_Menu_Enhancer();

ok(
	[ 'amehp_item_' . $rows[1]['uid'] ] === array_keys( call_priv( $GLOBALS['_components']['amehp_menu_enhancer'], 'get_custom_items' ) ),
	'E15 and the two still agree row for row'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [ [ 'label' => 'Only one' ] ];
amehp_migrate_v330_settings();
ok(
	! array_key_exists( 'hp_amehp_menu_order', $GLOBALS['_options'] ),
	'E16 with no saved arrangement the ids are stamped and no arrangement is invented'
);

amehp_test_reset();
$GLOBALS['_options']['hp_amehp_custom_items'] = [
	[ 'label' => 'One' ],
	'a stray string where a row should be',
	[ 'label' => 'Three' ],
];
$GLOBALS['_options']['hp_amehp_menu_order']   = 'amehp_item_1,amehp_item_3';
amehp_migrate_v330_settings();

$rows = get_option( 'hp_amehp_custom_items' );
ok( 'a stray string where a row should be' === $rows[1], 'E17 a row that is not a row is left alone' );
ok(
	'amehp_item_' . $rows[0]['uid'] . ',amehp_item_' . $rows[2]['uid'] === get_option( 'hp_amehp_menu_order' ),
	'E18 and the rows either side of it still get the positions they had'
);

/* ===================== F. the three together ===================== */
echo "\n[F] a real upgrade, all three in one pass\n";

amehp_test_reset();
$GLOBALS['_options']['amehp_settings'] = [
	'enable_integration' => 1,
	'custom_menu_items'  => [
		[
			'label' => 'Help',
			'url'   => 'https://example.org/help',
		],
	],
];
$GLOBALS['_options']['hp_hppam_items']                      = [ 'listings_edit' ];
$GLOBALS['_options']['hp_hppam_button_label_listings_edit'] = 'Add one';
$GLOBALS['_options']['hp_amehp_menu_order']                 = 'amehp_item_1,hp:listings_edit';

amehp_maybe_migrate();

ok( '1' === get_option( 'hp_amehp_wc_integration' ), 'F1 a 1.x site ends up with the WooCommerce integration on' );
ok( [ 'listings_edit' ] === get_option( 'hp_amehp_persistent_items' ), 'F2 with its Persistent Account Menu choices carried over' );
ok( 'Add one' === get_option( 'hp_amehp_button_label_listings_edit' ), 'F3 and its button wording' );

$rows = get_option( 'hp_amehp_custom_items' );
ok(
	'amehp_item_' . $rows[0]['uid'] . ',hp:listings_edit' === get_option( 'hp_amehp_menu_order' ),
	'F4 and its one custom item stamped and repointed, with the rest of the arrangement untouched'
);
ok( AMEHP_VERSION === get_option( 'amehp_version' ), 'F5 and the version recorded' );

$before = options_snapshot();
amehp_maybe_migrate();
ok( $before === options_snapshot(), 'F6 a second admin request changes nothing' );

amehp_test_finish();
