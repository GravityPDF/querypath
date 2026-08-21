<?php
/**
 * @file
 *
 * Smoke-tests the scripts in the examples/ directory.
 *
 * Each example is executed in its own subprocess and judged on three things: it
 * must exit cleanly, it must not emit a PHP diagnostic, and it must produce a
 * reasonable amount of output. That is enough to catch the ways examples
 * actually rot - a renamed method, a moved fixture, a selector that no longer
 * matches anything.
 */

namespace QueryPathTests;

use RuntimeException;

class ExampleRunner
{
	/**
	 * Examples that reach out to a third-party service.
	 *
	 * These are excluded from the unit test suite so that it stays fast and
	 * deterministic, and are run by .github/workflows/examples.yml instead.
	 *
	 * Anything not listed here is treated as offline and is run on every pull
	 * request. A new example that needs the network but is missing from this
	 * list will fail immediately rather than quietly becoming flaky, because
	 * offline runs have allow_url_fopen disabled.
	 */
	public const NETWORK_EXAMPLES = [
		'curl-xml-filter-and-retrieval',
		'filtering-by-text-content',
		'http-stream-xml-namespaces-and-linked-data',
		'parsing-rss-feed',
		'parsing-xml-from-url-and-dynamically-generating-html',
		'remote-filter-and-retrieval',
		'sparql-endpoint-query',
	];

	/** Output shorter than this means the example ran but produced nothing useful. */
	public const MINIMUM_OUTPUT_BYTES = 100;

	/** Seconds to allow an example before giving up on it. */
	public const TIMEOUT_OFFLINE = 30;
	public const TIMEOUT_NETWORK = 120;

	/** PHP diagnostics that should never appear in an example's output. */
	public const DIAGNOSTIC_PATTERN = '/\b(?:Fatal error|Parse error|Uncaught|Warning|Notice|Deprecated|Recoverable error)\b\s*:/';

	/**
	 * The absolute path to the examples directory.
	 *
	 * @return string
	 */
	public static function examplesDir(): string
	{
		return dirname(__DIR__, 2) . '/examples';
	}

	/**
	 * Every example, as a name => path map.
	 *
	 * @return array
	 */
	public static function all(): array
	{
		$examples = [];

		foreach (glob(self::examplesDir() . '/*/index.php') as $path) {
			$examples[basename(dirname($path))] = $path;
		}

		if (count($examples) === 0) {
			throw new RuntimeException('No examples found in ' . self::examplesDir());
		}

		ksort($examples);

		return $examples;
	}

	/**
	 * The examples that run without touching the network.
	 *
	 * @return array
	 */
	public static function offline(): array
	{
		return array_diff_key(self::all(), array_flip(self::NETWORK_EXAMPLES));
	}

	/**
	 * The examples that call a third-party service.
	 *
	 * @return array
	 */
	public static function network(): array
	{
		return array_intersect_key(self::all(), array_flip(self::NETWORK_EXAMPLES));
	}

	/**
	 * Run one example and report on how it went.
	 *
	 * @param string $path    Path to the example's index.php.
	 * @param bool   $offline Disable allow_url_fopen, so an example that is
	 *                        wrongly classified as offline fails loudly.
	 *
	 * @return array {
	 *
	 *     @type bool   $passed
	 *     @type string $reason Empty when the example passed.
	 *     @type string $output Combined stdout and stderr.
	 *     @type int    $status Exit code, or -1 if the example timed out.
	 * }
	 */
	public static function run(string $path, bool $offline = true): array
	{
		$timeout = $offline ? self::TIMEOUT_OFFLINE : self::TIMEOUT_NETWORK;

		$command = [PHP_BINARY, '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1'];

		if ($offline) {
			$command[] = '-d';
			$command[] = 'allow_url_fopen=0';
		}

		$command[] = $path;

		$result = self::execute($command, dirname($path), $timeout);

		return self::judge($result, $timeout);
	}

	/**
	 * Decide whether a completed run counts as a pass.
	 *
	 * @param array $result
	 * @param int   $timeout
	 *
	 * @return array
	 */
	private static function judge(array $result, int $timeout): array
	{
		$fail = function ($reason) use ($result) {
			return array_merge($result, ['passed' => false, 'reason' => $reason]);
		};

		if ($result['status'] === -1) {
			return $fail(sprintf('Timed out after %d seconds', $timeout));
		}

		if ($result['status'] !== 0) {
			return $fail(sprintf('Exited with status %d', $result['status']));
		}

		if (preg_match(self::DIAGNOSTIC_PATTERN, $result['output'], $matches) === 1) {
			return $fail('Emitted a PHP diagnostic: ' . trim($matches[0]));
		}

		$length = strlen(trim($result['output']));

		if ($length < self::MINIMUM_OUTPUT_BYTES) {
			return $fail(sprintf(
				'Produced only %d bytes of output, expected at least %d',
				$length,
				self::MINIMUM_OUTPUT_BYTES
			));
		}

		return array_merge($result, ['passed' => true, 'reason' => '']);
	}

	/**
	 * Run a command, capturing its output and enforcing a timeout.
	 *
	 * @param array  $command
	 * @param string $cwd
	 * @param int    $timeout
	 *
	 * @return array
	 */
	private static function execute(array $command, string $cwd, int $timeout): array
	{
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$pipes = [];

		/*
		 * proc_open() only accepts an array of arguments from PHP 7.4 onwards.
		 * Below that the command has to be escaped into a string by hand.
		 */
		$spec = PHP_VERSION_ID >= 70400 ? $command : implode(' ', array_map('escapeshellarg', $command));

		$process = proc_open($spec, $descriptors, $pipes, $cwd);

		if (! is_resource($process)) {
			throw new RuntimeException('Could not start ' . end($command));
		}

		fclose($pipes[0]);

		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$output = '';
		$deadline = time() + $timeout;
		$timedOut = false;
		$exitCode = 0;

		while (true) {
			$output .= (string) stream_get_contents($pipes[1]);
			$output .= (string) stream_get_contents($pipes[2]);

			$status = proc_get_status($process);

			/*
			 * Read the exit code from proc_get_status() rather than proc_close().
			 * The first call that sees the process finish is the one that reaps
			 * it; proc_close() afterwards reports -1.
			 */
			if (! $status['running']) {
				$exitCode = $status['exitcode'];
				break;
			}

			if (time() >= $deadline) {
				$timedOut = true;
				proc_terminate($process, 9);
				break;
			}

			/* Wait briefly rather than spinning on the pipes. */
			usleep(20000);
		}

		/* Drain whatever was still buffered when the process ended. */
		$output .= (string) stream_get_contents($pipes[1]);
		$output .= (string) stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		proc_close($process);

		return [
			'output' => $output,
			'status' => $timedOut ? -1 : $exitCode,
		];
	}
}
