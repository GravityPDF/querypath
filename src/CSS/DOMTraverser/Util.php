<?php
/**
 * @file
 *
 * Utilities for DOM traversal.
 */

namespace QueryPath\CSS\DOMTraverser;

use DOMNode;
use QueryPath\CSS\EventHandler;
use SplObjectStorage;

/**
 * Utilities for DOM Traversal.
 */
class Util
{
	/**
	 * Check whether the given DOMElement has the given attribute.
	 *
	 * @param      $node
	 * @param      $name
	 * @param null $value
	 * @param int  $operation
	 *
	 * @return bool
	 */
	public static function matchesAttribute($node, $name, $value = null, $operation = EventHandler::IS_EXACTLY): bool
	{
		if (! $node->hasAttribute($name)) {
			return false;
		}

		if (null === $value) {
			return true;
		}

		return self::matchesAttributeValue($value, $node->getAttribute($name), $operation);
	}

	/**
	 * Check whether the given DOMElement has the given namespaced attribute.
	 */
	public static function matchesAttributeNS(
		$node,
		$name,
		$nsuri,
		$value = null,
		$operation = EventHandler::IS_EXACTLY
	) {
		if (! $node->hasAttributeNS($nsuri, $name)) {
			return false;
		}

		if (is_null($value)) {
			return true;
		}

		return self::matchesAttributeValue($value, $node->getAttributeNS($nsuri, $name), $operation);
	}

	/**
	 * Check for attr value matches based on an operation.
	 */
	public static function matchesAttributeValue($needle, $haystack, $operation): bool
	{

		if (strlen($haystack) < strlen($needle)) {
			return false;
		}

		// According to the spec:
		// "The case-sensitivity of attribute names in selectors depends on the document language."
		// (6.3.2)
		// To which I say, "huh?". We assume case sensitivity.
		switch ($operation) {
			case EventHandler::IS_EXACTLY:
				return $needle == $haystack;
			case EventHandler::CONTAINS_WITH_SPACE:
				// XXX: This needs testing!
				return preg_match('/\b/', $haystack) == 1;
			//return in_array($needle, explode(' ', $haystack));
			case EventHandler::CONTAINS_WITH_HYPHEN:
				return in_array($needle, explode('-', $haystack));
			case EventHandler::CONTAINS_IN_STRING:
				return strpos($haystack, $needle) !== false;
			case EventHandler::BEGINS_WITH:
				return strpos($haystack, $needle) === 0;
			case EventHandler::ENDS_WITH:
				//return strrpos($haystack, $needle) === strlen($needle) - 1;
				return preg_match('/' . $needle . '$/', $haystack) == 1;
		}

		return false; // Shouldn't be able to get here.
	}

	/**
	 * Remove leading and trailing quotes.
	 */
	public static function removeQuotes(string $str)
	{
		$f = mb_substr($str, 0, 1);
		$l = mb_substr($str, -1);
		if ($f === $l && ($f === '"' || $f === "'")) {
			$str = mb_substr($str, 1, -1);
		}

		return $str;
	}

	/**
	 * Parse an an+b rule for CSS pseudo-classes.
	 *
	 * Invalid rules return `array(0, 0)`. This is per the spec.
	 *
	 * @param $rule
	 *  Some rule in the an+b format.
	 *
	 * @retval array
	 *  `array($aVal, $bVal)` of the two values.
	 * @return array
	 */
	public static function parseAnB($rule): array
	{
		if ($rule === 'even') {
			return [2, 0];
		}

		if ($rule === 'odd') {
			return [2, 1];
		}

		if ($rule === 'n') {
			return [1, 0];
		}

		if (is_numeric($rule)) {
			return [0, (int) $rule];
		}

		$regex   = '/^\s*([+\-]?[0-9]*)n\s*([+\-]?)\s*([0-9]*)\s*$/';
		$matches = [];
		$res     = preg_match($regex, $rule, $matches);

		// If it doesn't parse, return 0, 0.
		if (! $res) {
			return [0, 0];
		}

		$aVal = $matches[1] ?? 1;
		if ($aVal === '-') {
			$aVal = -1;
		} elseif ($aVal === '') {
			$aVal = 1;
		} else {
			$aVal = (int) $aVal;
		}

		$bVal = 0;
		if (isset($matches[3])) {
			$bVal = (int) $matches[3];
			if (isset($matches[2]) && $matches[2] === '-') {
				$bVal *= -1;
			}
		}

		return [$aVal, $bVal];
	}

	/**
	 * Sort nodes into document order.
	 *
	 * PHP's DOM has no compareDocumentPosition(), so each node is described by the list of
	 * child offsets from the document down to it, and those lists are compared element by
	 * element. Sibling offsets are indexed a whole child list at a time and memoized for the
	 * duration of the sort — computing them by walking previousSibling per node is quadratic
	 * on the width of the parent.
	 *
	 * The memo lives only as long as the call, so a document mutated between calls cannot
	 * produce a stale answer.
	 *
	 * @param array $nodes
	 *
	 * @return array
	 */
	public static function sortDocumentOrder(array $nodes): array
	{
		if (count($nodes) < 2) {
			return array_values($nodes);
		}

		$offsets = new SplObjectStorage();
		$indexed = [];
		foreach ($nodes as $node) {
			$indexed[] = [self::documentOrderPath($node, $offsets), $node];
		}

		usort($indexed, function ($a, $b) {
			return self::comparePaths($a[0], $b[0]);
		});

		$sorted = [];
		foreach ($indexed as $entry) {
			$sorted[] = $entry[1];
		}

		return $sorted;
	}

	/**
	 * Describe a node's position as the child offsets from the document down to it.
	 *
	 * @param DOMNode          $node
	 * @param SplObjectStorage $offsets
	 *
	 * @return array
	 */
	private static function documentOrderPath($node, SplObjectStorage $offsets): array
	{
		$path = [];
		while ($node instanceof DOMNode && $node->parentNode !== null) {
			$path[] = self::siblingOffset($node, $offsets);
			$node   = $node->parentNode;
		}

		// Built leaf-first; array_unshift() per step would be quadratic on depth.
		return array_reverse($path);
	}

	/**
	 * A node's offset among its parent's children.
	 *
	 * The parent's whole child list is indexed on the first request, so sorting a wide set of
	 * siblings costs one pass over the list rather than one pass per node.
	 *
	 * @param DOMNode          $node
	 * @param SplObjectStorage $offsets
	 *
	 * @return int
	 */
	private static function siblingOffset($node, SplObjectStorage $offsets): int
	{
		if ($offsets->offsetExists($node)) {
			return $offsets[$node];
		}

		$offset = 0;
		foreach ($node->parentNode->childNodes as $sibling) {
			$offsets[$sibling] = $offset++;
		}

		if ($offsets->offsetExists($node)) {
			return $offsets[$node];
		}

		// The node has to be one of its parent's children, but do not assume the DOM handed
		// back the same PHP object we were given.
		$offset = 0;
		for ($sibling = $node->previousSibling; $sibling !== null; $sibling = $sibling->previousSibling) {
			++$offset;
		}

		return $offset;
	}

	/**
	 * Compare two paths from documentOrderPath().
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
	private static function comparePaths(array $a, array $b): int
	{
		$shared = min(count($a), count($b));
		for ($i = 0; $i < $shared; ++$i) {
			if ($a[$i] !== $b[$i]) {
				return $a[$i] < $b[$i] ? -1 : 1;
			}
		}

		return count($a) - count($b);
	}
}
