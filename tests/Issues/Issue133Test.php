<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/133
 *
 * `:scope` in find() is the element the search starts from, as in jQuery's
 * `$(el).find(':scope > li')`, and each element in turn when the set holds several.
 */

namespace QueryPathTests;

use QueryPath\CSS\DOMTraverser\Util;

class Issue133Test extends TestCase
{
	public const XML = '<?xml version="1.0"?><root><ul id="u1"><li id="a"/><li id="b"><ul id="u3"><li id="d"/></ul></li></ul><ul id="u2"><li id="c"/></ul></root>';

	public function testScopeIsTheElementFindStartsFrom()
	{
		$this->assertSame('a,b', $this->idList(qp(self::XML, '#u1')->find(':scope > li')));
		$this->assertSame('d', $this->idList(qp(self::XML, '#u1')->find('li:not(:scope > li)')));
		$this->assertSame('a,b', $this->idList(qp(self::XML, '#u1')->find('li:matches(:scope > li)')));
		$this->assertSame('', $this->idList(qp(self::XML, '#u1')->find(':scope')));
	}

	public function testScopeIsEachElementOfTheSet()
	{
		$this->assertSame('a,b,c', $this->idList(qp(self::XML, '#u1, #u2')->find(':scope > li')));
		$this->assertSame('a,b,d,c', $this->idList(qp(self::XML, 'ul')->find(':scope > li')));
		$this->assertSame('', $this->idList(qp(self::XML, '#u1, #u2')->find(':scope + ul li')));
		$this->assertSame('u3', $this->idList(qp(self::XML, '#u1, #u2')->find(':scope > li > ul')));
	}

	public function testPositionalPseudoClassesCountPerElementWhenScopeIsUsed()
	{
		$this->assertSame('a,c', $this->idList(qp(self::XML, '#u1, #u2')->find(':scope > li:first')));
		$this->assertSame('a', $this->idList(qp(self::XML, '#u1, #u2')->find('li:first')));
	}

	public function testFindInPlaceAndTheConstructor()
	{
		$this->assertSame('a,b', $this->idList(qp(self::XML, '#u1')->findInPlace(':scope > li')));

		$u1 = qp(self::XML, '#u1')->get(0);
		$this->assertSame('a,b', $this->idList(qp($u1, ':scope > li')));
	}

	public function testRemoveWithASelector()
	{
		$qp = qp(self::XML, '#u1');
		$this->assertSame('a,b', $this->idList($qp->remove(':scope > li')));
		$this->assertSame('', $this->idList($qp->find('li')));
	}

	public function testScopeIsStillTheDocumentElementFromTheTop()
	{
		$this->assertSame('u1,u2', $this->idList(qp(self::XML)->find(':scope > ul')));
		$this->assertSame('root', qp(self::XML)->find(':scope')->tag());
		$this->assertSame('root', qp(self::XML, 'li')->top()->find(':scope')->tag());
	}

	public function testHasKeepsItsOwnScope()
	{
		$this->assertSame('b', $this->idList(qp(self::XML, '#u1')->find('li:has(> ul)')));
		$this->assertSame('u3', $this->idList(qp(self::XML, '#u1')->find('ul:has(> li)')));
	}

	public function testFilterAndIsAreUnchanged()
	{
		$this->assertSame('', $this->idList(qp(self::XML, 'ul')->filter(':scope')));
		$this->assertSame('', $this->idList(qp(self::XML, 'root')->children(':scope')));
		$this->assertTrue(qp(self::XML, 'root')->is(':scope'));
	}

	/**
	 * @dataProvider scopeSelectors
	 */
	public function testSelectorUsesScope($selector, $expected)
	{
		$this->assertSame($expected, Util::selectorUsesScope($selector));
	}

	public function scopeSelectors(): array
	{
		return [
			'plain'         => ['ul > li', false],
			':scope'        => [':scope > li', true],
			':x-root'       => [':x-root > li', true],
			'second group'  => ['ol, :scope li', true],
			'inside :not()' => ['li:not(:scope > li)', true],
			'nested'        => ['li:matches(:not(:scope > li))', true],
			'inside :has()' => ['li:has(:scope > ul)', false],
			'attribute'     => ['[name=scope]', false],
			'parse error'   => [':scope[', false],
		];
	}
}
