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

use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\NotImplementedException;
use QueryPath\CSS\EventHandler;
use QueryPath\CSS\ParseException;
use SPLObjectStorage;

/**
 *  The PseudoClass handler.
 *
 */
class PseudoClass
{

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
				return (boolean) mt_rand(0, 1);
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
			// NON-STANDARD extensions for simple support of even and odd. These
			// are supported by jQuery, FF, and other user agents.
			case 'even':
				return $this->isNthChild($node, 'even');
			case 'odd':
				return $this->isNthChild($node, 'odd');
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

			// Additional pseudo-classes defined in jQuery:
			case 'lt':
				// I'm treating this as "less than or equal to".
				$rule = sprintf('-n + %d', (int) $value);

				// $rule = '-n+15';
				return $this->isNthChild($node, $rule);
			case 'gt':
				// I'm treating this as "greater than"
				// return $this->nodePositionFromEnd($node) > (int) $value;
				return $this->nodePositionFromStart($node) > (int) $value;
			case 'nth':
			case 'eq':
				$rule = (int) $value;

				return $this->isNthChild($node, $rule);
			case 'first':
				return $this->isNthChild($node, 1);
			case 'first-child':
				return $this->isFirst($node);
			case 'last':
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
			case 'matches':
				return $this->has($node, $value);
			break;
			case 'not':
				if (empty($value)) {
					throw new ParseException(':not() requires a value.');
				}

				return $this->isNot($node, $value);
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
	 * @throws ParseException
	 */
	protected function has($node, $selector): bool
	{
		$splos = new SPLObjectStorage();
		$splos->attach($node);
		$traverser = new DOMTraverser($splos, true);
		$results   = $traverser->find($selector)->matches();

		return count($results) > 0;
	}

	/**
	 * Provides :not pseudoclass.
	 *
	 * @throws ParseException
	 */
	protected function isNot($node, $selector): bool
	{
		return ! $this->has($node, $selector);
	}

	/**
	 * Get the relative position of a node in its sibling set.
	 */
	protected function nodePositionFromStart($node, $byType = false): int
	{
		$i   = 1;
		$tag = $node->tagName;
		while (isset($node->previousSibling)) {
			$node = $node->previousSibling;
			if ($node->nodeType === XML_ELEMENT_NODE && ( ! $byType || $node->tagName === $tag)) {
				++$i;
			}
		}

		return $i;
	}

	/**
	 * Get the relative position of a node in its sibling set.
	 *
	 * @param      $node
	 * @param bool $byType
	 *
	 * @return int
	 */
	protected function nodePositionFromEnd($node, $byType = false): int
	{
		$i   = 1;
		$tag = $node->tagName;
		while (isset($node->nextSibling)) {
			$node = $node->nextSibling;
			if ($node->nodeType === XML_ELEMENT_NODE && ( ! $byType || $node->tagName === $tag)) {
				++$i;
			}
		}

		return $i;
	}

	/**
	 * Provides functionality for all "An+B" rules.
	 * Provides nth-child and also the functionality required for:
	 *
	 *- nth-last-child
	 *- even
	 *- odd
	 *- first
	 *- last
	 *- eq
	 *- nth
	 *- nth-of-type
	 *- first-of-type
	 *- last-of-type
	 *- nth-last-of-type
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
		[$groupSize, $elementInGroup] = Util::parseAnB($value);
		$parent = $node->parentNode;
		if (empty($parent)
			|| ($groupSize === 0 && $elementInGroup === 0)
		) {
			return false;
		}

		// First we need to find the position of $node in other elements.
		if ($reverse) {
			$pos = $this->nodePositionFromEnd($node, $byType);
		} else {
			$pos = $this->nodePositionFromStart($node, $byType);
		}

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
