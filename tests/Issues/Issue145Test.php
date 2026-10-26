<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/145
 *
 * even() and odd() count from zero, as jQuery and the :even/:odd pseudo-classes do.
 * Expected values are jQuery 3.7.1's.
 */

namespace QueryPathTests;

class Issue145Test extends TestCase
{
	private const XML = '<?xml version="1.0"?><r><i id="a"/><i id="b"/><i id="c"/><i id="d"/><i id="e"/></r>';

	public function testEvenKeepsTheZeroBasedEvenPositions()
	{
		$this->assertSame('a,c,e', $this->idList(qp(self::XML, 'i')->even()));
		$this->assertSame('a', $this->idList(qp(self::XML, '#a')->even()));
		$this->assertSame('', $this->idList(qp(self::XML, 'nothing')->even()));
	}

	public function testOddKeepsTheZeroBasedOddPositions()
	{
		$this->assertSame('b,d', $this->idList(qp(self::XML, 'i')->odd()));
		$this->assertSame('', $this->idList(qp(self::XML, '#a')->odd()));
		$this->assertSame('', $this->idList(qp(self::XML, 'nothing')->odd()));
	}

	public function testEvenAndOddAgreeWithThePseudoClasses()
	{
		$qp = qp(self::XML, 'i');

		$this->assertSame($this->idList($qp->filter(':even')), $this->idList($qp->even()));
		$this->assertSame($this->idList($qp->filter(':odd')), $this->idList($qp->odd()));
	}
}
