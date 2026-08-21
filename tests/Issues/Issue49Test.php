<?php

namespace QueryPathTests;

use DOMCdataSection;
use DOMComment;
use DOMProcessingInstruction;
use DOMText;

class Issue49Test extends TestCase
{
	protected const INPUT_HTML = '<div>'
								 . '<input id="a" type="text" />'
								 . '<input id="b" />'
								 . '<input id="c" type="TEXT" />'
								 . '<input id="d" type="password" />'
								 . '<input id="e" type="checkbox" />'
								 . '<input id="f" type="submit" />'
								 . '<textarea id="g"></textarea>'
								 . '<button id="h">Go</button>'
								 . '</div>';

	/**
	 * Get the ID of every element in the match set.
	 *
	 * @param \QueryPath\DOMQuery $query
	 *
	 * @return array
	 */
	protected function ids($query): array
	{
		$ids = [];
		foreach ($query as $item) {
			$ids[] = $item->attr('id');
		}
		sort($ids);

		return $ids;
	}

	public function testCheckingForMatchingTextInputs(): void
	{
		$q = html5qp('<div><input name="text1" type="text" /><input name="text2" /></div>', 'div');

		/*
		 * The collection holds the <div>. It is not itself a text input, but it contains two,
		 * so the containment question is asked with has() and the matches with find().
		 */
		$this->assertCount(1, $q->has(':text'));
		$this->assertCount(2, $q->find(':text'));

		/* The inputs themselves match: an explicit type="text", and an <input> with no type */
		$this->assertTrue($q->find('input')->is(':text'));
		$this->assertTrue($q->find('[name="text1"]')->is(':text'));
		$this->assertTrue($q->find('[name="text2"]')->is(':text'));

		/* contents() here holds the two <input> elements, not text nodes */
		$firstInput = $q->find('div')->contents()->eq(0);
		$this->assertTrue($firstInput->is(':text'));
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

	/**
	 * As in jQuery, ':text' matches an `input` whose type is absent or 'text'
	 * (case-insensitively), and nothing else.
	 *
	 * @see https://api.jquery.com/text-selector/
	 */
	public function testTextSelectorOnlyMatchesTextInputs(): void
	{
		$q = html5qp(self::INPUT_HTML, 'div');

		$this->assertSame(['a', 'b', 'c'], $this->ids($q->find(':text')));
	}

	public function testTextSelectorMatchesTheInputItself(): void
	{
		$this->assertTrue(html5qp('<div><input type="text" /></div>', 'input')->is(':text'));
		$this->assertTrue(html5qp('<div><input /></div>', 'input')->is(':text'));
		$this->assertTrue(html5qp('<div><input type="TeXt" /></div>', 'input')->is(':text'));

		$this->assertFalse(html5qp('<div><input type="password" /></div>', 'input')->is(':text'));
		$this->assertFalse(html5qp('<div><input type="checkbox" /></div>', 'input')->is(':text'));
		$this->assertFalse(html5qp('<div><textarea></textarea></div>', 'textarea')->is(':text'));
		$this->assertFalse(html5qp('<div><button>Go</button></div>', 'button')->is(':text'));
	}

	/**
	 * remove() runs the selector through the legacy CSS engine, which must agree
	 * with find().
	 */
	public function testTextSelectorInTheLegacyEngine(): void
	{
		$q = html5qp(self::INPUT_HTML, 'div');

		$this->assertSame(['a', 'b', 'c'], $this->ids($q->remove(':text')));
		$this->assertCount(0, $q->find(':text'));
	}

	/**
	 * Any selector run against a match set holding a text node must return a
	 * sane result rather than fataling on the element-only DOM API.
	 */
	public function testSelectorsAgainstATextNodeDoNotThrow(): void
	{
		$textNode = html5qp('<div class="wrap" id="wrap">Sample<span>Child</span></div>', 'div')
			->contents()
			->eq(0);

		$this->assertInstanceOf(DOMText::class, $textNode->get(0));

		$this->assertFalse($textNode->is('*'));
		$this->assertFalse($textNode->is('span'));
		$this->assertFalse($textNode->is('.wrap'));
		$this->assertFalse($textNode->is('#wrap'));
		$this->assertFalse($textNode->is('[class]'));
		$this->assertFalse($textNode->is('[class="wrap"]'));
		$this->assertFalse($textNode->is(':first-child'));
		$this->assertFalse($textNode->is('div span'));

		$this->assertCount(0, $textNode->find('*'));
		$this->assertCount(0, $textNode->find('span'));
		$this->assertCount(0, $textNode->find('.wrap'));
		$this->assertCount(0, $textNode->find('#wrap'));
		$this->assertCount(0, $textNode->find('[class]'));
		$this->assertCount(0, $textNode->filter('*'));
	}

	public function testSelectorsAgainstACommentNodeDoNotThrow(): void
	{
		$comment = html5qp('<div class="wrap" id="wrap"><!-- A comment --><span>Child</span></div>', 'div')
			->contents()
			->eq(0);

		$this->assertInstanceOf(DOMComment::class, $comment->get(0));

		$this->assertFalse($comment->is('*'));
		$this->assertFalse($comment->is('span'));
		$this->assertFalse($comment->is('.wrap'));
		$this->assertFalse($comment->is('#wrap'));
		$this->assertFalse($comment->is('[class]'));
		$this->assertFalse($comment->is(':text'));

		$this->assertCount(0, $comment->find('*'));
		$this->assertCount(0, $comment->find('span'));
		$this->assertCount(0, $comment->find('[class]'));
	}

	public function testSelectorsAgainstCdataAndProcessingInstructionNodesDoNotThrow(): void
	{
		$contents = qp(
			'<?xml version="1.0"?><root><![CDATA[Some data]]><?target instruction?><child class="c" id="i">Text</child></root>',
			'root'
		)->contents();

		$cdata = $contents->eq(0);
		$pi    = $contents->eq(1);

		$this->assertInstanceOf(DOMCdataSection::class, $cdata->get(0));
		$this->assertInstanceOf(DOMProcessingInstruction::class, $pi->get(0));

		foreach ([$cdata, $pi] as $node) {
			$this->assertFalse($node->is('*'));
			$this->assertFalse($node->is('child'));
			$this->assertFalse($node->is('.c'));
			$this->assertFalse($node->is('#i'));
			$this->assertFalse($node->is('[class]'));

			$this->assertCount(0, $node->find('*'));
			$this->assertCount(0, $node->find('child'));
		}
	}

	/**
	 * A match set mixing elements with non-element nodes must still match the
	 * elements it holds.
	 */
	public function testMixedNodeMatchSetStillMatchesItsElements(): void
	{
		$contents = html5qp('<div>Sample<span class="x" id="s">Child</span></div>', 'div')->contents();

		$this->assertCount(2, $contents);
		$this->assertSame(['s'], $this->ids($contents->find('span')));
		$this->assertSame(['s'], $this->ids($contents->find('.x')));
		$this->assertSame(['s'], $this->ids($contents->find('#s')));
		$this->assertSame(['s'], $this->ids($contents->filter('span')));
		$this->assertTrue($contents->is('.x'));
	}
}
