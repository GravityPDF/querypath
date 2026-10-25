<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/65 that run on every libxml
 * version. See Issue65Test for the bug itself.
 */

namespace QueryPathTests;

use QueryPath\Document;

class Issue65ParserIndependentTest extends TestCase
{
	private const HTML = '<html><body><h1><?php echo $title; ?></h1></body></html>';

	private const EXPECTED = '<h1><?php echo $title; ?></h1>';

	private const OPTIONS = ['format_output' => false];

	/**
	 * The comment libxml 2.14+ produces must round trip unchanged.
	 */
	public function testHtml5LibxmlKeepsTheBlockAsAComment()
	{
		if (! self::libxmlParsesHtmlAsHtml5()) {
			$this->markTestSkipped('libxml ' . LIBXML_DOTTED_VERSION . ' parses <?...> as a processing instruction.');
		}

		$expected = '<h1><!--?php echo $title; ?--></h1>';
		$markup   = self::HTML;

		for ($i = 0; $i < 3; $i++) {
			$markup = htmlqp($markup, null, self::OPTIONS)->top()->html();
			$this->assertStringContainsString($expected, $markup);
		}
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
	 * The marker is the document's type, and PHP rebuilds that wrapper object whenever a document
	 * is reached through ownerDocument after the original wrapper has been released.
	 */
	public function testTheDocumentKeepsItsTypeWhenReachedThroughOwnerDocument()
	{
		$element = htmlqp(self::HTML, null, self::OPTIONS)->top()->get(0);

		$this->assertInstanceOf(Document::class, $element->ownerDocument);
	}
}
