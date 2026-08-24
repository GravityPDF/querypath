<?php
/**
 * @file
 *
 * Utilities for DOM traversal.
 */

namespace QueryPath\CSS\DOMTraverser;

use DOMElement;
use DOMNode;
use QueryPath\CSS\EventHandler;
use QueryPath\CSS\ParseException;
use QueryPath\CSS\Parser;
use QueryPath\CSS\Selector;
use SplObjectStorage;

/**
 * Utilities for DOM Traversal.
 */
class Util
{
	/**
	 * The jQuery positional pseudo-classes.
	 *
	 * These are NOT CSS selectors. Unlike the structural pseudo-classes
	 * (:nth-child(), :first-child, :nth-of-type(), ...) which describe a
	 * node's position among its siblings, these describe a node's position
	 * within the ordered set of nodes matched by the selector. They must
	 * therefore be applied as a filter over the result set once traversal
	 * has completed, and cannot be evaluated one node at a time.
	 *
	 * All of them are zero-indexed, as in jQuery.
	 */
	public const POSITIONAL_PSEUDO_CLASSES = [
		'eq',
		'nth',
		'first',
		'last',
		'lt',
		'gt',
		'even',
		'odd',
	];

	/**
	 * Pseudo-classes that take a selector as their argument.
	 *
	 * If that argument contains a positional pseudo-class then the whole
	 * pseudo-class becomes a set filter too, because the argument has to be
	 * evaluated against the result set rather than against a lone node.
	 */
	private const SELECTOR_ARGUMENT_PSEUDO_CLASSES = [
		'not',
		'has',
		'matches',
	];

	/**
	 * Memoized results for selectorHasPositionalPseudoClass().
	 *
	 * @var bool[]
	 */
	/**
	 * Cap on the positional-selector cache, so a long-running process cannot grow it forever.
	 */
	const POSITIONAL_CACHE_LIMIT = 256;

	private static $positionalSelectorCache = [];

	/**
	 * Check whether the given pseudo-class name is a jQuery positional filter.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public static function isPositionalPseudoClass($name): bool
	{
		return in_array(strtolower((string) $name), self::POSITIONAL_PSEUDO_CLASSES, true);
	}

	/**
	 * Check whether a pseudo-class has to be applied to the whole result set.
	 *
	 * That is true of the positional pseudo-classes themselves, and of
	 * :not()/:has()/:matches() when their argument contains one.
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public static function isSetFilterPseudoClass($name, $value = null): bool
	{
		if (self::isPositionalPseudoClass($name)) {
			return true;
		}

		if (! in_array(strtolower((string) $name), self::SELECTOR_ARGUMENT_PSEUDO_CLASSES, true)) {
			return false;
		}

		return ! empty($value) && self::selectorHasPositionalPseudoClass($value);
	}

	/**
	 * Check whether a selector string uses a positional pseudo-class anywhere.
	 *
	 * Results are memoized: this is called once per node during traversal.
	 *
	 * @param string $selector
	 *
	 * @return bool
	 */
	public static function selectorHasPositionalPseudoClass($selector): bool
	{
		$key = (string) $selector;
		if (isset(self::$positionalSelectorCache[$key])) {
			return self::$positionalSelectorCache[$key];
		}

		$found   = false;
		$handler = new Selector();
		try {
			$parser = new Parser($key, $handler);
			$parser->parse();

			foreach ($handler as $selectorGroup) {
				foreach ($selectorGroup as $simpleSelector) {
					if ($simpleSelector->hasSetFilterPseudoClasses()) {
						$found = true;
						break 2;
					}
				}
			}
		} catch (ParseException $e) {
			// Let the real traversal report the parse error.
			$found = false;
		}

		// The cache is keyed by selector string, which in a long-running process can be
		// unbounded user input. Selectors repeat heavily within one query but rarely across
		// unrelated ones, so the table is dropped wholesale rather than evicted entry by entry.
		if (count(self::$positionalSelectorCache) >= self::POSITIONAL_CACHE_LIMIT) {
			self::$positionalSelectorCache = [];
		}

		self::$positionalSelectorCache[$key] = $found;

		return $found;
	}

	/**
	 * Apply a jQuery positional pseudo-class to an ordered list of nodes.
	 *
	 * The list is expected to already be in document order. See
	 * {@see Util::sortDocumentOrder()}.
	 *
	 * As in jQuery, indexes are zero-based, and a negative index counts back
	 * from the end of the set.
	 *
	 * @param array  $nodes An ordered, zero-indexed list of nodes.
	 * @param string $name  The pseudo-class name.
	 * @param mixed  $value The optional value supplied to the pseudo-class.
	 *
	 * @return array The filtered (and re-indexed) list of nodes.
	 */
	public static function applyPositionalPseudoClass(array $nodes, $name, $value = null): array
	{
		$nodes = array_values($nodes);
		$count = count($nodes);

		if ($count === 0) {
			return [];
		}

		switch (strtolower((string) $name)) {
			case 'first':
				return [$nodes[0]];
			case 'last':
				return [$nodes[$count - 1]];
			case 'eq':
			case 'nth':
				$index = self::normalizePositionalIndex($value, $count);

				return isset($nodes[$index]) ? [$nodes[$index]] : [];
			case 'lt':
				$index = self::normalizePositionalIndex($value, $count);

				return $index > 0 ? array_slice($nodes, 0, $index) : [];
			case 'gt':
				$index = self::normalizePositionalIndex($value, $count);

				return array_slice($nodes, max(0, $index + 1));
			case 'even':
				return self::everyOther($nodes, 0);
			case 'odd':
				return self::everyOther($nodes, 1);
		}

		return $nodes;
	}

	/**
	 * Sort a list of DOM nodes into document order.
	 *
	 * PHP's DOM extension does not expose compareDocumentPosition(), so the
	 * position of each node is derived by walking up the tree and recording
	 * the offset of each ancestor within its parent. Comparing those paths
	 * lexicographically yields document order.
	 *
	 * @param array $nodes
	 *
	 * @return array
	 */
	public static function sortDocumentOrder(array $nodes): array
	{
		$nodes = array_values($nodes);
		if (count($nodes) < 2) {
			return $nodes;
		}

		$siblingOffsets = new SplObjectStorage();

		$decorated = [];
		foreach ($nodes as $offset => $node) {
			$decorated[] = [self::documentOrderPath($node, $siblingOffsets), $offset, $node];
		}

		usort($decorated, function ($a, $b) {
			$comparison = self::comparePaths($a[0], $b[0]);

			// Fall back to the original offset so the sort is stable on PHP 7.
			return $comparison !== 0 ? $comparison : $a[1] - $b[1];
		});

		$sorted = [];
		foreach ($decorated as $item) {
			$sorted[] = $item[2];
		}

		return $sorted;
	}

	/**
	 * Resolve a (possibly negative) positional index against a set size.
	 *
	 * @param mixed $value
	 * @param int   $count
	 *
	 * @return int
	 */
	private static function normalizePositionalIndex($value, int $count): int
	{
		$index = (int) $value;

		return $index < 0 ? $index + $count : $index;
	}

	/**
	 * Return every other item from the list, beginning at $start.
	 *
	 * @param array $nodes
	 * @param int   $start
	 *
	 * @return array
	 */
	private static function everyOther(array $nodes, int $start): array
	{
		$found = [];
		$count = count($nodes);
		for ($i = $start; $i < $count; $i += 2) {
			$found[] = $nodes[$i];
		}

		return $found;
	}

	/**
	 * Build a comparable path describing a node's position in its document.
	 *
	 * @param DOMNode          $node
	 * @param SplObjectStorage $siblingOffsets Memo shared across one sort.
	 *
	 * @return int[]
	 */
	private static function documentOrderPath($node, SplObjectStorage $siblingOffsets): array
	{
		$path = [];
		while ($node instanceof DOMNode && $node->parentNode !== null) {
			$path[] = self::siblingOffset($node, $siblingOffsets);
			$node   = $node->parentNode;
		}

		return array_reverse($path);
	}

	/**
	 * Get a node's offset within its parent, indexing the whole sibling list
	 * the first time any one of them is asked for.
	 *
	 * Walking previousSibling per node is quadratic on wide documents (a table
	 * with thousands of rows, say), and a positional selector can easily ask
	 * for every row.
	 *
	 * @param DOMNode          $node
	 * @param SplObjectStorage $siblingOffsets
	 *
	 * @return int
	 */
	private static function siblingOffset(DOMNode $node, SplObjectStorage $siblingOffsets): int
	{
		if ($siblingOffsets->offsetExists($node)) {
			return $siblingOffsets[$node];
		}

		$offset = 0;
		foreach ($node->parentNode->childNodes as $sibling) {
			$siblingOffsets[$sibling] = $offset++;
		}

		// The node has to be one of its parent's children, but do not assume the
		// DOM handed back the same PHP object we were given.
		if ($siblingOffsets->offsetExists($node)) {
			return $siblingOffsets[$node];
		}

		$offset = 0;
		for ($sibling = $node->previousSibling; $sibling !== null; $sibling = $sibling->previousSibling) {
			++$offset;
		}

		return $offset;
	}

	/**
	 * Lexicographically compare two document order paths.
	 *
	 * @param int[] $a
	 * @param int[] $b
	 *
	 * @return int
	 */
	private static function comparePaths(array $a, array $b): int
	{
		$shared = min(count($a), count($b));
		for ($i = 0; $i < $shared; $i++) {
			if ($a[$i] !== $b[$i]) {
				return $a[$i] < $b[$i] ? -1 : 1;
			}
		}

		return count($a) - count($b);
	}

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
	 * Does this node match jQuery's :text pseudo-class?
	 *
	 * jQuery's :text selects input elements whose type attribute is absent, or is "text"
	 * regardless of case. It says nothing about whether a node is a text node.
	 *
	 * Both selector engines ask this question, so they share one answer — they are meant to
	 * agree, and two copies of the rule would be free to drift apart.
	 *
	 * @param mixed $node
	 *
	 * @return bool
	 */
	public static function isTextInput($node): bool
	{
		if (! $node instanceof DOMElement || strtolower($node->localName) !== 'input') {
			return false;
		}

		// An input with no type attribute defaults to a text input.
		return ! $node->hasAttribute('type') || strtolower($node->getAttribute('type')) === 'text';
	}
}
