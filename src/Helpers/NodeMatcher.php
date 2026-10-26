<?php

namespace QueryPath\Helpers;

use DOMElement;
use DOMNode;
use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\DOMTraverser\PseudoClass;
use QueryPath\CSS\DOMTraverser\Util;
use QueryPath\CSS\ParseException;
use SplObjectStorage;

/**
 * Test nodes that are already in hand against a CSS selector.
 *
 * The supplied nodes are themselves the only candidates, as in DOMTraverser::filterNodes().
 *
 * Prefer the set methods wherever there is a set. A per-node pass cannot answer a selector
 * that describes a position within the set (:first, :eq(n), :odd, ...), because each node
 * would be the only member of its own one-element set.
 *
 * matches() remembers each per-node answer, because the walk-and-stop traversals
 * (closest(), nextUntil(), ...) revisit the same nodes from every starting element, and
 * every query shares one parse of the selector and one sibling index for the :nth-*
 * pseudo-classes. An instance must not outlive the call that made it, or a later DOM change
 * could leave it holding stale answers.
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
	 * @var PseudoClass
	 */
	private $psHandler;

	/**
	 * The document's matches for a positional selector, FALSE for any other selector, or NULL
	 * until matchesInDocument() first needs to know.
	 *
	 * @var SplObjectStorage|false|null
	 */
	private $inDocument;

	/**
	 * @param string $selector
	 *   A valid CSS selector.
	 */
	public function __construct($selector)
	{
		$this->selector = $selector;
		$this->results  = new SplObjectStorage();

		// Held open for the matcher's lifetime, which is a single call.
		$this->psHandler = new PseudoClass();
		$this->psHandler->beginQuery();
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
	 * Test a single node as jQuery's .is() does, counting a positional selector (:first,
	 * :eq(n), ...) across the node's whole document.
	 *
	 * The document's matches are found once, on the first call, so every node tested must
	 * belong to the same document.
	 *
	 * @param DOMNode $node
	 *
	 * @return bool
	 * @throws ParseException
	 */
	public function matchesInDocument($node): bool
	{
		if ($this->inDocument === null) {
			$this->inDocument = Util::selectorHasPositionalPseudoClass($this->selector)
				? $this->documentMatches($node->ownerDocument)
				: false;
		}

		return $this->inDocument === false ? $this->matches($node) : $this->inDocument->offsetExists($node);
	}

	/**
	 * Test whether at least one of the given nodes matches as jQuery's .is() tests it.
	 *
	 * @see matchesInDocument()
	 *
	 * @param SplObjectStorage $nodes
	 *
	 * @return bool
	 * @throws ParseException
	 */
	public function matchesAnyInDocument(SplObjectStorage $nodes): bool
	{
		if (! Util::selectorHasPositionalPseudoClass($this->selector)) {
			return $this->matchesAny($nodes);
		}

		foreach ($nodes as $node) {
			if ($node instanceof DOMElement && $this->matchesInDocument($node)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param \DOMDocument $document
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	private function documentMatches($document): SplObjectStorage
	{
		// As in qp($document), the document element stands in for the document, so it can match.
		$scope = new SplObjectStorage();
		if ($document->documentElement) {
			$scope->offsetSet($document->documentElement);
		}

		return (new DOMTraverser($scope, false, null, $this->psHandler))->find($this->selector)->matches();
	}

	/**
	 * Alias of matching().
	 *
	 * @param SplObjectStorage $nodes
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	public function filter(SplObjectStorage $nodes): SplObjectStorage
	{
		return $this->matching($nodes);
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
		return count($this->matching($nodes)) > 0;
	}

	/**
	 * Reduce a set of nodes to those that match the selector, in the order they were given.
	 *
	 * Nodes that are not elements can never match a CSS selector, and are skipped.
	 *
	 * @param SplObjectStorage $nodes
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	public function matching(SplObjectStorage $nodes): SplObjectStorage
	{
		return DOMTraverser::filterNodes($nodes, $this->selector, null, $this->psHandler);
	}
}
