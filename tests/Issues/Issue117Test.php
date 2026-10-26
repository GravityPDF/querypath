<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/117
 *
 * The traversal methods returned the results of a multi-element set grouped by
 * starting element. jQuery returns `parents()`, `parentsUntil()`, `prevAll()` and
 * `prevUntil()` in reverse document order, `next()` and `prev()` in the order of
 * the starting elements, and the rest in document order. The expected values
 * below are what jQuery 3.7.1 returns for the same calls.
 */

namespace QueryPathTests;

class Issue117Test extends TestCase
{
	public const SIBLINGS = '<?xml version="1.0"?><r><i id="a"/><i id="b"/><i id="c"/><i id="d"/><i id="e"/></r>';

	public const ANCESTORS = '<?xml version="1.0"?><r id="r"><s id="s1"><t id="t1"><u id="u1"/></t></s><s id="s2"><t id="t2"><u id="u2"/></t></s></r>';

	public function testBackwardWalksReturnReverseDocumentOrder()
	{
		$this->assertSame('c,b,a', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'd')->prevAll()));
		$this->assertSame('d,c,b', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'e')->prevUntil('#a')));
		$this->assertSame('t2,s2,t1,s1,r', $this->idList($this->selectIds(self::ANCESTORS, 'u1', 'u2')->parents()));
		$this->assertSame('t2,s2,t1,s1', $this->idList($this->selectIds(self::ANCESTORS, 'u1', 'u2')->parentsUntil('#r')));
	}

	public function testForwardWalksReturnDocumentOrder()
	{
		$this->assertSame('b,c,d,e', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->nextAll()));
		$this->assertSame('b,c,d', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->nextUntil('#e')));
		$this->assertSame('a,b,c,d,e', $this->idList($this->selectIds(self::SIBLINGS, 'd', 'b')->siblings()));
	}

	public function testParentAndClosestReturnDocumentOrder()
	{
		$this->assertSame('t1,t2', $this->idList($this->selectIds(self::ANCESTORS, 'u2', 'u1')->parent()));
		$this->assertSame('s1,s2', $this->idList($this->selectIds(self::ANCESTORS, 'u2', 'u1')->closest('s')));
	}

	public function testNextAndPrevKeepTheOrderOfTheStartingElements()
	{
		$this->assertSame('d,b', $this->idList($this->selectIds(self::SIBLINGS, 'c', 'a')->next()));
		$this->assertSame('d,b', $this->idList($this->selectIds(self::SIBLINGS, 'e', 'c')->prev()));
	}

	public function testSingleStartingElementKeepsItsWalkOrder()
	{
		$this->assertSame('d,c,b,a', $this->idList($this->selectIds(self::SIBLINGS, 'e')->prevAll()));
		$this->assertSame('t1,s1,r', $this->idList($this->selectIds(self::ANCESTORS, 'u1')->parents()));
	}
}
