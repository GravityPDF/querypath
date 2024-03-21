<?php

namespace QueryPathTests;

class Issue49Test extends TestCase
{
	public function testCheckingForMatchingTextInputs(): void
	{
		$q = html5qp('<div><input name="text1" type="text" /><input name="text2" /></div>', 'div');

		/* Check if the DOMNode or its children matches */
		$this->assertTrue($q->is(':text'));
		$this->assertCount(2, $q->find(':text'));

		$textNode = $q->find('div')->contents()->eq(0);
		$this->assertTrue($textNode->is(':text'));
	}

	public function testCheckingForEmptyTextInputs(): void
	{
		$q = html5qp('<div>Sample</div>', 'div');

		/* Check if the DOMNode or its children matches */
		$this->assertFalse($q->is(':text'));
		$this->assertCount(0, $q->find(':text'));

		/* check if a text node matches */
		$textNode = $q->find('div')->contents()->eq(0);
		$this->assertFalse($textNode->is(':text'));
	}
}