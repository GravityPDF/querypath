<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/134
 *
 * jQuery filters a traversal's results in walk order, before sorting them: it joins
 * the walk from each starting element, repeats included, filters that list, then
 * removes the repeats and sorts. A positional selector in prevAll(), parents() and
 * nextAll() counts along that list. The expected values are what jQuery 3.7.1's
 * algorithm gives for a set in the same order.
 */

namespace QueryPathTests;

use DOMXPath;
use QueryPath\CSS\DOMTraverser;

class Issue134Test extends TestCase
{
	// Document order: r, a, b, c, d, e.
	private const SIBLINGS = '<?xml version="1.0"?><r><a id="a"/><b id="b"/><c id="c"/><d id="d"/><e id="e"/></r>';

	// Document order: r, s1, t1, u1, s2, t2, u2.
	private const ANCESTORS = '<?xml version="1.0"?><r id="r"><s id="s1"><t id="t1"><u id="u1"/></t></s><s id="s2"><t id="t2"><u id="u2"/></t></s></r>';

	/**
	 * @testWith [":first", "d"]
	 *           [":last", "a"]
	 *           [":eq(0)", "d"]
	 *           [":eq(1)", "c"]
	 *           [":eq(-1)", "a"]
	 *           [":lt(2)", "d,c"]
	 *           [":gt(1)", "b,a"]
	 *           [":odd", "c,a"]
	 *           [":even", "d,b"]
	 *           ["*:not(:first)", "c,b,a"]
	 *           [":matches(:last)", "a"]
	 *           ["b, :first", "d,b"]
	 */
	public function testPrevAllCountsFromTheNearestSibling($selector, $expected)
	{
		$this->assertSame($expected, $this->idList(qp(self::SIBLINGS, '#e')->prevAll($selector)));
	}

	/**
	 * The walks from c and e join as b, a, d, c, b, a.
	 *
	 * @testWith [":first", "b"]
	 *           [":last", "a"]
	 *           [":eq(2)", "d"]
	 *           [":eq(4)", "b"]
	 *           [":lt(3)", "d,b,a"]
	 *           [":gt(3)", "b,a"]
	 *           [":odd", "c,a"]
	 */
	public function testPrevAllOnASetCountsAlongTheJoinedWalks($selector, $expected)
	{
		$this->assertSame($expected, $this->idList($this->selectIds(self::SIBLINGS, 'c', 'e')->prevAll($selector)));
	}

	/**
	 * @testWith ["u2", ":first", "t2"]
	 *           ["u2", ":last", "r"]
	 *           ["u2", ":eq(1)", "s2"]
	 *           ["u2", "s:first", "s2"]
	 *           ["u1,u2", ":first", "t1"]
	 *           ["u1,u2", ":eq(3)", "t2"]
	 *           ["u1,u2", ":lt(2)", "t1,s1"]
	 *           ["u1,u2", ":gt(3)", "s2,r"]
	 *           ["u1,u2", "s:last", "s2"]
	 */
	public function testParentsCountsFromTheNearestAncestor($ids, $selector, $expected)
	{
		$query = $this->selectIds(self::ANCESTORS, ...explode(',', $ids));

		$this->assertSame($expected, $this->idList($query->parents($selector)));
	}

	/**
	 * The walks from a and c join as b, c, d, e, d, e.
	 *
	 * @testWith [":first", "b"]
	 *           [":eq(1)", "c"]
	 *           [":eq(4)", "d"]
	 *           [":last", "e"]
	 */
	public function testNextAllOnASetCountsAlongTheJoinedWalks($selector, $expected)
	{
		$this->assertSame($expected, $this->idList($this->selectIds(self::SIBLINGS, 'a', 'c')->nextAll($selector)));
	}

	/**
	 * The walks are joined in match-set order, as jQuery joins them in its own set's order. add()
	 * keeps the order elements were added in, where jQuery's sorts them into document order.
	 */
	public function testWalksAreJoinedInMatchSetOrder()
	{
		$this->assertSame('d', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->nextAll(':first')));
		$this->assertSame('d', $this->idList($this->selectIds(self::SIBLINGS, 'e', 'c')->prevAll(':first')));
	}

	public function testSelectorsWithoutAPositionAreUnchanged()
	{
		$this->assertSame('d,c,b,a', $this->idList(qp(self::SIBLINGS, '#e')->prevAll()));
		$this->assertSame('c,a', $this->idList(qp(self::SIBLINGS, '#e')->prevAll('a, c')));
		$this->assertSame('t2,s2,t1,s1', $this->idList($this->selectIds(self::ANCESTORS, 'u1', 'u2')->parents('s, t')));
		$this->assertSame('d,e', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->nextAll('d, e')));
	}

	/**
	 * The stop selector of prevUntil() and parentsUntil() already counts across the document
	 * (issue #118), so walk order does not affect it.
	 */
	public function testUntilStopsAreUnaffected()
	{
		$this->assertSame('d,c', $this->idList(qp(self::SIBLINGS, '#e')->prevUntil(':eq(2)')));
		$this->assertSame('t2', $this->idList(qp(self::ANCESTORS, '#u2')->parentsUntil(':eq(4)')));
	}

	public function testFilterListCountsInTheOrderGiven()
	{
		$xpath = new DOMXPath(qp(self::SIBLINGS)->document());
		$nodes = [];
		foreach (['d', 'c', 'b', 'c'] as $id) {
			$nodes[] = $xpath->query("//*[@id='$id']")->item(0);
		}

		$ids = function ($storage) {
			$found = [];
			foreach ($storage as $node) {
				$found[] = $node->getAttribute('id');
			}

			return implode(',', $found);
		};

		$this->assertSame('d', $ids(DOMTraverser::filterList($nodes, ':first')));
		$this->assertSame('c', $ids(DOMTraverser::filterList($nodes, ':last')));
		$this->assertSame('c', $ids(DOMTraverser::filterList($nodes, ':eq(3)')));
		$this->assertSame('d,c,b', $ids(DOMTraverser::filterList($nodes, '*')));
		$this->assertSame('c,b', $ids(DOMTraverser::filterList($nodes, ':gt(0)')));
		$this->assertSame('', $ids(DOMTraverser::filterList([], ':first')));
	}
}
