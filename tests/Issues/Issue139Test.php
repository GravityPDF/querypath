<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/139
 *
 * siblings() on an element with no parent.
 */

namespace QueryPathTests;

class Issue139Test extends TestCase
{
	public function testSiblingsOfAParentlessElementIsEmpty()
	{
		$doc  = qp('<?xml version="1.0"?><root/>')->get(0)->ownerDocument;
		$lone = $doc->createElement('lone');

		$this->assertSame(0, qp($lone)->siblings()->count());
		$this->assertSame(0, qp($lone)->siblings('*')->count());
	}

	public function testSiblingsSkipsAParentlessElementInASet()
	{
		$qp   = qp('<?xml version="1.0"?><r><i id="a"/><i id="b"/><i id="c"/></r>', '#b');
		$lone = $qp->get(0)->ownerDocument->createElement('lone');

		$this->assertSame('a,c', $this->idList($qp->add($lone)->siblings()));
	}

	public function testSiblingsInADetachedSubtree()
	{
		$doc = qp('<?xml version="1.0"?><root/>')->get(0)->ownerDocument;
		$div = $doc->createElement('div');
		$a   = $div->appendChild($doc->createElement('a'));
		$b   = $div->appendChild($doc->createElement('b'));

		$this->assertSame([$b], qp($a)->siblings()->get());
	}
}
