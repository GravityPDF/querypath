<?php

namespace QueryPathTests;

/**
 * find() searches descendants, as jQuery's find() does.
 *
 * A node already in the match set is not a candidate for its own selector. The document
 * element is the one exception, because QueryPath seeds the match set with the document
 * element rather than with the document node.
 */
class FindDescendantOnlyTest extends TestCase
{

	const XML = '<?xml version="1.0"?><root><a id="a1"><a id="a2"/><b id="b1"/></a></root>';

	public function testFindDoesNotMatchTheNodesItStartsFrom(): void
	{
		$a1 = qp(self::XML, '#a1');
		$this->assertSame('a1', $a1->attr('id'));

		// <a id="a1"> is in the match set, so it is not a candidate: only the nested
		// <a id="a2"> is found.
		$found = $a1->find('a');
		$this->assertCount(1, $found);
		$this->assertSame('a2', $found->attr('id'));
	}

	public function testWildcardDoesNotMatchTheNodesItStartsFrom(): void
	{
		$ids = [];
		foreach (qp(self::XML, '#a1')->find('*') as $node) {
			$ids[] = $node->attr('id');
		}

		$this->assertSame(['a2', 'b1'], $ids);
	}

	public function testTheDocumentElementIsStillReachable(): void
	{
		// qp() seeds the match set with the document element, which stands in for the
		// document, so a selector naming it has to keep working.
		$this->assertCount(1, qp(self::XML)->find('root'));
		$this->assertSame('root', qp(self::XML)->find('root')->tag());

		$this->assertCount(1, qp(self::XML)->find(':root'));
	}

	public function testDescendantsOfTheDocumentElementAreStillFound(): void
	{
		$this->assertCount(2, qp(self::XML)->find('a'));
		$this->assertCount(1, qp(self::XML)->find('b'));
	}

	/**
	 * filter() is the jQuery-equivalent way to ask whether the nodes in hand match.
	 */
	public function testFilterIsTheWayToMatchTheNodesInHand(): void
	{
		$this->assertCount(1, qp(self::XML, '#a1')->filter('a'));
		$this->assertSame('a1', qp(self::XML, '#a1')->filter('a')->attr('id'));
	}
}
