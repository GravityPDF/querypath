<?php

namespace QueryPath\Helpers;

use DOMNode;
use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\ParseException;
use SplObjectStorage;

/**
 * Test nodes that are already in hand against a CSS selector.
 *
 * Unlike find(), which searches the <em>descendants</em> of the nodes it is given, the
 * supplied nodes are themselves the only candidates: for `<a><b/></a>`, `b` matches the
 * `b` element and not `a`. Combinators are still resolved against the real document, so
 * full selectors (e.g. `div > p`) keep working.
 *
 * Prefer the set methods wherever there is a set. A per-node pass cannot answer a selector
 * that describes a position within the set (:first, :eq(n), :odd, ...), because each node
 * would be the only member of its own one-element set.
 *
 * matches() remembers each per-node answer, because the walk-and-stop traversals
 * (closest(), nextUntil(), ...) revisit the same nodes from every starting element. An
 * instance must not outlive the call that made it, or a later DOM change could leave it
 * holding stale answers.
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
	 * @param DOMNode $node
	 *   The node to test. A node that is not an element returns FALSE.
	 *
	 * @return bool
	 * @throws ParseException
	 */
	public function matches($node): bool
	{
		if (! $this->results->offsetExists($node)) {
			$single = new SplObjectStorage();
			$single->offsetSet($node);
			$this->results[$node] = $this->matchesAny($single);
		}

		return $this->results[$node];
	}

	/**
	 * Reduce a set of nodes to those that match the selector, preserving their order.
	 *
	 * Nodes that are not elements can never match a CSS selector, and are skipped.
	 *
	 * @param SplObjectStorage $nodes
	 *   The candidate nodes.
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	public function filter(SplObjectStorage $nodes): SplObjectStorage
	{
		if (count($nodes) === 0) {
			return new SplObjectStorage();
		}

		$matched = $this->matching($nodes);

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
	 * Test whether at least one of the given nodes matches the selector.
	 *
	 * @param SplObjectStorage $nodes
	 *
	 * @return bool
	 * @throws ParseException
	 */
	public function matchesAny(SplObjectStorage $nodes): bool
	{
		return count($nodes) > 0 && count($this->matching($nodes)) > 0;
	}

	/**
	 * Find the nodes that match the selector, in document order.
	 *
	 * @param SplObjectStorage $nodes
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	public function matching(SplObjectStorage $nodes): SplObjectStorage
	{
		// "Initialized" mode makes the traverser take the supplied set as its candidates rather
		// than seeding them with a descendant search. The scope node is left at its default (the
		// document element) so that `:scope` means the same here as in find(); scoping to a
		// candidate would make every candidate match `:scope`.
		$traverser = new DOMTraverser($nodes, true);

		return $traverser->find($this->selector)->matches();
	}
}
