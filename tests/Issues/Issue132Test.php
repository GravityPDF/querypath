<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/132
 *
 * A positional pseudo-class on a compound left of a combinator, tested against
 * the nodes in hand. As in jQuery's .filter(), that compound counts across the
 * document.
 */

namespace QueryPathTests;

use QueryPath\CSS\DOMTraverser;
use SplObjectStorage;

class Issue132Test extends TestCase
{
	private const XML = '<?xml version="1.0"?><root><ul id="u1"><li id="a"/><li id="b"/></ul><ul id="u2"><li id="c"/></ul></root>';

	/**
	 * @testWith ["ul:first > li", "a,b"]
	 *           ["ul:eq(1) > li", "c"]
	 *           ["ul:last li", "c"]
	 *           ["ul:eq(0) + ul > li", "c"]
	 *           ["ul:gt(0) > li:first", "c"]
	 *           ["ul:first > li:last", "b"]
	 *           ["ul:first > li, ul:last > li", "a,b,c"]
	 */
	public function testFilterCountsALeftHandPositionalCompoundAcrossTheDocument($selector, $expected)
	{
		$this->assertSame($expected, $this->idList(qp(self::XML, 'li')->filter($selector)));
	}

	public function testIsMatchesWhatFindSelects()
	{
		$this->assertSame('a,b', $this->idList(qp(self::XML)->find('ul:first > li')));
		$this->assertTrue(qp(self::XML, 'li')->is('ul:first > li'));
	}

	public function testTheCompoundIsNotLimitedToTheNodesInHand()
	{
		// u1 is not in the set, but is still the document's first <ul>.
		$this->assertSame('b', $this->idList(qp(self::XML, '#b')->filter('ul:first > li')));
		$this->assertSame('', $this->idList(qp(self::XML, '#c')->filter('ul:first > li')));
		$this->assertSame('c', $this->idList(qp(self::XML, 'li')->not('ul:first > li')));
	}

	public function testFilterNodes()
	{
		$nodes = new SplObjectStorage();
		foreach (qp(self::XML, 'li')->get() as $node) {
			$nodes->offsetSet($node);
		}

		$this->assertCount(2, DOMTraverser::filterNodes($nodes, 'ul:first > li'));
		$this->assertCount(1, DOMTraverser::filterNodes($nodes, 'ul:eq(1) > li'));
	}
}
