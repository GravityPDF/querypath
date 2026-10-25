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

	/**
	 * Whether libxml's HTML parser follows HTML5, as it does from 2.14.
	 *
	 * Such a parser reads `<?...>` as a comment rather than a processing instruction, and reports no
	 * parse errors. Probed rather than read from LIBXML_VERSION, which is the version PHP was built
	 * against, not necessarily the one it runs with.
	 *
	 * @return bool
	 */
	protected static function libxmlParsesHtmlAsHtml5(): bool
	{
		static $html5;

		if ($html5 === null) {
			$document = new \DOMDocument();
			@$document->loadHTML('<p><?x y?></p>');
			$html5 = ! $document->getElementsByTagName('p')->item(0)->firstChild instanceof \DOMProcessingInstruction;
		}

		return $html5;
	}

	protected function requireLegacyHtmlParser()
	{
		if (self::libxmlParsesHtmlAsHtml5()) {
			$this->markTestSkipped('libxml ' . LIBXML_DOTTED_VERSION . ' parses HTML as HTML5.');
		}
	}
}
