<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/142
 *
 * Negative indexes in eq(), get() and slice(). Expected values are jQuery 3.7.1's.
 */

namespace QueryPathTests;

class Issue142Test extends TestCase
{
	private const XML = '<?xml version="1.0"?><r><i id="a"/><i id="b"/><i id="c"/><i id="d"/><i id="e"/></r>';

	public function testEqCountsANegativeIndexFromTheEnd()
	{
		$qp = qp(self::XML, 'i');

		$this->assertSame('e', $this->idList($qp->eq(-1)));
		$this->assertSame('d', $this->idList($qp->eq(-2)));
		$this->assertSame('a', $this->idList($qp->eq(-5)));
		$this->assertSame('', $this->idList($qp->eq(-6)));
		$this->assertSame('', $this->idList($qp->eq(5)));
		$this->assertSame('', $this->idList(qp(self::XML, 'nothing')->eq(-1)));
	}

	public function testGetCountsANegativeIndexFromTheEnd()
	{
		$qp = qp(self::XML, 'i');

		$this->assertSame('e', $qp->get(-1)->getAttribute('id'));
		$this->assertSame('a', $qp->get(-5)->getAttribute('id'));
		$this->assertNull($qp->get(-6));
		$this->assertNull($qp->get(5));
		$this->assertNull(qp(self::XML, 'nothing')->get(-1));
	}

	public function testSliceCountsANegativeStartFromTheEnd()
	{
		$qp = qp(self::XML, 'i');

		$this->assertSame('d,e', $this->idList($qp->slice(-2)));
		$this->assertSame('e', $this->idList($qp->slice(-1)));
		$this->assertSame('a,b,c,d,e', $this->idList($qp->slice(-5)));
		$this->assertSame('a,b,c,d,e', $this->idList($qp->slice(-9)));
	}
}
