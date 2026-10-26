<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/86
 *
 * libxml's HTML serializer writes a processing instruction as `<?target data>` and relies on the
 * closing "?" already being in the data. Masterminds never puts it there, so html() and writeHTML()
 * turned `<?php echo $title; ?>` from an html5qp() document into `<?php echo $title; >`.
 */

namespace QueryPathTests;

use DOMDocument;
use Masterminds\HTML5;
use QueryPath\Document;

class Issue86Test extends TestCase
{
	private const PI_FILE_HTML = 'tests/processing-instruction.html';

	private const HTML = '<html><body><h1><?php echo $title; ?></h1></body></html>';

	private const EXPECTED = '<h1><?php echo $title; ?></h1>';

	private const OPTIONS = ['format_output' => false];

	/**
	 * Every serializer in the issue's table, with the selector each one is called on.
	 *
	 * @return array
	 */
	public function serializerProvider(): array
	{
		return [
			'html'       => ['html', 'html'],
			'html5'      => ['html', 'html5'],
			'xml'        => ['html', 'xml'],
			'innerHTML'  => ['body', 'innerHTML'],
			'innerHTML5' => ['body', 'innerHTML5'],
			'innerXML'   => ['body', 'innerXML'],
		];
	}

	/**
	 * @dataProvider serializerProvider
	 *
	 * @param string $selector
	 * @param string $method
	 */
	public function testSerializersKeepTheTerminator($selector, $method)
	{
		$qp = html5qp(self::HTML, null, self::OPTIONS);

		$this->assertStringContainsString(self::EXPECTED, $qp->find($selector)->$method());
	}

	/**
	 * @return array
	 */
	public function writerProvider(): array
	{
		return [
			'writeHTML'  => ['writeHTML'],
			'writeHTML5' => ['writeHTML5'],
			'writeXML'   => ['writeXML'],
		];
	}

	/**
	 * @dataProvider writerProvider
	 *
	 * @param string $method
	 */
	public function testWritersKeepTheTerminator($method)
	{
		$qp = html5qp(self::HTML, null, self::OPTIONS);

		$output = $this->capture(function () use ($qp, $method) {
			$qp->$method();
		});

		$this->assertStringContainsString(self::EXPECTED, $output);
	}

	public function testWriteHtmlToAFileKeepsTheTerminator()
	{
		$qp   = html5qp(self::HTML, null, self::OPTIONS);
		$path = tempnam(sys_get_temp_dir(), 'qp86');

		try {
			$qp->writeHTML($path);
			$output = file_get_contents($path);
		} finally {
			unlink($path);
		}

		$this->assertStringContainsString(self::EXPECTED, $output);
	}

	/**
	 * html5qp() reads a path through a different Masterminds method than it reads markup.
	 */
	public function testDocumentLoadedFromAFileKeepsTheTerminator()
	{
		$qp = html5qp(self::PI_FILE_HTML, null, self::OPTIONS);

		$this->assertStringContainsString(self::EXPECTED, $qp->html());
	}

	public function testHtmlAndWriteHtmlKeepTheDoctype()
	{
		$qp = html5qp(self::HTML, null, self::OPTIONS);

		$output = $this->capture(function () use ($qp) {
			$qp->writeHTML();
		});

		$this->assertStringStartsWith('<!DOCTYPE html>', $qp->html());
		$this->assertStringStartsWith('<!DOCTYPE html>', $output);
	}

	public function testDocumentIsADocumentThroughOwnerDocument()
	{
		$element = html5qp(self::HTML, null, self::OPTIONS)->get(0);

		$this->assertInstanceOf(Document::class, $element->ownerDocument);
	}

	/**
	 * Only the restored "?" is added, so content that itself ends in "?" keeps it.
	 */
	public function testProcessingInstructionEndingInAQuestionMarkRoundTrips()
	{
		$qp = html5qp('<html><body><h1><?php $a = 1; ??></h1></body></html>', null, self::OPTIONS);

		$this->assertStringContainsString('<h1><?php $a = 1; ??></h1>', $qp->html());
	}

	public function testWriteHtmlLeavesTheDocumentUnchanged()
	{
		$qp = html5qp(self::HTML, null, self::OPTIONS);

		$this->capture(function () use ($qp) {
			$qp->writeHTML();
		});

		$this->assertSame('echo $title; ', $qp->find('h1')->get(0)->firstChild->data);
	}

	/**
	 * A Masterminds document the caller built is a plain DOMDocument, and is written as-is.
	 */
	public function testCallerSuppliedHtml5DocumentIsSerializedAsIs()
	{
		$document = (new HTML5())->loadHTML(self::HTML);
		$qp       = qp($document, null, self::OPTIONS);

		$this->assertNotInstanceOf(Document::class, $qp->document());
		$this->assertStringContainsString('<h1><?php echo $title; ></h1>', $qp->html());
	}

	public function testCallerSuppliedHtmlDocumentIsSerializedAsIs()
	{
		$this->requireLegacyHtmlParser();

		$document = new DOMDocument();
		@$document->loadHTML(self::HTML);

		$this->assertStringContainsString(self::EXPECTED, qp($document, null, self::OPTIONS)->html());
	}

	public function testHtmlParserIsUnchanged()
	{
		$this->requireLegacyHtmlParser();

		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$output = $this->capture(function () use ($qp) {
			$qp->writeHTML();
		});

		$this->assertStringContainsString(self::EXPECTED, $qp->html());
		$this->assertStringContainsString(self::EXPECTED, $output);
	}

	public function testXmlParserIsUnchanged()
	{
		$qp = qp('<?xml version="1.0"?><root><item><?php echo $title; ?></item></root>', null, self::OPTIONS);

		$output = $this->capture(function () use ($qp) {
			$qp->writeHTML();
		});

		$this->assertStringContainsString('<item><?php echo $title; ?></item>', $qp->html());
		$this->assertStringContainsString('<item><?php echo $title; ?></item>', $output);
	}
}
