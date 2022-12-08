<?php

namespace QueryPathTests;

class QueryPathIteratorTest extends TestCase
{
	public function testCurrent()
	{
		$qp = qp('<ul><li>Item1</li><li>Item2</li></ul>', 'li');

		$iterator = $qp->getIterator();
		$iterator->rewind();

		$this->assertSame('Item1', $iterator->current()->text());
		$iterator->next();
		$this->assertSame('Item2', $iterator->current()->text());
	}
}
