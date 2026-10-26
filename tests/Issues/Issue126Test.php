<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/126,
 * https://github.com/GravityPDF/querypath/issues/123 and
 * https://github.com/GravityPDF/querypath/issues/124
 *
 * remove($selector) and replaceAll($selector) used the legacy QueryPathEventHandler
 * engine, which matched the nodes in hand, did not know :matches(), and evaluated a
 * positional :not() argument one element at a time. They now select what find() does.
 */

namespace QueryPathTests;

use QueryPath\DOMQuery;

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class Issue126Test extends TestCase
{
	private const LIST_XML = '<?xml version="1.0"?><ul><li id="a"/><li id="b" class="x"/><li id="c"/></ul>';

	private const LIST_HTML = '<ul><li id="a"></li><li id="b" class="x"></li><li id="c"></li></ul>';

	private const NESTED_XML = '<?xml version="1.0"?><root><p><i>a</i><b>x</b></p><p><i>b</i></p><i>c</i></root>';

	/**
	 * The tables from #123 and #124 against each factory's document: the selector, what
	 * find() matches, and the list items left behind.
	 *
	 * @return array
	 */
	public function selectorProvider(): array
	{
		$selectors = [
			'li:matches(.x)' => [['b'], ['a', 'c']],
			'li:not(:eq(0))' => [['b', 'c'], ['a']],
			'li:not(:first)' => [['b', 'c'], ['a']],
			'li:not(:last)'  => [['a', 'b'], ['c']],
			'li:not(:odd)'   => [['a', 'c'], ['b']],
			'li:not(.x)'     => [['a', 'c'], ['b']],
		];

		$cases = [];
		foreach (['qp' => self::LIST_XML, 'htmlqp' => self::LIST_HTML, 'html5qp' => self::LIST_HTML] as $factory => $markup) {
			foreach ($selectors as $selector => list($matched, $left)) {
				$cases[$factory . ' ' . $selector] = [$factory, $markup, $selector, $matched, $left];
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider selectorProvider
	 */
	public function testRemoveAgreesWithFind(string $factory, string $markup, string $selector, array $matched, array $left)
	{
		$this->assertSame($matched, $this->ids($factory($markup)->find($selector)));

		$qp = $factory($markup);
		$this->assertSame($matched, $this->ids($qp->remove($selector)));
		$this->assertSame($left, $this->ids($qp->find('li')));
	}

	/**
	 * @dataProvider selectorProvider
	 */
	public function testReplaceAllAgreesWithFind(string $factory, string $markup, string $selector, array $matched, array $left)
	{
		$document = $factory($markup)->get(0)->ownerDocument;
		qp('<?xml version="1.0"?><root><z/></root>', 'z')->replaceAll($selector, $document);

		$this->assertSame($left, $this->ids(qp($document)->find('li')));
		$this->assertCount(count($matched), qp($document)->find('z'));
	}

	public function testRemoveSearchesDescendantsOnly()
	{
		$this->assertSame(0, qp(self::NESTED_XML, 'p')->remove('p')->count());
		$this->assertSame(3, qp(self::NESTED_XML, 'p')->remove('*')->count());

		$qp = qp(self::NESTED_XML, 'p');
		$this->assertSame(['a', 'b'], $this->text($qp->remove('i')));
		$this->assertSame(['x', 'c'], $this->text($qp->top()->find('b, i')));
	}

	public function testRemoveCanMatchTheDocumentElement()
	{
		$qp = qp(self::LIST_XML);

		$this->assertSame(1, $qp->remove('ul')->count());
		$this->assertNull($qp->get(0)->ownerDocument->documentElement);
	}

	/**
	 * replaceAll() searches the whole target document, whatever the match set holds.
	 */
	public function testReplaceAllSearchesTheWholeDocument()
	{
		$document = qp(self::LIST_XML)->get(0)->ownerDocument;
		qp('<?xml version="1.0"?><root><z/></root>', 'z')->replaceAll('ul', $document);

		$this->assertSame('z', $document->documentElement->tagName);

		$document = qp(self::NESTED_XML)->get(0)->ownerDocument;
		qp('<?xml version="1.0"?><root><z/></root>', 'z')->replaceAll('p', $document);

		$this->assertSame(['z', 'z', 'i'], $this->tags(qp($document)->children()));
	}

	private function ids(DOMQuery $qp): array
	{
		return array_map(function ($node) {
			return $node->getAttribute('id');
		}, $qp->get());
	}

	private function text(DOMQuery $qp): array
	{
		return array_map(function ($node) {
			return $node->textContent;
		}, $qp->get());
	}

	private function tags(DOMQuery $qp): array
	{
		return array_map(function ($node) {
			return $node->tagName;
		}, $qp->get());
	}
}
