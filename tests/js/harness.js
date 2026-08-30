/**
 * The shared assertion helpers for the JavaScript harness.
 *
 * Deliberately the same shape as the PHP harness in tests/stubs.php: one PASS
 * or FAIL line per check, a "RESULT: n passed, n failed" summary that
 * run-js.js collects, and a non-zero exit when anything failed. A failing
 * check therefore greps out of the output as one line that names itself.
 *
 * They live in their own file for the same reason the PHP stubs do: every test
 * file needs them, and two copies would drift.
 *
 * @package AccountMenuEnhancer\Tests
 */

'use strict';

const state = { passed: 0, failed: 0 };

/**
 * Records one check.
 *
 * @param {boolean} condition What is being asserted.
 * @param {string} label What it means, in words, so a failure explains itself.
 */
function ok( condition, label ) {
	if ( condition ) {
		state.passed++;

		process.stdout.write( '  PASS  ' + label + '\n' );
	} else {
		state.failed++;

		process.stdout.write( '  FAIL  ' + label + '\n' );
	}
}

/**
 * Records one check comparing two values structurally.
 *
 * Arrays and plain objects are compared by content, because almost everything
 * this harness pins returns a list. A mismatch prints both sides, so the line
 * that failed says what it got as well as what it wanted.
 *
 * @param {*} actual What the code returned.
 * @param {*} expected What it should have returned.
 * @param {string} label What it means.
 */
function is( actual, expected, label ) {
	const same = JSON.stringify( actual ) === JSON.stringify( expected );

	if ( same ) {
		ok( true, label );

		return;
	}

	ok( false, label + '  [got ' + JSON.stringify( actual ) + ', wanted ' + JSON.stringify( expected ) + ']' );
}

/**
 * Prints the summary line run-js.js collects, and exits.
 */
function finish() {
	process.stdout.write( '\n----------------------------------------\n' );
	process.stdout.write( 'RESULT: ' + state.passed + ' passed, ' + state.failed + ' failed\n' );

	process.exit( state.failed > 0 ? 1 : 0 );
}

/**
 * Prints a section heading.
 *
 * @param {string} title Heading.
 */
function section( title ) {
	process.stdout.write( '\n' + title + '\n' );
}

module.exports = { ok, is, finish, section };
