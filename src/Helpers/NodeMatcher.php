<?php

namespace QueryPath\Helpers;

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
 * Sets are evaluated in one pass wherever the caller has a set. A per-node pass cannot
 * answer a selector that describes a position within the set (:first, :eq(n), :odd, ...),
 * because each node would be the only member of its own one-element set.
 *
 * The scope node is deliberately left at its default (the document element) so that
 * `:scope` means the same thing here as it does in find(). Passing a candidate as the
 * scope node would make every candidate match `:scope`.
 *
 * An instance answers the per-node question for one selector, and remembers each answer.
 * The walk-and-stop traversals (closest(), nextUntil(), ...) revisit the same nodes from
 * every starting element, so without the memo they would re-parse the selector for each
 * visit. An instance must not outlive the call that made it, or a later DOM change could
 * leave it holding stale answers.
 */
final class NodeMatcher
{

	/**
	 * @var string
	 */
	private $selector;

	/**
	 * @var SplObjectStorage
	 */
	private $results;

	/**
	 * @param string $selector
	 *   A valid CSS selector.
	 */
	public function __construct($selector)
	{
		$this->selector = $selector;
		$this->results  = new SplObjectStorage();
	}

	/**
	 * Test whether a single node, taken as an element, matches the selector.
	 *
	 * @param mixed $node
	 *   The node to test. Anything that is not an element returns FALSE.
	 *
	 * @return bool
	 * @throws ParseException
	 */
	public function matches($node): bool
	{
		if (! $this->results->offsetExists($node)) {
			$single = new SplObjectStorage();
			$single->offsetSet($node);
			$this->results[$node] = self::matchesAny($single, $this->selector);
		}

		return $this->results[$node];
	}

	/**
	 * Reduce a set of nodes to those that match a selector, preserving their order.
	 *
	 * Nodes that are not elements can never match a CSS selector, and are skipped.
	 *
	 * @param SplObjectStorage $nodes
	 *   The candidate nodes.
	 * @param string           $selector
	 *   A valid CSS selector.
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	public static function filter(SplObjectStorage $nodes, $selector): SplObjectStorage
	{
		if (count($nodes) === 0) {
			return new SplObjectStorage();
		}

		$matched = self::traverse($nodes, $selector);

		// The traverser returns document order; callers such as parents() need their own.
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
	public static function matchesAny(SplObjectStorage $nodes, $selector): bool
	{
		return count($nodes) > 0 && count(self::traverse($nodes, $selector)) > 0;
	}

	/**
	 * @param SplObjectStorage $nodes
	 * @param string           $selector
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	private static function traverse(SplObjectStorage $nodes, $selector): SplObjectStorage
	{
		$traverser = new DOMTraverser($nodes, true);

		return $traverser->find($selector)->matches();
	}
}
