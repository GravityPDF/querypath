<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/140
 *
 * is() counts a positional pseudo-class across the document, as jQuery's .is()
 * does, where filter() counts it within the set. The expected values are
 * jQuery 3.7.1's.
 */

namespace QueryPathTests;

use SplObjectStorage;

class Issue140Test extends TestCase
{
	// Document order: r, a, b, c, d.
	private const XML = '<?xml version="1.0"?><r><i id="a"/><i id="b"/><i id="c"/><i id="d"/></r>';

	public function issueTableProvider(): array
	{
		return [
			'#c :first'        => [['c'], ':first', false],
			'#c i:first'       => [['c'], 'i:first', false],
			'#a i:last'        => [['a'], 'i:last', false],
			'#c, #d i:eq(1)'   => [['c', 'd'], 'i:eq(1)', false],
			'#a :eq(1)'        => [['a'], ':eq(1)', true],
			'#c i:eq(2)'       => [['c'], 'i:eq(2)', true],
			'#c :eq(2)'        => [['c'], ':eq(2)', false],
			'#c, #d i:last'    => [['c', 'd'], 'i:last', true],
			'#a, #b i:gt(1)'   => [['a', 'b'], 'i:gt(1)', false],
			'#a, #d i:odd'     => [['a', 'd'], 'i:odd', true],
			'#b i:not(:first)' => [['b'], 'i:not(:first)', true],
			'#a i:not(:first)' => [['a'], 'i:not(:first)', false],
		];
	}

	/**
	 * @dataProvider issueTableProvider
	 */
	public function testIsCountsAPositionalSelectorAcrossTheDocument(array $ids, $selector, $expected)
	{
		$this->assertSame($expected, $this->selectIds(self::XML, ...$ids)->is($selector));
	}

	public function testFilterStillCountsWithinTheSet()
	{
		$this->assertSame('c', $this->idList($this->selectIds(self::XML, 'c')->filter('i:first')));
		$this->assertSame('d', $this->idList($this->selectIds(self::XML, 'c', 'd')->filter('i:eq(1)')));
	}

	public function testNonPositionalSelectorsAreUnchanged()
	{
		$qp = $this->selectIds(self::XML, 'b', 'c');

		$this->assertTrue($qp->is('i'));
		$this->assertTrue($qp->is('#c'));
		$this->assertTrue($qp->is('r > i:nth-child(2)'));
		$this->assertFalse($qp->is('#a'));
		$this->assertFalse($qp->is('r'));
	}

	public function testNodeAndSetArgumentsAreUnchanged()
	{
		$qp = $this->selectIds(self::XML, 'c');
		$this->assertTrue($qp->is($qp->get(0)));
		$this->assertFalse($qp->is(qp(self::XML, '#c')->get(0)));

		$set = new SplObjectStorage();
		$set->offsetSet($qp->get(0));
		$this->assertTrue($qp->is($set));
	}

	public function testNodesThatAreNotElementsAreSkipped()
	{
		$xml = '<?xml version="1.0"?><r>text<i id="a"/><!-- c --><i id="b"/></r>';

		$this->assertTrue(qp($xml, 'r')->contents()->is('i:first'));
		$this->assertFalse(qp($xml, 'r')->contents()->is('i:eq(5)'));
		$this->assertFalse(qp($xml, 'r')->contents()->eq(0)->is(':first'));
	}
}
