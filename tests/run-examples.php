<?php
/**
 * @file
 *
 * Runs the examples and reports on which ones still work.
 *
 *     php tests/run-examples.php            # every example
 *     php tests/run-examples.php --offline  # only the ones that need no network
 *     php tests/run-examples.php --network  # only the ones that call a remote service
 *
 * The offline examples are also covered by the unit test suite, which is what
 * runs on a pull request. This script exists for the network examples, which are
 * too dependent on third parties to gate a pull request on, and for checking the
 * whole set by hand.
 *
 * Exits non-zero if any example fails.
 */

use QueryPathTests\ExampleRunner;

require_once __DIR__ . '/../vendor/autoload.php';

$group = $argv[1] ?? '--all';

switch ($group) {
	case '--offline':
		$examples = ExampleRunner::offline();
		$offline = true;
		break;

	case '--network':
		$examples = ExampleRunner::network();
		$offline = false;
		break;

	case '--all':
		$examples = ExampleRunner::all();
		$offline = false;
		break;

	default:
		fwrite(STDERR, 'Usage: php tests/run-examples.php [--all|--offline|--network]' . PHP_EOL);
		exit(1);
}

printf('Running %d example(s)%s' . PHP_EOL . PHP_EOL, count($examples), $offline ? ' with networking disabled' : '');

$failures = [];

foreach ($examples as $name => $path) {
	printf('  %-56s ', $name);

	/* Network examples are always run with networking available. */
	$isOffline = $offline || ! in_array($name, ExampleRunner::NETWORK_EXAMPLES, true);

	$result = ExampleRunner::run($path, $isOffline);

	if ($result['passed']) {
		printf('ok (%d bytes)' . PHP_EOL, strlen(trim($result['output'])));
		continue;
	}

	echo 'FAILED' . PHP_EOL;

	$failures[$name] = $result;
}

if (count($failures) === 0) {
	printf(PHP_EOL . 'All %d example(s) passed.' . PHP_EOL, count($examples));
	exit(0);
}

printf(PHP_EOL . '%d of %d example(s) failed:' . PHP_EOL, count($failures), count($examples));

foreach ($failures as $name => $result) {
	printf(PHP_EOL . '--- %s ---' . PHP_EOL, $name);
	echo $result['reason'] . PHP_EOL;

	$output = trim($result['output']);

	if ($output !== '') {
		echo PHP_EOL . 'Output:' . PHP_EOL;
		echo substr($output, 0, 2000) . PHP_EOL;
	}
}

exit(1);
