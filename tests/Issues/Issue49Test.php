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
								 . '<input id="c" type="TeXt" />'
								 . '<input id="d" type="password" />'
								 . '<input id="e" type="checkbox" />'
								 . '<input id="f" type="submit" />'
								 . '<textarea id="g"></textarea>'
								 . '<button id="h">Go</button>'
								 . '</div>';

	/**
	 * Every kind of non-element node the DOM can hand back from contents(), as siblings.
	 */
	protected const MIXED_XML = '<?xml version="1.0"?>'
							   . '<root class="wrap" id="wrap">'
							   . 'Sample'
							   . '<!-- A comment -->'
							   . '<![CDATA[Some data]]>'
							   . '<?target instruction?>'
							   . '<span class="wrap" id="child">Child</span>'
							   . '</root>';

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
		$firstInput = $q->contents()->eq(0);
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
		$q = html5qp(self::INPUT_HTML, 'div');

		// Explicit type="text", no type at all, and mixed-case type.
		foreach (['a', 'b', 'c'] as $id) {
			$this->assertTrue($q->find('#' . $id)->is(':text'), $id);
		}

		// password, checkbox, submit, textarea, button.
		foreach (['d', 'e', 'f', 'g', 'h'] as $id) {
			$this->assertFalse($q->find('#' . $id)->is(':text'), $id);
		}
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
	 * Any selector run against a match set holding a non-element node must return a sane result
	 * rather than fataling on the element-only DOM API.
	 */
	public function testSelectorsAgainstNonElementNodesDoNotThrow(): void
	{
		$contents = qp(self::MIXED_XML, 'root')->contents();

		$kinds = [
			DOMText::class,
			DOMComment::class,
			DOMCdataSection::class,
			DOMProcessingInstruction::class,
		];

		foreach ($kinds as $index => $class) {
			$node = $contents->eq($index);
			$this->assertInstanceOf($class, $node->get(0));
			$this->assertMatchesNothing($node, $class);
		}
	}

	/**
	 * The full battery, so every node kind is held to the same standard.
	 *
	 * @param \QueryPath\DOMQuery $node
	 * @param string               $kind
	 */
	private function assertMatchesNothing($node, $kind): void
	{
		$selectors = ['*', 'span', '.wrap', '#wrap', '[class]', '[class="wrap"]', ':first-child', ':text', 'root span'];
		foreach ($selectors as $selector) {
			$this->assertFalse($node->is($selector), sprintf('%s must not match is(%s)', $kind, $selector));
		}

		foreach (['*', 'span', '.wrap', '#wrap', '[class]'] as $selector) {
			$this->assertCount(0, $node->find($selector), sprintf('%s must not match find(%s)', $kind, $selector));
		}

		$this->assertCount(0, $node->filter('*'), sprintf('%s must not match filter(*)', $kind));
	}

	/**
	 * A match set mixing elements with non-element nodes must still match the
	 * elements it holds.
	 */
	public function testMixedNodeMatchSetStillMatchesItsElements(): void
	{
		$contents = html5qp('<div>Sample<span class="x" id="s"><em id="e">Child</em></span></div>', 'div')
			->contents();

		/* The set holds a text node and an element; neither may cause a fatal. */
		$this->assertCount(2, $contents);

		/* find() reaches the descendants of the elements in the set */
		$this->assertSame(['e'], $this->ids($contents->find('em')));
		$this->assertSame(['e'], $this->ids($contents->find('#e')));

		/* filter() and is() ask about the elements in the set itself */
		$this->assertSame(['s'], $this->ids($contents->filter('span')));
		$this->assertSame(['s'], $this->ids($contents->filter('.x')));
		$this->assertSame(['s'], $this->ids($contents->filter('#s')));
		$this->assertTrue($contents->is('.x'));
	}
}
