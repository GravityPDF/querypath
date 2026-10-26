<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/136
 *
 * `:is()` and `:where()` are the CSS Selectors 4 names for `:matches()`. QueryPath does not use
 * specificity, so `:where()` matches exactly as `:is()` does.
 */

namespace QueryPathTests;

class Issue136Test extends TestCase
{
	public const XML = '<?xml version="1.0"?><ul><li id="a"/><li id="b" class="x"/><li id="c" class="x"/></ul>';

	/**
	 * @dataProvider aliases
	 */
	public function testAliasesMatchAsMatchesDoes(string $name)
	{
		$this->assertSame('b', $this->idList(qp(self::XML)->find("li:$name(#b)")));
		$this->assertSame('b', $this->idList(qp(self::XML)->find(":$name(#b)")));
		$this->assertSame('a,b', $this->idList(qp(self::XML)->find("li:$name(#a, #b)")));
		$this->assertSame('a', $this->idList(qp(self::XML)->find("li:not(:$name(.x))")));
		$this->assertSame('b,c', $this->idList(qp(self::XML, 'li')->filter(":$name(.x)")));
		$this->assertTrue(qp(self::XML, '#c')->is(":$name(.x)"));
	}

	/**
	 * @dataProvider aliases
	 */
	public function testPositionalArgumentIsAppliedToTheSet(string $name)
	{
		$this->assertSame('a', $this->idList(qp(self::XML)->find("li:$name(:first)")));
		$this->assertSame('b', $this->idList(qp(self::XML)->find("li:$name(.x:first)")));
		$this->assertSame('a,c', $this->idList(qp(self::XML)->find("li:not(:$name(:eq(1)))")));
	}

	public function aliases(): array
	{
		return [
			'is'         => ['is'],
			'where'      => ['where'],
			'upper case' => ['IS'],
		];
	}
}
