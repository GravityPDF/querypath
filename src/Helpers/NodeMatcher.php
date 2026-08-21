<?php

namespace QueryPath\Helpers;

use DOMElement;
use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\ParseException;
use SplObjectStorage;

/**
 * Test nodes that are already in hand against a CSS selector.
 *
 * This is deliberately different from running a find(): find() searches the
 * <em>descendants</em> of the nodes it is given, so for `<a><b/></a>` it would report
 * that the `a` element "matches" the selector `b`. Here the supplied nodes are
 * themselves the only candidates, so `b` matches the `b` element and nothing else.
 *
 * The DOMTraverser is built in "initialized" mode, which tells it to treat the
 * supplied set as the candidate set rather than seeding it with a descendant
 * search. Combinators are still resolved against the real document by walking up
 * from each candidate, so full selectors (e.g. `div > p`) keep working.
 *
 * The scope node is deliberately left at its default (the document element) so that
 * `:scope` means the same thing here as it does in find(). Passing a candidate as the
 * scope node would make every candidate match `:scope`.
 */
final class NodeMatcher
{

	/**
	 * Reduce a set of nodes to those that match a selector.
	 *
	 * Nodes that are not elements can never match a CSS selector, and are skipped
	 * rather than raising an error.
	 *
	 * @param SplObjectStorage $nodes
	 *   The candidate nodes.
	 * @param string           $selector
	 *   A valid CSS selector.
	 *
	 * @return SplObjectStorage
	 *   The subset of $nodes that match the selector.
	 * @throws ParseException
	 */
	public static function filter(SplObjectStorage $nodes, $selector): SplObjectStorage
	{
		$candidates = new SplObjectStorage();
		foreach ($nodes as $node) {
			if ($node instanceof DOMElement) {
				$candidates->offsetSet($node);
			}
		}

		if (count($candidates) === 0) {
			return $candidates;
		}

		$traverser = new DOMTraverser($candidates, true);
		$traverser->find($selector);
		$matched = $traverser->matches();

		// Returned in the caller's order rather than the traverser's, so callers do not each
		// have to re-walk their own input to restore it.
		$found = new SplObjectStorage();
		foreach ($nodes as $node) {
			if ($matched->offsetExists($node)) {
				$found->offsetSet($node);
			}
		}

		return $found;
	}

	/**
	 * Test whether at least one of the given nodes matches a selector.
	 *
	 * @param SplObjectStorage $nodes
	 * @param string           $selector
	 *
	 * @return bool
	 * @throws ParseException
	 */
	/**
	 * Test whether a single node, taken as an element, matches a selector.
	 *
	 * @param mixed  $node
	 *   The node to test. Anything that is not an element returns FALSE.
	 * @param string $selector
	 *
	 * @return bool
	 * @throws ParseException
	 */
	public static function matchesNode($node, $selector): bool
	{
		if (! $node instanceof DOMElement) {
			return false;
		}

		$nodes = new SplObjectStorage();
		$nodes->offsetSet($node);

		return count(self::filter($nodes, $selector)) > 0;
	}
}
