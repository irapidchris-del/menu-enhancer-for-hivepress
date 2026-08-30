<?php
/**
 * Runs the whole test matrix.
 *
 * The plugin behaves differently depending on which plugins and HivePress
 * extensions are present - WooCommerce decides which items appear in which
 * menu, the absorbed Persistent Account Menu plugin can still be active
 * alongside, and an absent extension means an absent route - so the logic
 * tests are run once per meaningful combination rather than only in the
 * default one.
 *
 * Usage: php tests/run.php
 *
 * THIS RUNNER COVERS THE PHP ONLY. The settings screen's live preview is
 * JavaScript, and its logic has its own matrix in tests/run-js.js - that is
 * where the 3.3.1 drag bug would have been caught, and nothing here can see it.
 * Run both. They are separate runners on purpose: this one needs nothing but
 * PHP, and folding Node into it would either make it unrunnable without Node or
 * let it report a pass while quietly skipping the JavaScript.
 *
 *     "C:\Program Files (x86)\php-8.1.32-nts-Win32-vs16-x64\php.exe" tests/run.php
 *     node tests/run-js.js
 *
 * @package AccountMenuEnhancer\Tests
 */

$runs = [
	'logic: baseline'                     => [ 'logic-tests.php', [] ],
	'logic: no WooCommerce'               => [ 'logic-tests.php', [ 'AMEHP_WC' => 'absent' ] ],
	'logic: Persistent Account Menu active' => [ 'logic-tests.php', [ 'AMEHP_PAM' => '1' ] ],
	'logic: no Bookings'                  => [ 'logic-tests.php', [ 'AMEHP_BOOKINGS' => 'absent' ] ],
	'logic: no Memberships'               => [ 'logic-tests.php', [ 'AMEHP_MEMBERSHIPS' => 'absent' ] ],
	'logic: no WooCommerce, no Bookings'  => [ 'logic-tests.php', [ 'AMEHP_WC' => 'absent', 'AMEHP_BOOKINGS' => 'absent' ] ],
	'migrations'                          => [ 'migration-tests.php', [] ],
	'migrations: no WooCommerce'          => [ 'migration-tests.php', [ 'AMEHP_WC' => 'absent' ] ],
];

$total_passed = 0;
$total_failed = 0;
$failed_runs  = [];

foreach ( $runs as $label => $run ) {
	list( $script, $env ) = $run;

	// Environment is passed through putenv() rather than a `NAME=value cmd`
	// shell prefix: that syntax is bash-only, and on Windows cmd.exe it makes
	// every variant run die with "'AMEHP_WC' is not recognized as an internal
	// or external command" while still reporting a plain FAIL. putenv() is
	// inherited by the child process on both platforms.
	foreach ( $env as $key => $value ) {
		putenv( $key . '=' . $value );
	}

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/' . $script );

	$output = [];
	exec( $command . ' 2>&1', $output, $status );

	// Never leak one variant's environment into the next.
	foreach ( array_keys( $env ) as $key ) {
		putenv( $key );
	}

	$summary = '';

	foreach ( $output as $line ) {
		if ( 0 === strpos( $line, 'RESULT:' ) ) {
			$summary = $line;
		}
	}

	if ( preg_match( '/RESULT: (\d+) passed, (\d+) failed/', $summary, $m ) ) {
		$total_passed += (int) $m[1];
		$total_failed += (int) $m[2];
	}

	if ( 0 !== $status ) {
		$failed_runs[] = $label;

		// Show the detail only for runs that failed, so a green matrix stays
		// readable but a failure is never silent.
		echo "\n--- $label ---\n" . implode( "\n", $output ) . "\n";
	}

	printf( "%-42s %s\n", $label, 0 === $status ? 'OK   ' . $summary : 'FAIL ' . $summary );

	$output = [];
}

echo str_repeat( '-', 60 ) . "\n";
printf( "TOTAL: %d passed, %d failed\n", $total_passed, $total_failed );

if ( $failed_runs ) {
	echo 'Failing runs: ' . implode( ', ', $failed_runs ) . "\n";
}

exit( $failed_runs || $total_failed ? 1 : 0 );
