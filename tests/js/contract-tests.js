/**
 * QA harness for the contract between the three files this split created.
 *
 * The logic tests can be perfectly green while the BROWSER gets nothing: the
 * whole preview is skipped if window.amehpPreviewLogic is not on the page when
 * admin-preview.js runs, and nothing in Node would notice. So this run pins the
 * seams rather than the logic - that preview-logic.js is enqueued, that
 * admin-preview.js declares it as a dependency so the load order is guaranteed,
 * that the browser file no longer carries its own copy of anything that moved,
 * and that the logic file has stayed pure enough to be loadable outside a
 * browser at all.
 *
 * It also pins where the harness lives. package.ps1 excludes a folder named
 * "test" or "tests" and nothing else, so a test file anywhere but under tests/
 * ships to customers.
 *
 * @package AccountMenuEnhancer\Tests
 */

'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );

const { logic, source } = require( './load-logic' );
const { ok, is, finish, section } = require( './harness' );

const PLUGIN = path.join( __dirname, '..', '..' );

const read = ( relative ) => fs.readFileSync( path.join( PLUGIN, relative ), 'utf8' );

/**
 * A file with its comments taken out, so a token that only appears in prose
 * does not read as a token that appears in code. Block comments are stripped
 * first, then line comments; neither pattern can be confused by the regular
 * expressions in these files, none of which contain a double slash.
 *
 * @param {string} text File contents.
 * @return {string}
 */
function code( text ) {
	return text.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' );
}

const LOGIC_SOURCE = read( 'assets/js/preview-logic.js' );
const PREVIEW_SOURCE = read( 'assets/js/admin-preview.js' );
const COMPONENT_SOURCE = read( 'includes/components/class-amehp-menu-enhancer.php' );

process.stdout.write( '=== Account Menu Enhancer preview logic: the module contract ===\n' );

/* ===================== L. what the module publishes ===================== */
section( '[L] the published API' );

ok( source.endsWith( 'preview-logic.js' ), 'L1 the tests load the shipped file, not a copy of it' );

[
	'hex',
	'absint',
	'iconName',
	'menuWeight',
	'iconSize',
	'iconSpacing',
	'stroke',
	'customItemKey',
	'customItemKeys',
	'parseOrder',
	'mergeOrder',
	'isWooItem',
	'includesCatalogueEntry',
	'catalogueItems',
	'menusDiverge',
	'itemLabel',
	'includesCustomItem',
	'customItemOrder',
	'sortItems',
].forEach( function ( name ) {
	ok( 'function' === typeof logic[ name ], 'L2 ' + name + '() is published' );
} );

is( Object.keys( logic ).length, 19, 'L3 and nothing else is - an untested export is an export nobody is watching' );

/* ===================== M. the module has stayed pure ===================== */
section( '[M] preview-logic.js touches nothing but its arguments' );

const LOGIC_CODE = code( LOGIC_SOURCE );

[ 'document', 'jQuery', 'querySelector', 'localStorage', 'addEventListener', 'setTimeout', 'XMLHttpRequest', 'fetch(' ].forEach( function ( token ) {
	ok( -1 === LOGIC_CODE.indexOf( token ), 'M1 no "' + token + '" in preview-logic.js - the moment it touches the page it stops being testable' );
} );

ok( -1 === LOGIC_CODE.indexOf( 'require(' ), 'M2 and no require(), because the shipped file is browser JavaScript' );
ok( -1 === LOGIC_CODE.indexOf( 'module.exports' ), 'M3 nor module.exports - the test shape must not leak into shipped code' );
ok( -1 !== LOGIC_CODE.indexOf( 'window.amehpPreviewLogic = api' ), 'M4 it publishes exactly one namespaced global' );

/* ===================== N. the browser still gets the logic ===================== */
section( '[N] the seam between the two scripts' );

const PREVIEW_CODE = code( PREVIEW_SOURCE );

ok( -1 !== PREVIEW_CODE.indexOf( 'window.amehpPreviewLogic' ), 'N1 admin-preview.js reads the published global' );
ok( -1 !== PREVIEW_CODE.indexOf( '! logic' ), 'N2 and stands down when it is missing, rather than failing inside a paint' );

// Anything still defined in admin-preview.js is a second copy that will drift.
[ 'function hex(', 'function absint(', 'function iconName(', 'var STROKES' ].forEach( function ( token ) {
	ok( -1 === PREVIEW_CODE.indexOf( token ), 'N3 admin-preview.js no longer defines "' + token.trim() + '"' );
} );

ok( -1 === PREVIEW_CODE.indexOf( "'amehp_item_' +" ), 'N4 nor does it build a custom item key of its own' );

/* ===================== O. the load order is guaranteed ===================== */
section( '[O] the enqueue' );

ok( -1 !== COMPONENT_SOURCE.indexOf( "'amehp-preview-logic'" ), 'O1 the component enqueues the logic script' );
ok( -1 !== COMPONENT_SOURCE.indexOf( "assets/js/preview-logic.js" ), 'O2 pointing at the file the tests load' );

const DEPS = COMPONENT_SOURCE.match( /\[ 'jquery', 'jquery-ui-sortable'[^\]]*\]/ );

ok( null !== DEPS, 'O3 the preview script still declares its dependencies' );
ok( null !== DEPS && -1 !== DEPS[ 0 ].indexOf( "'amehp-preview-logic'" ), 'O4 and the logic script is one of them, so it is always on the page first' );

/*
 * Every name the browser reads out of the localised blob has to be a name the
 * component actually puts in it. A misspelling on either side is undefined at
 * runtime with no error: the panel simply draws the wrong menu, which is the
 * failure this whole harness exists for. hpMenuWcKeys is the one added in
 * 3.3.10, and reading it wrong would silently restore the bug it fixed.
 */
const BROWSER_CODE = PREVIEW_CODE + code( read( 'assets/js/backend.js' ) );

[ 'itemOrders', 'customOrders', 'hpMenuWcKeys', 'placeholderPages', 'itemLabels', 'wcItemLabels' ].forEach( function ( name ) {
	ok( -1 !== BROWSER_CODE.indexOf( name ), 'O5 a browser script reads "' + name + '"' );
	ok( -1 !== COMPONENT_SOURCE.indexOf( "'" + name + "'" ), 'O6 and the component sends "' + name + '"' );
} );

/*
 * The reverse check, which is what this section was missing.
 *
 * Until 3.3.11 the component also sent "menuOrder", the owner's saved
 * arrangement, and no browser script ever read it. That was recorded here as a
 * comment saying it was deliberately excluded from the list above - which
 * documented the dead payload rather than preventing it, and left it sitting in
 * every page build for several releases.
 *
 * The arrangement must come from the hidden form field (admin-preview.js,
 * storedOrder()), because that changes as the owner drags while a localised
 * copy is frozen at page build. So sending it is not merely wasteful, it is a
 * second source of truth that can disagree with the first.
 */
/*
 * Match the array KEY, not any mention of the name. The component carries a
 * comment explaining why the payload was removed, and that comment quotes the
 * name - a bare indexOf( "'menuOrder'" ) fails on the explanation itself, which
 * is how the first version of this assertion behaved.
 */
ok(
	! /'menuOrder'\s*=>/.test( COMPONENT_SOURCE ),
	'O7 the component does not send "menuOrder" - the live form field is the only source for the arrangement'
);

/* ===================== Q. the WooCommerce-only hidden list ===================== */
section( '[Q] the setting that hides from one menu' );

/*
 * "Also Hidden from the WooCommerce Menu", added in 3.3.12. Its whole value is
 * that the HivePress menu is untouched, so the seam worth pinning is which
 * methods are allowed to read it: a second reader on the HivePress side would
 * turn it into a duplicate of "Hidden Items" with no test failing to say so.
 */
const SETTINGS_SOURCE = read( 'includes/configs/settings.php' );

ok( -1 !== SETTINGS_SOURCE.indexOf( "'amehp_hidden_wc_items'" ), 'Q1 the setting is registered' );
ok( -1 !== SETTINGS_SOURCE.indexOf( "'amehp_wc_menu_items'" ), 'Q2 offering the shorter list, of items that can be in that menu at all' );
ok( -1 !== BROWSER_CODE.indexOf( 'hp_amehp_hidden_wc_items' ), 'Q3 and the preview reads it off the page, so the panels follow it before anything is saved' );

const READER = 'get_wc_hidden_keys';

ok( -1 !== COMPONENT_SOURCE.indexOf( READER ), 'Q4 the component has a reader for it' );

/**
 * One method's source, from its signature to the next one named.
 *
 * @param {string} from Opening signature.
 * @param {string} to The signature that follows it.
 * @return {string}
 */
function method( from, to ) {
	return COMPONENT_SOURCE.slice( COMPONENT_SOURCE.indexOf( from ), COMPONENT_SOURCE.indexOf( to ) );
}

const HP_MENU = method( 'public function alter_hp_menu(', 'public function alter_wc_menu(' );
const HP_ITEMS = method( 'public function alter_hp_menu_items(', 'protected function record_seen_items(' );
const WC_MENU = method( 'public function alter_wc_menu(', 'public function alter_wc_endpoint_url(' );

ok( HP_MENU.length > 100 && HP_ITEMS.length > 100 && WC_MENU.length > 100, 'Q5 the three menu methods are all still where this test looks for them' );
ok( -1 !== WC_MENU.indexOf( READER ), 'Q6 alter_wc_menu() reads the list' );
ok( -1 === HP_MENU.indexOf( READER ), 'Q7 alter_hp_menu() does not, or an item hidden from one menu would vanish from both' );
ok( -1 === HP_ITEMS.indexOf( READER ), 'Q8 nor does alter_hp_menu_items(), which is the other stage that hides things in that menu' );

/* ===================== P. the harness never ships ===================== */
section( '[P] where the harness lives' );

ok( -1 !== __dirname.replace( /\\/g, '/' ).indexOf( '/tests/' ), 'P1 the harness sits under tests/, which package.ps1 excludes from the zip' );
ok( ! fs.existsSync( path.join( PLUGIN, 'package.json' ) ), 'P2 there is no package.json - Node built-ins only, and no lockfile to ship' );
ok( ! fs.existsSync( path.join( PLUGIN, 'node_modules' ) ), 'P3 and no node_modules' );

finish();
