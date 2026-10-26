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
	 * The tables from #123 and #124, as find() answers them.
	 *
	 * @return array
	 */
	public function selectorProvider(): array
	{
		return [
			'matches (#123)'   => ['li:matches(.x)', ['b']],
			'not eq(0) (#124)' => ['li:not(:eq(0))', ['b', 'c']],
			'not first (#124)' => ['li:not(:first)', ['b', 'c']],
			'not last (#124)'  => ['li:not(:last)', ['a', 'b']],
			'not odd (#124)'   => ['li:not(:odd)', ['a', 'c']],
			'not class (#124)' => ['li:not(.x)', ['a', 'c']],
		];
	}

	/**
	 * Each selector from selectorProvider() against each factory's document.
	 *
	 * @return array
	 */
	public function factorySelectorProvider(): array
	{
		$cases = [];
		foreach (['qp' => self::LIST_XML, 'htmlqp' => self::LIST_HTML, 'html5qp' => self::LIST_HTML] as $factory => $markup) {
			foreach ($this->selectorProvider() as $name => $case) {
				$cases[$factory . ': ' . $name] = array_merge([$factory, $markup], $case);
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider factorySelectorProvider
	 */
	public function testRemoveAgreesWithFind(string $factory, string $markup, string $selector, array $expected)
	{
		$this->assertSame($expected, $this->ids($factory($markup)->find($selector)));

		$qp = $factory($markup);
		$this->assertSame($expected, $this->ids($qp->remove($selector)));
		$this->assertSame(array_values(array_diff(['a', 'b', 'c'], $expected)), $this->ids($qp->find('li')));
	}

	/**
	 * @dataProvider factorySelectorProvider
	 */
	public function testReplaceAllAgreesWithFind(string $factory, string $markup, string $selector, array $expected)
	{
		$document = $factory($markup)->get(0)->ownerDocument;
		qp('<?xml version="1.0"?><root><z/></root>', 'z')->replaceAll($selector, $document);

		$this->assertSame(array_values(array_diff(['a', 'b', 'c'], $expected)), $this->ids(qp($document)->find('li')));
		$this->assertCount(count($expected), qp($document)->find('z'));
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

	/**
	 * @return string[]
	 */
	private function ids(DOMQuery $qp): array
	{
		$ids = [];
		foreach ($qp->get() as $node) {
			$ids[] = $node->getAttribute('id');
		}

		return $ids;
	}

	/**
	 * @return string[]
	 */
	private function text(DOMQuery $qp): array
	{
		$text = [];
		foreach ($qp->get() as $node) {
			$text[] = $node->textContent;
		}

		return $text;
	}

	/**
	 * @return string[]
	 */
	private function tags(DOMQuery $qp): array
	{
		$tags = [];
		foreach ($qp->get() as $node) {
			$tags[] = $node->tagName;
		}

		return $tags;
	}
}
