<?php

namespace QueryPathTests;

/**
 * The right-hand side of `X > *` also matches the document element, whose parent is the
 * document rather than an element.
 */
class Issue74Test extends TestCase
{
	protected const XML = '<?xml version="1.0"?><root><wrap><a/><b/></wrap></root>';

	protected const HTML = '<html><body><ul><li>a</li><li>b</li></ul></body></html>';

	/**
	 * Get the tag name of every element in the match set.
	 *
	 * @param \QueryPath\DOMQuery $query
	 *
	 * @return array
	 */
	protected function tagNames($query): array
	{
		$names = [];
		foreach ($query->get() as $node) {
			$names[] = $node->tagName;
		}

		return $names;
	}

	public function testChildCombinatorWithUniversalSelectorInXml(): void
	{
		$this->assertSame(['a', 'b'], $this->tagNames(qp(self::XML, 'wrap > *')));
		$this->assertSame(['a', 'b'], $this->tagNames(qp(self::XML)->find('wrap > *')));
		$this->assertSame(['wrap'], $this->tagNames(qp(self::XML, 'root > *')));
		$this->assertSame(['wrap', 'a', 'b'], $this->tagNames(qp(self::XML, '* > *')));
	}

	/**
	 * The other combinators also start from a right-hand side that matches the root element.
	 */
	public function testOtherCombinatorsWithUniversalSelectorInXml(): void
	{
		$this->assertSame(['wrap', 'a', 'b'], $this->tagNames(qp(self::XML, '* *')));
		$this->assertSame(['b'], $this->tagNames(qp(self::XML, '* + *')));
		$this->assertSame(['b'], $this->tagNames(qp(self::XML, '* ~ *')));
	}

	public function testChildCombinatorWithUniversalSelectorInHtml(): void
	{
		foreach (['htmlqp', 'html5qp'] as $factory) {
			$this->assertSame(['li', 'li'], $this->tagNames($factory(self::HTML, 'ul > *')), $factory);
			$this->assertSame(['li', 'li'], $this->tagNames($factory(self::HTML)->find('ul > *')), $factory);
		}
	}
}
