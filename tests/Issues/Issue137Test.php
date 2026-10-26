<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/137
 *
 * Methods that return a new object no longer deep-copy the match set, so end() returns
 * the document's own nodes. clone still deep-copies.
 */

namespace QueryPathTests;

use DOMElement;

/**
 * @ingroup querypath_tests
 */
class Issue137Test extends TestCase
{
	private const XML = '<?xml version="1.0"?><root><p id="one"><i>x</i><b>y</b></p><p id="two"><i>z</i></p></root>';

	public function chainProvider(): array
	{
		return [
			'find'     => [function ($qp) {
				return $qp->find('p');
			}],
			'children' => [function ($qp) {
				return $qp->children();
			}],
			'filter'   => [function ($qp) {
				return $qp->filter('root');
			}],
			'eq'       => [function ($qp) {
				return $qp->eq(0);
			}],
			'not'      => [function ($qp) {
				return $qp->not('p');
			}],
		];
	}

	/**
	 * @dataProvider chainProvider
	 */
	public function testEndReturnsTheOriginalNodes(callable $chain)
	{
		$qp   = qp(self::XML);
		$root = $qp->get(0);

		$previous = $chain($qp)->end();

		$this->assertSame(1, $previous->count());
		$this->assertSame($root, $previous->get(0));
	}

	public function testEndAfterChainedFindsEditsTheDocument()
	{
		$qp = qp(self::XML);

		$qp->find('p')->find('i')->end()->attr('class', 'seen');

		$this->assertSame(2, $qp->find('p.seen')->count());
	}

	public function testResultsAreTheDocumentsNodes()
	{
		$qp       = qp(self::XML);
		$document = $qp->get(0)->ownerDocument;

		$found = $qp->find('p')->children('i');

		$this->assertSame(2, $found->count());
		foreach ($found->get() as $node) {
			$this->assertSame($document, $node->ownerDocument);
			$this->assertSame($document->documentElement, $node->parentNode->parentNode);
		}
		$this->assertSame($qp->get(0), $qp->find('p')->get(0)->parentNode);
	}

	public function testCloneStillDeepCopies()
	{
		$qp = qp(self::XML, 'p');
		$qp->find('i');

		$copy = clone $qp;

		$this->assertSame(2, $copy->count());
		$originals = $qp->get();
		foreach ($copy->get() as $index => $node) {
			$this->assertInstanceOf(DOMElement::class, $node);
			$this->assertNotSame($originals[$index], $node);
			$this->assertNull($node->parentNode);
			$this->assertSame($originals[$index]->getAttribute('id'), $node->getAttribute('id'));
			$this->assertSame($originals[$index]->childNodes->length, $node->childNodes->length);
		}

		$copy->find('i')->attr('class', 'copy');
		$this->assertSame(0, $qp->find('i.copy')->count());

		$found = $qp->find('i');
		$this->assertNotSame($found->get(0), (clone $found)->get(0));
	}
}
