<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/135
 *
 * replaceAll() inserts each replacement with its children.
 */

namespace QueryPathTests;

/**
 * @ingroup querypath_tests
 */
class Issue135Test extends TestCase
{
	public function testReplaceAllCopiesChildren()
	{
		$document = qp('<?xml version="1.0"?><root><p id="p1"/><p id="p2"/></root>')->get(0)->ownerDocument;

		qp('<?xml version="1.0"?><w><x a="1">hi<y/></x></w>', 'x')->replaceAll('p', $document);

		$copies = qp($document)->children();
		$this->assertSame(['x', 'x'], array_map(function ($node) {
			return $node->tagName;
		}, $copies->get()));
		foreach ($copies->get() as $copy) {
			$this->assertSame('1', $copy->getAttribute('a'));
			$this->assertSame('hi', $copy->textContent);
			$this->assertSame(1, qp($copy)->children('y')->count());
		}
	}

	public function testReplaceAllInsertsSeparateCopies()
	{
		$document = qp('<?xml version="1.0"?><root><p/><p/></root>')->get(0)->ownerDocument;
		$source   = qp('<?xml version="1.0"?><w><x><y/></x></w>', 'x');

		$source->replaceAll('p', $document);

		$copies = qp($document)->find('x')->get();
		$this->assertCount(2, $copies);
		$this->assertNotSame($copies[0], $copies[1]);
		$this->assertNotSame($copies[0]->firstChild, $copies[1]->firstChild);
		$this->assertSame(1, qp($source->get(0)->ownerDocument)->find('x > y')->count());
	}
}
