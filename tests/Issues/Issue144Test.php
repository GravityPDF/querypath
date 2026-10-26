<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/144
 *
 * slice($start, $end) takes an exclusive end index. Expected values are jQuery 3.7.1's.
 */

namespace QueryPathTests;

class Issue144Test extends TestCase
{
	private const XML = '<?xml version="1.0"?><r><i id="a"/><i id="b"/><i id="c"/><i id="d"/><i id="e"/></r>';

	/**
	 * @dataProvider sliceProvider
	 */
	public function testSliceTakesAnEndIndex(array $args, string $expected)
	{
		$this->assertSame($expected, $this->idList(qp(self::XML, 'i')->slice(...$args)));
	}

	public function sliceProvider(): array
	{
		return [
			'slice(1, 3)'   => [[1, 3], 'b,c'],
			'slice(1, -1)'  => [[1, -1], 'b,c,d'],
			'slice(2, 0)'   => [[2, 0], ''],
			'slice(0)'      => [[0], 'a,b,c,d,e'],
			'slice(2)'      => [[2], 'c,d,e'],
			'slice(1, 9)'   => [[1, 9], 'b,c,d,e'],
			'slice(3, 2)'   => [[3, 2], ''],
			'slice(2, 2)'   => [[2, 2], ''],
			'slice(-3, -1)' => [[-3, -1], 'c,d'],
			'slice(-2, 5)'  => [[-2, 5], 'd,e'],
			'slice(0, -5)'  => [[0, -5], ''],
			'slice(0, -9)'  => [[0, -9], ''],
			'slice(-9, 2)'  => [[-9, 2], 'a,b'],
			'slice(5)'      => [[5], ''],
		];
	}
}
