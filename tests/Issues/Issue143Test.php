<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/143
 * and https://github.com/GravityPDF/querypath/issues/146
 *
 * even(), odd(), first() and last() count and keep every node in the set, not
 * just the elements. Expected values are jQuery 3.7.1's.
 */

namespace QueryPathTests;

class Issue143Test extends TestCase
{
	// contents(): "x", a, "y", b, "z".
	private const XML = '<?xml version="1.0"?><r>x<i id="a"/>y<i id="b"/>z</r>';

	public function testEvenCountsAndKeepsEveryNode()
	{
		$this->assertSame(['x', 'y', 'z'], $this->describe($this->contents()->even()));
	}

	public function testOddCountsAndKeepsEveryNode()
	{
		$this->assertSame(['a', 'b'], $this->describe($this->contents()->odd()));
	}

	public function testFirstIsTheFirstNodeOfAnyType()
	{
		$c = $this->contents();

		$this->assertSame(['x'], $this->describe($c->first()));
	}

	public function testLastIsTheLastNodeOfAnyType()
	{
		$c = $this->contents();

		$this->assertSame(['z'], $this->describe($c->last()));
	}

	public function testOnlyElementsAfterFilteringOrChildren()
	{
		$c = $this->contents();

		$this->assertSame(['a'], $this->describe($c->filter('*')->first()));
		$this->assertSame(['b'], $this->describe($c->filter('*')->last()));
		$this->assertSame(['a'], $this->describe($c->filter('*')->even()));
		$this->assertSame(['b'], $this->describe($c->filter('*')->odd()));
		$this->assertSame(['a'], $this->describe(qp(self::XML, 'r')->children()->first()));
	}

	public function testEmptySet()
	{
		$qp = qp(self::XML, 'nothing');

		$this->assertSame(0, $qp->first()->count());
		$this->assertSame(0, $qp->last()->count());
		$this->assertSame(0, $qp->even()->count());
		$this->assertSame(0, $qp->odd()->count());
	}

	private function contents()
	{
		return qp(self::XML, 'r')->contents();
	}

	/**
	 * Each node's ID if it is an element, or its text otherwise.
	 */
	private function describe($query): array
	{
		return array_map(function ($node) {
			return $node instanceof \DOMElement ? $node->getAttribute('id') : $node->textContent;
		}, $query->get());
	}
}
