<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/118
 *
 * Positional pseudo-classes in the walk-and-stop traversals. Expected values
 * are jQuery 3.7.1's, except where a test says otherwise.
 */

namespace QueryPathTests;

class Issue118Test extends TestCase
{
	// Document order: r, a, b, c, d, e.
	private const SIBLINGS = '<?xml version="1.0"?><r><i id="a"/><i id="b" class="k"/><i id="c"/><i id="d" class="k"/><i id="e"/></r>';

	// Document order: r, s1, t1, u1, s2, t2, u2.
	private const ANCESTORS = '<?xml version="1.0"?><r id="r"><s id="s1"><t id="t1"><u id="u1"/></t></s><s id="s2"><t id="t2"><u id="u2"/></t></s></r>';

	public function testNextUntilFromTheIssue()
	{
		$xml = '<?xml version="1.0"?><ul><li id="a"/><li id="b"/><li id="c"/><li id="d"/></ul>';

		// The document's :eq(1) is #a itself, which the walk never reaches.
		$this->assertSame('b,c,d', $this->idList(qp($xml, '#a')->nextUntil(':eq(1)')));
		$this->assertSame('b', $this->idList(qp($xml, '#a')->nextUntil(':eq(3)')));
	}

	public function testNextUntilCountsAPositionalStopAcrossTheDocument()
	{
		$this->assertSame('b,c,d,e', $this->idList($this->selectIds(self::SIBLINGS, 'a')->nextUntil(':first')));
		$this->assertSame('b', $this->idList($this->selectIds(self::SIBLINGS, 'a')->nextUntil(':eq(3)')));
		$this->assertSame('b', $this->idList($this->selectIds(self::SIBLINGS, 'a')->nextUntil('i:eq(2)')));
		$this->assertSame('b,c,d', $this->idList($this->selectIds(self::SIBLINGS, 'b', 'a')->nextUntil(':last')));
		$this->assertSame('', $this->idList($this->selectIds(self::SIBLINGS, 'a')->nextUntil('i:not(:first)')));
	}

	public function testPrevUntilCountsAPositionalStopAcrossTheDocument()
	{
		$this->assertSame('d,c,b,a', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prevUntil(':first')));
		$this->assertSame('d,c', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prevUntil(':eq(2)')));
		$this->assertSame('d,c', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prevUntil('.k:first')));
	}

	public function testParentsUntilCountsAPositionalStopAcrossTheDocument()
	{
		$this->assertSame('t2,s2', $this->idList($this->selectIds(self::ANCESTORS, 'u2')->parentsUntil(':first')));
		$this->assertSame('t2', $this->idList($this->selectIds(self::ANCESTORS, 'u2')->parentsUntil(':eq(4)')));
	}

	public function testClosestNeverMatchesAPositionalSelector()
	{
		$this->assertSame('', $this->idList($this->selectIds(self::ANCESTORS, 'u1')->closest(':first')));
		$this->assertSame('', $this->idList($this->selectIds(self::ANCESTORS, 'u1')->closest('t:first')));
		$this->assertSame('', $this->idList($this->selectIds(self::ANCESTORS, 'u2', 'u1')->closest(':eq(0)')));
		$this->assertSame('t1', $this->idList($this->selectIds(self::ANCESTORS, 'u1')->closest('t')));
	}

	/**
	 * next(), prev() and parent() test each candidate on its own, which matches jQuery
	 * for a single element. For [c, a], jQuery's next(':first') is just d.
	 */
	public function testNextPrevAndParentTestAPositionalSelectorPerNode()
	{
		$this->assertSame('b', $this->idList($this->selectIds(self::SIBLINGS, 'a')->next(':first')));
		$this->assertSame('', $this->idList($this->selectIds(self::SIBLINGS, 'a')->next(':eq(1)')));
		$this->assertSame('d,b', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->next(':first')));
		$this->assertSame('d', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prev(':last')));
		$this->assertSame('t1', $this->idList($this->selectIds(self::ANCESTORS, 'u1')->parent(':first')));
	}

	/**
	 * The set methods count a positional selector within the set, in document order
	 * (issue #66), whatever order the results come back in (issue #117). jQuery counts
	 * in walk order, so for prevAll() and parents() its :first is the nearest node.
	 */
	public function testSetMethodsCountPositionsInDocumentOrder()
	{
		$this->assertSame('a', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prevAll(':first')));
		$this->assertSame('d', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prevAll(':last')));
		$this->assertSame('b', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'e')->prevAll(':eq(1)')));
		$this->assertSame('b', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->nextAll(':first')));
		$this->assertSame('a', $this->idList($this->selectIds(self::SIBLINGS, 'd', 'b')->siblings(':first')));
		$this->assertSame('r', $this->idList($this->selectIds(self::ANCESTORS, 'u1', 'u2')->parents(':first')));
		$this->assertSame('t2', $this->idList($this->selectIds(self::ANCESTORS, 'u1', 'u2')->parents(':last')));
	}
}
