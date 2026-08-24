<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/65
 *
 * libxml's HTML parser keeps the closing "?" of a processing instruction as part of the node's
 * data, while its XML parser and the Masterminds HTML5 parser do not. Serializers that append
 * their own "?>" therefore doubled it up, so `<?php echo $title; ?>` came back out as
 * `<?php echo $title; ??>` and grew another "?" on every round trip.
 */

namespace QueryPathTests;

use DOMDocument;
use DOMProcessingInstruction;
use QueryPath\Document;

class Issue65Test extends TestCase
{
	private const PI_FILE_HTML = 'tests/processing-instruction.html';

	/**
	 * A document with a PHP block.
	 */
	private const HTML = '<html><body><h1><?php echo $title; ?></h1></body></html>';

	/**
	 * The expected serialization of self::HTML's <h1>, for every serializer.
	 */
	private const EXPECTED = '<h1><?php echo $title; ?></h1>';

	/**
	 * Keeps the serialized output compact enough to compare exactly.
	 */
	private const OPTIONS = ['format_output' => false];

	/**
	 * Every serializer that appends its own "?>", with the selector each one is called on.
	 *
	 * @return array
	 */
	public function serializerProvider(): array
	{
		return [
			'innerHTML'  => ['body', 'innerHTML'],
			'innerXML'   => ['body', 'innerXML'],
			'innerXHTML' => ['body', 'innerXHTML'],
			'innerHTML5' => ['body', 'innerHTML5'],
			'html'       => ['h1', 'html'],
			'html5'      => ['h1', 'html5'],
			'xml'        => ['h1', 'xml'],
		];
	}

	/**
	 * @dataProvider serializerProvider
	 *
	 * @param string $selector
	 * @param string $method
	 */
	public function testSerializersDoNotDoubleUpTheProcessingInstructionTerminator($selector, $method)
	{
		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$this->assertSame(self::EXPECTED, $qp->top()->find($selector)->$method());
	}

	public function testHtmlOfTheWholeDocumentKeepsASingleTerminator()
	{
		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$this->assertStringContainsString(self::EXPECTED, $qp->top()->html());
	}

	/**
	 * A document parsed from a .html file goes through loadHTMLFile() rather than loadHTML().
	 */
	public function testHtmlFileOnDiskIsNormalisedToo()
	{
		$qp = qp(self::PI_FILE_HTML, null, self::OPTIONS);

		$this->assertSame(self::EXPECTED, $qp->top()->find('body')->innerHTML());
	}

	/**
	 * The bug compounded: every extra round trip used to add another "?".
	 */
	public function testRepeatedRoundTripsAreStable()
	{
		$markup = self::HTML;

		for ($i = 0; $i < 3; $i++) {
			$markup = htmlqp($markup, null, self::OPTIONS)->top()->html();
			$this->assertStringContainsString(self::EXPECTED, $markup);
			$this->assertStringNotContainsString('??>', $markup);
		}
	}

	public function testRepeatedInnerHtmlRoundTripsAreStable()
	{
		$markup = self::HTML;

		for ($i = 0; $i < 3; $i++) {
			$markup = htmlqp($markup, null, self::OPTIONS)->top()->find('body')->innerHTML();
			$this->assertSame(self::EXPECTED, $markup);
		}
	}

	/**
	 * Reading the node directly should hand back usable PHP, not source with a stray "?" glued on.
	 */
	public function testProcessingInstructionDataHasNoTrailingQuestionMark()
	{
		$instruction = htmlqp(self::HTML, null, self::OPTIONS)->top()->find('h1')->get(0)->firstChild;

		$this->assertInstanceOf(DOMProcessingInstruction::class, $instruction);
		$this->assertSame('php', $instruction->target);
		$this->assertSame('echo $title; ', $instruction->data);
	}

	/**
	 * Exactly one "?" is stripped, so content that legitimately ends in "?" still round trips.
	 */
	public function testProcessingInstructionEndingInAQuestionMarkIsNotDoubleStripped()
	{
		$markup = '<html><body><h1><?php $a = 1; ??></h1></body></html>';
		$qp     = htmlqp($markup, null, self::OPTIONS);

		$this->assertSame('<h1><?php $a = 1; ??></h1>', $qp->top()->find('body')->innerHTML());
		$this->assertStringContainsString('<h1><?php $a = 1; ??></h1>', $qp->top()->html());
	}

	/**
	 * Every method that prints the whole document, all of which must emit exactly one terminator.
	 *
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
	public function testWritersEmitASingleTerminator($method)
	{
		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$output = $this->capture(function () use ($qp, $method) {
			$qp->top()->$method();
		});

		$this->assertStringContainsString(self::EXPECTED, $output);
		$this->assertStringNotContainsString('??>', $output);
	}

	public function testWriteHtmlToAFileEmitsASingleTerminator()
	{
		$qp   = htmlqp(self::HTML, null, self::OPTIONS);
		$path = tempnam(sys_get_temp_dir(), 'qp65');

		try {
			$qp->top()->writeHTML($path);
			$output = file_get_contents($path);
		} finally {
			unlink($path);
		}

		$this->assertStringContainsString(self::EXPECTED, $output);
		$this->assertStringNotContainsString('??>', $output);
	}

	/**
	 * The temporary terminator writeHTML() needs must not leak into the document afterwards.
	 */
	public function testWriteHtmlLeavesTheDocumentUnchanged()
	{
		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$this->capture(function () use ($qp) {
			$qp->top()->writeHTML();
		});

		$this->assertSame(self::EXPECTED, $qp->top()->find('body')->innerHTML());
	}

	/**
	 * html5qp() was never affected, because the Masterminds parser does not keep the "?".
	 */
	public function testHtml5ParserIsUnaffected()
	{
		$qp = html5qp(self::HTML, null, self::OPTIONS);

		$this->assertSame(self::EXPECTED, $qp->top()->find('body')->innerHTML());
		$this->assertSame(self::EXPECTED, $qp->top()->find('body')->innerHTML5());
	}

	/**
	 * A document parsed in XML mode was never affected either.
	 */
	public function testXmlParserIsUnaffected()
	{
		$xml = '<?xml version="1.0"?><root><item><?php echo $title; ?></item></root>';
		$qp  = qp($xml, null, self::OPTIONS);

		$this->assertSame('<?php echo $title; ?>', $qp->top()->find('item')->innerXML());
		$this->assertSame('<item><?php echo $title; ?></item>', $qp->top()->find('item')->xml());

		$instruction = $qp->top()->find('item')->get(0)->firstChild;
		$this->assertSame('echo $title; ', $instruction->data);
	}

	/**
	 * writeHTML() on an XML-parsed document used to drop the terminator entirely, because libxml's
	 * HTML serializer writes a processing instruction verbatim and never adds one of its own.
	 */
	public function testWriteHtmlOnAnXmlParsedDocumentEmitsATerminator()
	{
		$xml = '<?xml version="1.0"?><root><item><?php echo $title; ?></item></root>';
		$qp  = qp($xml, null, self::OPTIONS);

		$output = $this->capture(function () use ($qp) {
			$qp->top()->writeHTML();
		});

		$this->assertStringContainsString('<item><?php echo $title; ?></item>', $output);
	}

	/**
	 * An HTML processing instruction with no "?" before the ">" has nothing to strip.
	 */
	public function testProcessingInstructionWithoutATerminatorIsLeftAlone()
	{
		$qp = htmlqp('<html><body><h1><?foo bar></h1></body></html>', null, self::OPTIONS);

		$instruction = $qp->top()->find('h1')->get(0)->firstChild;
		$this->assertSame('foo', $instruction->target);
		$this->assertSame('bar', $instruction->data);
	}

	/**
	 * Documents with no processing instructions must serialize exactly as they did before.
	 */
	public function testDocumentsWithoutProcessingInstructionsAreUntouched()
	{
		$qp = htmlqp('<html><body><div id="d"><br><p>hi</p></div></body></html>', null, self::OPTIONS);

		$this->assertSame('<br/><p>hi</p>', $qp->top()->find('#d')->innerHTML());

		$output = $this->capture(function () use ($qp) {
			$qp->top()->writeHTML();
		});

		$this->assertStringContainsString('<div id="d"><br><p>hi</p></div>', $output);
	}

	/**
	 * Normalisation only happens on documents QueryPath parses, so the HTML serializer must leave a
	 * caller-supplied document alone -- its processing instructions still carry their own "?".
	 */
	public function testCallerSuppliedHtmlDocumentIsSerializedAsIs()
	{
		$doc = new DOMDocument();
		@$doc->loadHTML(self::HTML);

		$this->assertStringContainsString(self::EXPECTED, qp($doc, null, self::OPTIONS)->top()->html());
		$this->assertStringContainsString(self::EXPECTED, qp($doc->documentElement, null, self::OPTIONS)->top()->html());
	}

	/**
	 * branch() assigns $this->document directly rather than going through the constructor.
	 */
	public function testBranchKeepsTheDocumentSerializingCorrectly()
	{
		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$this->assertStringContainsString(self::EXPECTED, $qp->top()->branch()->html());
	}

	/**
	 * A DOMQuery built from another DOMQuery inherits its document, and with it the invariant.
	 */
	public function testDocumentCopiedFromAnotherQueryPathSerializesCorrectly()
	{
		$qp = qp(htmlqp(self::HTML, null, self::OPTIONS), null, self::OPTIONS);

		$this->assertStringContainsString(self::EXPECTED, $qp->top()->html());
	}

	/**
	 * Iterating a match set builds a fresh DOMQuery per element over the same document.
	 */
	public function testIteratingAMatchSetKeepsTheDocumentSerializingCorrectly()
	{
		$qp = htmlqp(self::HTML, null, self::OPTIONS);

		$output = $this->capture(function () use ($qp) {
			foreach ($qp->top()->find('html') as $element) {
				$element->writeHTML();
			}
		});

		$this->assertStringContainsString(self::EXPECTED, $output);
		$this->assertStringNotContainsString('??>', $output);
	}

	/**
	 * Every route to a second query over one document, none of which run the parser again.
	 *
	 * @return array
	 */
	public function sharedDocumentProvider(): array
	{
		return [
			'from the document' => [true],
			'from a node'       => [false],
		];
	}

	/**
	 * @dataProvider sharedDocumentProvider
	 *
	 * @param bool $fromDocument
	 */
	public function testASecondQueryOverTheSameDocumentSerializesCorrectly($fromDocument)
	{
		$element = htmlqp(self::HTML, null, self::OPTIONS)->top()->get(0);
		$source  = $fromDocument ? $element->ownerDocument : $element;

		$this->assertStringContainsString(self::EXPECTED, qp($source, null, self::OPTIONS)->top()->html());
	}

	/**
	 * remove() runs the legacy selector engine and returns a new DOMQuery over the same document.
	 */
	public function testRemoveKeepsTheDocumentSerializingCorrectly()
	{
		$html = '<html><body><p>gone</p><h1><?php echo $title; ?></h1></body></html>';
		$qp   = htmlqp($html, null, self::OPTIONS);
		$qp->top()->find('p')->remove();

		$this->assertStringContainsString(self::EXPECTED, $qp->top()->html());
	}

	/**
	 * The marker is the document's type, and PHP rebuilds that wrapper object whenever a document
	 * is reached through ownerDocument after the original wrapper has been released.
	 */
	public function testTheDocumentKeepsItsTypeWhenReachedThroughOwnerDocument()
	{
		$element = htmlqp(self::HTML, null, self::OPTIONS)->top()->get(0);

		$this->assertInstanceOf(Document::class, $element->ownerDocument);
	}

	/**
	 * Normalisation runs once, when the document is parsed. Re-using it must not strip a second
	 * "?" from content that legitimately ends in one.
	 */
	public function testProcessingInstructionEndingInAQuestionMarkSurvivesReuse()
	{
		$markup   = '<html><body><h1><?php $a = 1; ??></h1></body></html>';
		$expected = '<h1><?php $a = 1; ??></h1>';
		$qp       = htmlqp($markup, null, self::OPTIONS);

		$this->assertStringContainsString($expected, $qp->top()->html());
		$this->assertStringContainsString($expected, $qp->top()->branch()->html());
		$this->assertStringContainsString(
			$expected,
			qp($qp->top()->get(0)->ownerDocument, null, self::OPTIONS)->top()->html()
		);
	}
}
