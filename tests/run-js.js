/**
 * Runs the whole JavaScript test matrix.
 *
 * The companion to tests/run.php, and deliberately the same shape: one line per
 * run, a "RESULT: n passed, n failed" summary per run, a TOTAL, and a non-zero
 * exit when anything failed. It is a SEPARATE runner rather than another row in
 * run.php on purpose - run.php needs nothing but PHP, and folding Node into it
 * would mean the PHP harness could not be trusted on a machine without Node,
 * or would "pass" by quietly skipping the JavaScript.
 *
 * WHAT IT COVERS. assets/js/preview-logic.js, which holds the pure logic behind
 * the settings screen's live preview: the key a custom item is stored under,
 * the merge that protects off-screen items when the visible ones are dragged,
 * the routing of the site menu into the panels, and the small validators. That
 * code used to live inside the IIFE in assets/js/admin-preview.js, where
 * nothing could reach it - which is how the 3.3.1 drag bug survived both the
 * PHP harness (it cannot see JavaScript) and a browser check (it confirms one
 * page in one state, not a function across its cases).
 *
 * WHAT IT DOES NOT COVER: anything visual, and anything that reads the DOM.
 * Those stay in admin-preview.js and browser verification is still the only
 * check for them.
 *
 * Node 24 is installed at C:\Program Files\nodejs and is not on the Bash tool's
 * PATH, so add it first:
 *
 *     export PATH="$PATH:/c/Program Files/nodejs"
 *     cd <plugin dir> && node tests/run-js.js
 *
 * Node built-ins only. There is no package.json and there must not be one.
 *
 * @package AccountMenuEnhancer\Tests
 */

'use strict';

const path = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );

const RUNS = [
	[ 'logic: keys, merge, validators', 'logic-tests.js', {} ],
	[ 'menus: baseline', 'menu-tests.js', {} ],
	[ 'menus: no WooCommerce', 'menu-tests.js', { AMEHP_JS_WC: 'absent' } ],
	[ 'contract: module and enqueue', 'contract-tests.js', {} ],
];

let totalPassed = 0;
let totalFailed = 0;
const failedRuns = [];

RUNS.forEach( function ( run ) {
	const [ label, script, env ] = run;

	const result = spawnSync(
		process.execPath,
		[ path.join( __dirname, 'js', script ) ],
		{
			encoding: 'utf8',

			// The variant's own environment, on top of this one. Never mutate
			// process.env here: a leftover variable would silently change the
			// next run, which is the failure run.php's putenv() cleanup exists
			// to avoid.
			env: Object.assign( {}, process.env, env ),
		}
	);

	const output = ( result.stdout || '' ) + ( result.stderr || '' );

	let summary = '';

	output.split( '\n' ).forEach( function ( line ) {
		if ( 0 === line.indexOf( 'RESULT:' ) ) {
			summary = line.trim();
		}
	} );

	const counts = summary.match( /RESULT: (\d+) passed, (\d+) failed/ );

	if ( counts ) {
		totalPassed += parseInt( counts[ 1 ], 10 );
		totalFailed += parseInt( counts[ 2 ], 10 );
	}

	if ( 0 !== result.status ) {
		failedRuns.push( label );

		// Detail only for a run that failed, so a green matrix stays readable
		// but a failure is never silent.
		process.stdout.write( '\n--- ' + label + ' ---\n' + output + '\n' );
	}

	process.stdout.write( label.padEnd( 42 ) + ' ' + ( 0 === result.status ? 'OK   ' : 'FAIL ' ) + summary + '\n' );
} );

process.stdout.write( '-'.repeat( 60 ) + '\n' );
process.stdout.write( 'TOTAL: ' + totalPassed + ' passed, ' + totalFailed + ' failed\n' );

if ( failedRuns.length ) {
	process.stdout.write( 'Failing runs: ' + failedRuns.join( ', ' ) + '\n' );
}

process.exit( failedRuns.length || totalFailed ? 1 : 0 );
