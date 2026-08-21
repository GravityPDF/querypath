<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/66
 *
 * The jQuery positional pseudo-classes (:eq, :first, :last, :lt, :gt, :odd,
 * :even) index the matched set, not a node's siblings. They used to be
 * implemented as sibling-position tests, which gave the wrong answer whenever
 * the match set spanned more than one parent, and meant :eq(0) never matched.
 */

namespace QueryPathTests;

use QueryPath\CSS\DOMTraverser\Util;

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class Issue66Test extends TestCase
{

	/**
	 * find('li') matches five elements in document order: a1, a2, a3, b1, b2.
	 */
	private const HTML = '<div><ul><li>a1</li><li>a2</li><li>a3</li></ul><ul><li>b1</li><li>b2</li></ul></div>';

	/**
	 * The divergence table from issue #66. Every expectation here is what
	 * jQuery returns for the same selector against the same markup.
	 *
	 * @return array
	 */
	public function positionalProvider(): array
	{
		return [
			'eq(0) used to match nothing' => ['li:eq(0)', ['a1']],
			'eq is zero indexed'          => ['li:eq(1)', ['a2']],
			'eq spans parents'            => ['li:eq(3)', ['b1']],
			'eq past the end'             => ['li:eq(9)', []],
			'eq counts back when negative' => ['li:eq(-1)', ['b2']],
			'nth is an alias of eq'       => ['li:nth(0)', ['a1']],
			'first'                       => ['li:first', ['a1']],
			'last'                        => ['li:last', ['b2']],
			'lt'                          => ['li:lt(2)', ['a1', 'a2']],
			'lt(0)'                       => ['li:lt(0)', []],
			'gt'                          => ['li:gt(2)', ['b1', 'b2']],
			'odd'                         => ['li:odd', ['a2', 'b1']],
			'even'                        => ['li:even', ['a1', 'a3', 'b2']],
		];
	}

	/**
	 * @dataProvider positionalProvider
	 *
	 * @param string $selector
	 * @param array  $expected
	 */
	public function testPositionalPseudoClassesIndexTheMatchedSet($selector, array $expected)
	{
		$this->assertSame($expected, $this->textOf(html5qp(self::HTML)->find($selector)));
	}

	/**
	 * remove() and replaceAll() use the legacy QueryPathEventHandler engine.
	 * It has to give the same answer as find().
	 *
	 * @dataProvider positionalProvider
	 *
	 * @param string $selector
	 * @param array  $expected
	 */
	public function testLegacyEngineAgreesWithTheTraverser($selector, array $expected)
	{
		$removed = $this->textOf(html5qp(self::HTML)->remove($selector));
		sort($removed);

		$sorted = $expected;
		sort($sorted);

		$this->assertSame($sorted, $removed);
	}

	/**
	 * The selector and the equivalent method have to agree.
	 */
	public function testSelectorMatchesTheEquivalentMethod()
	{
		$this->assertSame('a1', html5qp(self::HTML)->find('li')->eq(0)->text());
		$this->assertSame('a1', html5qp(self::HTML)->find('li:eq(0)')->text());

		$this->assertSame('b2', html5qp(self::HTML)->find('li')->last()->text());
		$this->assertSame('b2', html5qp(self::HTML)->find('li:last')->text());
	}

	/**
	 * Positional filters compose with combinators. A filter written on a
	 * non-subject simple selector applies to that selector's own result set.
	 */
	public function testPositionalPseudoClassesComposeWithCombinators()
	{
		$this->assertSame(['a1', 'a2', 'a3'], $this->textOf(html5qp(self::HTML)->find('ul:first li')));
		$this->assertSame(['b1'], $this->textOf(html5qp(self::HTML)->find('ul:last li:first')));
		$this->assertSame(['a2'], $this->textOf(html5qp(self::HTML)->find('ul:eq(0) li:eq(1)')));
		$this->assertSame(['b1'], $this->textOf(html5qp(self::HTML)->find('ul > li:eq(3)')));
		$this->assertSame(['b1', 'b2'], $this->textOf(html5qp(self::HTML)->find('ul:not(:first) li')));
	}

	/**
	 * Each comma-separated group is filtered on its own, as in jQuery, and the
	 * union is returned in document order.
	 */
	public function testEachSelectorGroupIsFilteredIndependently()
	{
		$this->assertSame(['a1', 'b2'], $this->textOf(html5qp(self::HTML)->find('li:first, li:last')));
		$this->assertSame(['a1', 'a2'], $this->textOf(html5qp(self::HTML)->find('li:eq(1), li:first')));
	}

	/**
	 * Chained filters are applied left to right over the running set.
	 */
	public function testPositionalPseudoClassesChain()
	{
		$this->assertSame(['a2', 'a3'], $this->textOf(html5qp(self::HTML)->find('li:gt(0):lt(2)')));
	}

	/**
	 * :not(:first) is the common jQuery idiom: the argument sees the whole
	 * result set, not the node under test.
	 */
	public function testPositionalPseudoClassesInsideNot()
	{
		$this->assertSame(['a2', 'a3', 'b1', 'b2'], $this->textOf(html5qp(self::HTML)->find('li:not(:first)')));
		$this->assertSame(['a1', 'a3', 'b1', 'b2'], $this->textOf(html5qp(self::HTML)->find('li:not(:eq(1))')));
		$this->assertSame(['a1', 'a3', 'b2'], $this->textOf(html5qp(self::HTML)->find('li:not(:odd)')));
		$this->assertSame(['a2'], $this->textOf(html5qp(self::HTML)->find('li:not(:first):first')));
		$this->assertSame(['a1'], $this->textOf(html5qp(self::HTML)->find('li:matches(:first)')));
	}

	/**
	 * The CSS structural pseudo-classes still count siblings. Only the jQuery
	 * positional set moved to result-set semantics.
	 */
	public function testStructuralPseudoClassesStillCountSiblings()
	{
		$this->assertSame(['a1', 'b1'], $this->textOf(html5qp(self::HTML)->find('li:first-child')));
		$this->assertSame(['a3', 'b2'], $this->textOf(html5qp(self::HTML)->find('li:last-child')));

		// :nth-child() is one-based and counts siblings.
		$this->assertSame(['a1', 'b1'], $this->textOf(html5qp(self::HTML)->find('li:nth-child(1)')));
		$this->assertSame(['a2', 'b2'], $this->textOf(html5qp(self::HTML)->find('li:nth-child(even)')));
		$this->assertSame(['a1', 'a3', 'b1'], $this->textOf(html5qp(self::HTML)->find('li:nth-child(odd)')));
		$this->assertSame(['a1', 'b1'], $this->textOf(html5qp(self::HTML)->find('li:nth-of-type(1)')));
	}

	/**
	 * XML documents go through the same code path.
	 */
	public function testPositionalPseudoClassesOnXml()
	{
		$xml = '<?xml version="1.0"?><root><a><i>1</i><i>2</i></a><a><i>3</i></a></root>';

		$this->assertSame('1', qp($xml, 'i:eq(0)')->text());
		$this->assertSame('3', qp($xml, 'i:eq(2)')->text());
		$this->assertSame('3', qp($xml, 'i:last')->text());
		$this->assertSame(2, qp($xml, 'i:even')->count());
	}

	/**
	 * The filter helper itself, which both engines share.
	 */
	public function testApplyPositionalPseudoClassOnAnEmptySet()
	{
		$this->assertSame([], Util::applyPositionalPseudoClass([], 'first'));
		$this->assertSame([], Util::applyPositionalPseudoClass([], 'eq', 0));
	}

	public function testUtilRecognisesThePositionalSet()
	{
		foreach (['eq', 'nth', 'first', 'last', 'lt', 'gt', 'odd', 'even', 'EQ', 'First'] as $name) {
			$this->assertTrue(Util::isPositionalPseudoClass($name), $name . ' should be positional');
		}

		foreach (['nth-child', 'nth-of-type', 'first-child', 'last-child', 'first-of-type', 'root'] as $name) {
			$this->assertFalse(Util::isPositionalPseudoClass($name), $name . ' should not be positional');
		}
	}

	/**
	 * Collect the text of every match, in match order.
	 *
	 * @param \QueryPath\DOMQuery $query
	 *
	 * @return string[]
	 */
	private function textOf($query): array
	{
		$text = [];
		foreach ($query as $item) {
			$text[] = $item->text();
		}

		return $text;
	}
}
