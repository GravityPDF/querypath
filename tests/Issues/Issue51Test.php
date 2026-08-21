<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/51
 *
 * is() used to run a descendant search, which meant it returned TRUE whenever anything
 * *below* the current match set matched the selector. It now behaves like jQuery's is():
 * it tests the elements held in the match set and nothing else.
 */

namespace QueryPathTests;

use SplDoublyLinkedList;

class Issue51Test extends TestCase
{

	/**
	 * The bug as reported: a descendant of an element in the collection made is() return TRUE.
	 */
	public function testIsDoesNotMatchDescendants(): void
	{
		$dom = html5qp('<p><span>foo</span></p>', 'p');

		self::assertTrue($dom->is('p'), 'Should match an element held directly in the collection');
		self::assertFalse($dom->is('span'), 'Should not match a descendant of an element held in the collection');
	}

	/**
	 * The same document, but with the collection left at the document element. Neither the
	 * <p> nor the <span> is in the collection, so neither may match.
	 */
	public function testIsDoesNotMatchDeeperDescendants(): void
	{
		$dom = html5qp('<p><span>foo</span></p>');

		self::assertTrue($dom->is('html'), 'The collection holds the document element');
		self::assertFalse($dom->is('p'), 'Should not match a descendant of an element held in the collection');
		self::assertFalse($dom->is('span'), 'Should not match a descendant of an element held in the collection');
	}

	public function testIsDoesNotMatchAncestors(): void
	{
		$dom = html5qp('<div id="outer"><p><span>foo</span></p></div>', 'span');

		self::assertTrue($dom->is('span'));
		self::assertFalse($dom->is('p'), 'Should not match the parent of an element held in the collection');
		self::assertFalse($dom->is('#outer'), 'Should not match an ancestor of an element held in the collection');
	}

	/**
	 * jQuery returns TRUE when *at least one* of the elements in the set matches.
	 */
	public function testIsMatchesWhenAnyElementInTheSetMatches(): void
	{
		$dom = html5qp('<ul><li id="one">1</li><li class="two">2</li><li>3</li></ul>', 'li');

		self::assertCount(3, $dom);
		self::assertTrue($dom->is('li'));
		self::assertTrue($dom->is('#one'), 'The first element matches');
		self::assertTrue($dom->is('.two'), 'The second element matches');
		self::assertTrue($dom->is('li, dt'), 'Selector groups are supported');
		self::assertFalse($dom->is('.missing'));
		self::assertFalse($dom->is('ul'), 'Should not match the parent of the elements in the collection');
	}

	/**
	 * The elements are still tested in the context of their own document, so combinators and
	 * positional pseudo-classes continue to work.
	 */
	public function testIsSupportsFullSelectors(): void
	{
		$dom = html5qp('<div><p id="pp" class="one two">foo</p></div>', 'p');

		self::assertTrue($dom->is('div p'), 'Descendant combinators are evaluated against the document');
		self::assertTrue($dom->is('div > p'), 'Child combinators are evaluated against the document');
		self::assertTrue($dom->is('p#pp.one.two'));
		self::assertTrue($dom->is('p[id="pp"]'));
		self::assertTrue($dom->is(':first-child'));
		self::assertFalse($dom->is('section p'), 'A non-matching ancestor means no match');
		self::assertFalse($dom->is(':root'));
	}

	public function testIsOnAnEmptyCollectionIsFalse(): void
	{
		$dom = html5qp('<div><p>foo</p></div>', 'section');

		self::assertCount(0, $dom);
		self::assertFalse($dom->is('*'));
	}

	/**
	 * Nodes that are not elements can never match a CSS selector, and must not raise an error.
	 */
	public function testIsOnNonElementNodesIsFalse(): void
	{
		$contents = html5qp('<div>Sample<!-- a comment --></div>', 'div')->contents();

		self::assertCount(2, $contents);
		self::assertFalse($contents->is('div'));
		self::assertFalse($contents->is('*'));
	}

	/**
	 * The DOMNode and Traversable overloads are unchanged.
	 */
	public function testIsStillAcceptsADomNode(): void
	{
		$dom  = html5qp('<ul><li id="one">1</li><li id="two">2</li></ul>');
		$one  = $dom->top('#one');
		$node = $one->get(0);

		self::assertTrue($one->is($node));
		self::assertFalse($dom->top('#two')->is($node));
		self::assertFalse($dom->top('li')->is($node), 'A single node cannot equal a set of two');
	}

	public function testIsStillAcceptsATraversable(): void
	{
		$dom = html5qp('<ul><li id="one">1</li><li id="two">2</li></ul>');

		$list = new SplDoublyLinkedList();
		$list->push($dom->top('#one')->get(0));
		$list->push($dom->top('#two')->get(0));

		self::assertTrue($dom->top('#one,#two')->is($list));
		self::assertFalse($dom->top('#one')->is($list));
	}

	/**
	 * has() is the migration path for anyone who relied on the old containment behaviour.
	 */
	public function testHasProvidesTheOldContainmentBehaviour(): void
	{
		$dom = html5qp('<p><span>foo</span></p>', 'p');

		self::assertFalse($dom->is('span'));
		self::assertCount(1, $dom->branch()->has('span'));
		self::assertCount(0, $dom->branch()->has('em'));
	}

	/**
	 * parents() filters with is(), so it inherited the descendant-matching bug: every ancestor
	 * that merely *contained* a matching element was returned.
	 */
	public function testParentsNoLongerMatchesAncestorsThatOnlyContainTheSelector(): void
	{
		$dom = html5qp('<div id="outer"><section><p id="target">foo</p></section></div>');

		self::assertCount(1, $dom->top('#target')->parents('div'));
		self::assertSame('outer', $dom->top('#target')->parents('div')->attr('id'));
	}
}
