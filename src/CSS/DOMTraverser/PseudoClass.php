<?php
/**
 * @file
 *
 * PseudoClass class.
 *
 * This is the first pass in an experiment to break PseudoClass handling
 * out of the normal traversal. Eventually, this should become a
 * top-level pluggable registry that will allow custom pseudoclasses.
 * For now, though, we just handle the core pseudoclasses.
 */

namespace QueryPath\CSS\DOMTraverser;

use DOMElement;
use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\NotImplementedException;
use QueryPath\CSS\EventHandler;
use QueryPath\CSS\ParseException;
use DOMNode;
use QueryPath\CSS\Parser;
use QueryPath\CSS\Selector;
use QueryPath\CSS\SimpleSelector;
use SPLObjectStorage;

/**
 *  The PseudoClass handler.
 *
 * It also holds what queries can share: parsed selectors and, while a query is open, sibling
 * positions. Nested and per-node queries are passed their caller's handler for that reason.
 */
class PseudoClass
{

	/**
	 * Element positions within their sibling lists, keyed by element.
	 *
	 * Each entry is [fromStart, fromEnd, ofTypeFromStart, ofTypeFromEnd].
	 * Positions are only kept while a query is running: the DOM may be mutated
	 * between queries, so they must not outlive the query that indexed them.
	 *
	 * @var SplObjectStorage|null
	 */
	protected $positions;

	/**
	 * How many queries (including nested :not() and :has() queries) are running.
	 *
	 * @var int
	 */
	protected $queryDepth = 0;

	/**
	 * Parsed an+b rules, keyed by the rule string.
	 *
	 * @var array
	 */
	protected $anbRules = [];

	/**
	 * :has() arguments as relativeSelector() returns them, keyed by the argument.
	 *
	 * @var array
	 */
	protected $relativeSelectors = [];

	/**
	 * Parsed selectors, keyed by the selector string. See parse().
	 *
	 * @var Selector[]
	 */
	protected $selectors = [];

	/**
	 * Start remembering sibling positions, so the :nth-* pseudo-classes index
	 * each sibling list once rather than walking it for every element.
	 *
	 * Calls nest; positions are forgotten when the outermost query ends.
	 */
	public function beginQuery(): void
	{
		if ($this->queryDepth++ === 0) {
			$this->positions = new SplObjectStorage();
		}
	}

	public function endQuery(): void
	{
		if (--$this->queryDepth === 0) {
			$this->positions = null;
		}
	}

	/**
	 * Parse a selector, reusing an earlier parse of the same string.
	 *
	 * DOMTraverser does not modify a parsed selector, so every query that shares this handler can
	 * share the parse.
	 *
	 * @param string $selector
	 *
	 * @return Selector
	 * @throws ParseException
	 */
	public function parse($selector): Selector
	{
		if (! isset($this->selectors[$selector])) {
			$handler = new Selector();
			$parser  = new Parser($selector, $handler);
			$parser->parse();

			$this->selectors[$selector] = $handler;
		}

		return $this->selectors[$selector];
	}

	/**
	 * Tests whether the given element matches the given pseudoclass.
	 *
	 * @param string   $pseudoclass
	 *   The string name of the pseudoclass
	 * @param resource $node
	 *   The DOMNode to be tested.
	 * @param resource $scope
	 *   The DOMElement that is the active root for this node.
	 * @param mixed    $value
	 *   The optional value string provided with this class. This is
	 *   used, for example, in an+b psuedoclasses.
	 *
	 * @return bool
	 * @throws NotImplementedException
	 * @throws ParseException
	 * @retval boolean
	 *   TRUE if the node matches, FALSE otherwise.
	 */
	public function elementMatches($pseudoclass, $node, $scope, $value = null)
	{
		$name = strtolower($pseudoclass);
		// Need to handle known pseudoclasses.
		switch ($name) {
			case 'current':
			case 'past':
			case 'future':
			case 'visited':
			case 'hover':
			case 'active':
			case 'focus':
			case 'animated': //  Last 3 are from jQuery
			case 'visible':
			case 'hidden':
				// These require a UA, which we don't have.
			case 'valid':
			case 'invalid':
			case 'required':
			case 'optional':
			case 'read-only':
			case 'read-write':
				// Since we don't know how to validate elements,
				// we can't supply these.
			case 'dir':
				// FIXME: I don't know how to get directionality info.
			case 'nth-column':
			case 'nth-last-column':
				// We don't know what a column is in most documents.
				// FIXME: Can we do this for HTML?
			case 'target':
				// This requires a location URL, which we don't have.
				return false;
			case 'indeterminate':
				// Because sometimes screwing with people is fun.
				return (bool) mt_rand(0, 1);
			case 'lang':
				// No value = exception.
				if (! isset($value)) {
					throw new NotImplementedException(':lang() requires a value.');
				}

				return $this->lang($node, $value);
			case 'any-link':
				return Util::matchesAttribute($node, 'href')
					   || Util::matchesAttribute($node, 'src')
					   || Util::matchesAttribute($node, 'link');
			case 'link':
				return Util::matchesAttribute($node, 'href');
			case 'local-link':
				return $this->isLocalLink($node);
			case 'root':
				return $node->isSameNode($node->ownerDocument->documentElement);

			// CSS 4 declares the :scope pseudo-class, which describes what was
			// the :x-root QueryPath extension.
			case 'x-root':
			case 'x-reset':
			case 'scope':
				return $node->isSameNode($scope);
			// The jQuery positional pseudo-classes index the *matched set*, not a
			// node's siblings, so they cannot be answered for a node in isolation.
			// QueryPath\CSS\DOMTraverser applies them to its ordered result set
			// once traversal has finished. See Util::applyPositionalPseudoClass().
			case 'even':
			case 'odd':
			case 'lt':
			case 'gt':
			case 'nth':
			case 'eq':
			case 'first':
			case 'last':
				throw new NotImplementedException(
					sprintf(
						':%s filters an ordered result set and cannot be evaluated against a single node. '
						. 'Use a Traverser, which applies it after traversal.',
						$name
					)
				);
			case 'nth-child':
				return $this->isNthChild($node, $value);
			case 'nth-last-child':
				return $this->isNthChild($node, $value, true);
			case 'nth-of-type':
				return $this->isNthChild($node, $value, false, true);
			case 'nth-last-of-type':
				return $this->isNthChild($node, $value, true, true);
			case 'first-of-type':
				return $this->isFirstOfType($node);
			case 'last-of-type':
				return $this->isLastOfType($node);
			case 'only-of-type':
				return $this->isFirstOfType($node) && $this->isLastOfType($node);

			case 'first-child':
				return $this->isFirst($node);
			case 'last-child':
				return $this->isLast($node);
			case 'only-child':
				return $this->isFirst($node) && $this->isLast($node);
			case 'empty':
				return $this->isEmpty($node);
			case 'parent':
				return ! $this->isEmpty($node);

			case 'enabled':
			case 'disabled':
			case 'checked':
				return Util::matchesAttribute($node, $name);
			case 'text':
				return Util::isTextInput($node);

			case 'radio':
			case 'checkbox':
			case 'file':
			case 'password':
			case 'submit':
			case 'image':
			case 'reset':
			case 'button':
				return Util::matchesAttribute($node, 'type', $name);

			case 'header':
				return $this->header($node);
			case 'has':
				return $this->has($node, $value);
			case 'matches':
			case 'is':
			case 'where':
				return $this->is($node, $value, $scope);
			case 'not':
				if (empty($value)) {
					throw new ParseException(':not() requires a value.');
				}

				return $this->isNot($node, $value, $scope);
			// Contains == text matches.
			// In QP 2.1, this was changed.
			case 'contains':
				return $this->contains($node, $value);
			// Since QP 2.1
			case 'contains-exactly':
				return $this->containsExactly($node, $value);
			default:
				throw new ParseException('Unknown Pseudo-Class: ' . $name);
		}
	}

	/**
	 * Pseudo-class handler for :lang
	 *
	 * Note that this does not implement the spec in its entirety because we do
	 * not presume to "know the language" of the document. If anyone is interested
	 * in making this more intelligent, please do so.
	 */
	protected function lang($node, $value)
	{
		// TODO: This checks for cases where an explicit language is
		// set. The spec seems to indicate that an element should inherit
		// language from the parent... but this is unclear.
		$operator = (strpos($value, '-') !== false) ? EventHandler::IS_EXACTLY : EventHandler::CONTAINS_WITH_HYPHEN;

		$match = true;
		foreach ($node->attributes as $attrNode) {
			if ($attrNode->localName === 'lang') {
				if ($attrNode->nodeName === $attrNode->localName) {
					// fprintf(STDOUT, "%s in NS %s\n", $attrNode->name, $attrNode->nodeName);
					return Util::matchesAttribute($node, 'lang', $value, $operator);
				}

				$nsuri = $attrNode->namespaceURI;

				// fprintf(STDOUT, "%s in NS %s\n", $attrNode->name, $nsuri);
				return Util::matchesAttributeNS($node, 'lang', $nsuri, $value, $operator);
			}
		}

		return false;
	}

	/**
	 * Provides jQuery pseudoclass ':header'.
	 *
	 * @param $node
	 *
	 * @return bool
	 */
	protected function header($node): bool
	{
		return preg_match('/^h[1-9]$/i', $node->tagName) === 1;
	}

	/**
	 * Provides pseudoclass :empty.
	 */
	protected function isEmpty($node): bool
	{
		foreach ($node->childNodes as $kid) {
			// We don't want to count PIs and comments. From the spec, it
			// appears that CDATA is also not counted.
			if ($kid->nodeType === XML_ELEMENT_NODE || $kid->nodeType === XML_TEXT_NODE) {
				// As soon as we hit a FALSE, return.
				return false;
			}
		}

		return true;
	}

	/**
	 * Provides jQuery pseudoclass :first.
	 *
	 * @todo
	 *   This can be replaced by isNthChild().
	 */
	protected function isFirst($node): bool
	{
		while (isset($node->previousSibling)) {
			$node = $node->previousSibling;
			if ($node->nodeType === XML_ELEMENT_NODE) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Fast version of first-of-type.
	 */
	protected function isFirstOfType($node)
	{
		$type = $node->tagName;
		while (isset($node->previousSibling)) {
			$node = $node->previousSibling;
			if ($node->nodeType === XML_ELEMENT_NODE && $node->tagName === $type) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Fast version of jQuery :last.
	 */
	protected function isLast($node)
	{
		while (isset($node->nextSibling)) {
			$node = $node->nextSibling;
			if ($node->nodeType === XML_ELEMENT_NODE) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Provides last-of-type.
	 */
	protected function isLastOfType($node)
	{
		$type = $node->tagName;
		while (isset($node->nextSibling)) {
			$node = $node->nextSibling;
			if ($node->nodeType === XML_ELEMENT_NODE && $node->tagName === $type) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Provides :contains() as the original spec called for.
	 *
	 * This is an INEXACT match.
	 */
	protected function contains($node, $value): bool
	{
		$text  = $node->textContent;
		$value = Util::removeQuotes($value);

		return isset($text) && (stripos($text, $value) !== false);
	}

	/**
	 * Provides :contains-exactly QueryPath pseudoclass.
	 *
	 * This is an EXACT match.
	 */
	protected function containsExactly($node, $value): bool
	{
		$text  = $node->textContent;
		$value = Util::removeQuotes($value);

		return isset($text) && $text == $value;
	}

	/**
	 * Provides :has pseudoclass.
	 *
	 * Its argument is a relative selector, so `:has(li)` looks for a descendant
	 * and `:has(> li)`, `:has(+ li)` and `:has(~ li)` for a child or later sibling.
	 *
	 * @throws ParseException
	 */
	protected function has($node, $selector): bool
	{
		foreach ($this->relativeSelector($selector) as [$group, $siblings, $nextOnly, $descends]) {
			if (! $siblings) {
				if ($this->findsAny($node, $node, $group, false)) {
					return true;
				}
			} else {
				// Search the later siblings one at a time, so a match near the node ends the search.
				for ($next = $node->nextSibling; $next !== null; $next = $next->nextSibling) {
					if (! $next instanceof DOMElement) {
						continue;
					}

					// The sibling can match itself, and its descendants when the group goes on to them (`+ ul li`).
					if ($this->findsAny($next, $node, $group, true)
						|| ($descends && $this->findsAny($next, $node, $group, false))
					) {
						return true;
					}

					if ($nextOnly) {
						break;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Parse the argument of :has(), anchoring each group to :scope.
	 *
	 * A group that starts with a combinator (`> li`) has a blank leftmost simple selector,
	 * which becomes :scope. Any other group (`li`) is a descendant of :scope.
	 *
	 * @return array
	 *   One [group, whether it starts at a later sibling (`+`/`~`), whether only the next sibling
	 *   can match, whether it reaches below the sibling it starts from] per group.
	 * @throws ParseException
	 */
	protected function relativeSelector($selector): array
	{
		if (isset($this->relativeSelectors[$selector])) {
			return $this->relativeSelectors[$selector];
		}

		$sibling = SimpleSelector::ADJACENT | SimpleSelector::SIBLING;
		$groups  = [];
		foreach ($this->parse($selector) as $group) {
			// Groups are stored right-to-left, so the last simple selector is the leftmost. It is
			// copied before :scope is added, as the parse is shared.
			$anchor = end($group);
			if (count($group) === 1 || ! $anchor->isBlank()) {
				$anchor             = new SimpleSelector();
				$anchor->combinator = SimpleSelector::ANY_DESCENDANT;
				$group[]            = $anchor;
			} else {
				$anchor                   = clone $anchor;
				$group[count($group) - 1] = $anchor;
			}
			$anchor->pseudoClasses[] = ['name' => 'scope', 'value' => null];

			// `+ li` can only be the next sibling, unless the group goes on to another one (`+ li ~ li`).
			$nextOnly = $anchor->combinator === SimpleSelector::ADJACENT;
			$descends = false;
			foreach (array_slice($group, 0, -1) as $simpleSelector) {
				if ($simpleSelector->combinator & $sibling) {
					$nextOnly = false;
				} elseif ($simpleSelector->combinator) {
					$descends = true;
				}
			}

			$groups[] = [$group, (bool) ($anchor->combinator & $sibling), $nextOnly, $descends];
		}

		return $this->relativeSelectors[$selector] = $groups;
	}

	/**
	 * Whether $group matches $root itself or, when $self is FALSE, any of its descendants.
	 *
	 * @param DOMElement       $root
	 * @param DOMElement       $scope
	 * @param SimpleSelector[] $group
	 * @param bool             $self
	 *
	 * @throws ParseException
	 */
	protected function findsAny(DOMElement $root, DOMElement $scope, array $group, bool $self): bool
	{
		$splos = new SPLObjectStorage();
		$splos->offsetSet($root);
		$traverser = new DOMTraverser($splos, $self, $scope, $this);

		return count($traverser->findGroups([$group])->matches()) > 0;
	}

	/**
	 * Provides :matches pseudoclass (and its :is and :where aliases), which tests the node itself.
	 *
	 * @throws ParseException
	 */
	protected function is($node, $selector, $scope = null): bool
	{
		$splos = new SPLObjectStorage();
		$splos->offsetSet($node);

		return count(DOMTraverser::filterNodes($splos, $selector, $scope, $this)) > 0;
	}

	/**
	 * Provides :not pseudoclass.
	 *
	 * @throws ParseException
	 */
	protected function isNot($node, $selector, $scope = null): bool
	{
		return ! $this->is($node, $selector, $scope);
	}

	/**
	 * Record the position of every element child of $parent.
	 */
	protected function indexSiblings(DOMNode $parent, SplObjectStorage $positions): void
	{
		$elements   = [];
		$typeCounts = [];
		foreach ($parent->childNodes as $child) {
			if ($child->nodeType === XML_ELEMENT_NODE) {
				$tag              = $child->tagName;
				$typeCounts[$tag] = ($typeCounts[$tag] ?? 0) + 1;
				$elements[]       = [$child, $typeCounts[$tag]];
			}
		}

		$count = count($elements);
		foreach ($elements as $i => [$child, $typePos]) {
			$positions[$child] = [$i + 1, $count - $i, $typePos, $typeCounts[$child->tagName] - $typePos + 1];
		}
	}

	/**
	 * Provides functionality for all "An+B" rules.
	 * Provides nth-child and also the functionality required for:
	 *
	 *- nth-last-child
	 *- nth-of-type
	 *- first-of-type
	 *- last-of-type
	 *- nth-last-of-type
	 *
	 * Note that the jQuery positional pseudo-classes (:even, :odd, :first,
	 * :last, :eq(), :nth(), :lt(), :gt()) are NOT handled here. They index the
	 * matched set rather than an element's siblings, and are applied to the
	 * result set by the traverser.
	 *
	 * See also QueryPath::CSS::DOMTraverser::Util::parseAnB().
	 *
	 * @param      $node
	 * @param      $value
	 * @param bool $reverse
	 * @param bool $byType
	 *
	 * @return bool
	 */
	protected function isNthChild($node, $value, $reverse = false, $byType = false): bool
	{
		if (! isset($this->anbRules[$value])) {
			$this->anbRules[$value] = Util::parseAnB($value);
		}
		[$groupSize, $elementInGroup] = $this->anbRules[$value];
		$parent = $node->parentNode;
		if (empty($parent)
			|| ($groupSize === 0 && $elementInGroup === 0)
		) {
			return false;
		}

		// First we need to find the position of $node in other elements.
		// Outside a query nothing is remembered, so index into a throwaway store.
		$positions = $this->positions ?? new SplObjectStorage();
		if (! $positions->offsetExists($node)) {
			$this->indexSiblings($parent, $positions);
		}
		$pos = $positions[$node][($byType ? 2 : 0) + ($reverse ? 1 : 0)];

		// If group size is 0, we just check to see if this
		// is the nth element:
		if ($groupSize === 0) {
			return $pos === $elementInGroup;
		}

		// Next, we normalize $elementInGroup
		if ($elementInGroup < 0) {
			$elementInGroup = $groupSize + $elementInGroup;
		}
		$prod = ($pos - $elementInGroup) / $groupSize;

		return is_int($prod) && $prod >= 0;
	}

	protected function isLocalLink($node): bool
	{
		if (! $node->hasAttribute('href')) {
			return false;
		}
		$url    = $node->getAttribute('href');
		$scheme = parse_url($url, PHP_URL_SCHEME);

		return empty($scheme) || $scheme === 'file';
	}
}
