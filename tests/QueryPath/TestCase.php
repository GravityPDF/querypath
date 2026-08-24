<?php
/**
 * @file
 *
 * The master test case.
 */

namespace QueryPathTests;

class TestCase extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	public const DATA_FILE_XML = 'tests/data.xml';
	public const DATA_FILE_HTML = 'tests/data.html';

	/**
	 * Capture everything a callback prints.
	 *
	 * Many QueryPath methods print rather than return (writeHTML(), writeHTML5(), writeXML(), ...)
	 * and phpunit.xml sets beStrictAboutOutputDuringTests, so exercising them means buffering.
	 *
	 * @param callable $callback
	 *
	 * @return string
	 */
	protected function capture(callable $callback): string
	{
		ob_start();
		try {
			$callback();

			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}
}
