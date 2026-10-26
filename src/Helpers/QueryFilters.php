<?php

namespace QueryPath\Helpers;

use DOMElement;
use QueryPath\CSS\DOMTraverser\Util;
use QueryPath\CSS\ParseException;
use QueryPath\DOMQuery;
use QueryPath\Exception;
use QueryPath\Query;
use SplObjectStorage;
use stdClass;

/**
 * Trait QueryFilters
 *
 * @package QueryPath\Helpers
 *
 * @property array matches
 */
trait QueryFilters
{

	/**
	 * Filter a list down to only elements that match the selector.
	 * Use this, for example, to find all elements with a class, or with
	 * certain children.
	 *
	 * @param string $selector
	 *   The selector to use as a filter.
	 *
	 * @return Query The DOMQuery with non-matching items filtered out.*   The DOMQuery with non-matching items
	 *               filtered out.
	 * @throws ParseException
	 * @see filterCallback()
	 * @see map()
	 * @see find()
	 * @see is()
	 * @see filterLambda()
	 */
	public function filter($selector): Query
	{
		return $this->inst((new NodeMatcher($selector))->filter($this->matches), null);
	}

	/**
	 * Filter based on a lambda function.
	 *
	 * The function string will be executed as if it were the body of a
	 * function. It is passed two arguments:
	 * - $index: The index of the item.
	 * - $item: The current Element.
	 * If the function returns boolean FALSE, the item will be removed from
	 * the list of elements. Otherwise it will be kept.
	 *
	 * Example:
	 *
	 * @code
	 * qp('li')->filterLambda('qp($item)->attr("id") == "test"');
	 * @endcode
	 *
	 * The above would filter down the list to only an item whose ID is
	 * 'text'.
	 *
	 * @param string $fn
	 *  Inline lambda function in a string.
	 *
	 * @return DOMQuery
	 * @throws ParseException
	 * @throws Exception
	 *
	 * @see map()
	 * @see mapLambda()
	 * @see filterCallback()
	 * @see filter()
	 * @deprecated
	 *   Relies on create_function(), which was removed in PHP 8.0, so this
	 *   method only works on PHP 7. Use filterCallback() with a closure
	 *   instead. It will be removed in 5.0.
	 */
	public function filterLambda($fn): Query
	{
		if (!function_exists('create_function')) {
			throw new Exception('filterLambda() requires create_function(), which was removed in PHP 8.0. Use filterCallback() with a closure instead.');
		}

		$function = create_function('$index, $item', $fn);
		$found    = new SplObjectStorage();
		$i        = 0;
		foreach ($this->matches as $item) {
			if ($function($i++, $item) !== false) {
				$found->offsetSet($item);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Use regular expressions to filter based on the text content of matched elements.
	 *
	 * Only items that match the given regular expression will be kept. All others will
	 * be removed.
	 *
	 * The regular expression is run against the <i>text content</i> (the PCDATA) of the
	 * elements. This is a way of filtering elements based on their content.
	 *
	 * Example:
	 *
	 * @code
	 *  <?xml version="1.0"?>
	 *  <div>Hello <i>World</i></div>
	 * @endcode
	 *
	 * @code
	 *  <?php
	 *    // This will be 1.
	 *    qp($xml, 'div')->filterPreg('/World/')->matches->count();
	 *  ?>
	 * @endcode
	 *
	 * The return value above will be 1 because the text content of @codeqp($xml, 'div')@endcode is
	 * @codeHello World@endcode.
	 *
	 * Compare this to the behavior of the <em>:contains()</em> CSS3 pseudo-class.
	 *
	 * @param string $regex
	 *  A regular expression.
	 *
	 * @return DOMQuery
	 * @throws ParseException
	 * @see       filterCallback()
	 * @see       preg_match()
	 * @see       filter()
	 */
	public function filterPreg($regex): Query
	{
		$found = new SplObjectStorage();

		foreach ($this->matches as $item) {
			if (preg_match($regex, $item->textContent) > 0) {
				$found->offsetSet($item);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Filter based on a callback function.
	 *
	 * A callback may be any of the following:
	 *  - a function: 'my_func'.
	 *  - an object/method combo: $obj, 'myMethod'
	 *  - a class/method combo: 'MyClass', 'myMethod'
	 * Note that classes are passed in strings. Objects are not.
	 *
	 * Each callback is passed to arguments:
	 *  - $index: The index position of the object in the array.
	 *  - $item: The item to be operated upon.
	 *
	 * If the callback function returns FALSE, the item will be removed from the
	 * set of matches. Otherwise the item will be considered a match and left alone.
	 *
	 * @param callback $callback .
	 *                           A callback either as a string (function) or an array (object, method OR
	 *                           classname, method).
	 *
	 * @return DOMQuery
	 *                           Query path object augmented according to the function.
	 * @throws ParseException
	 * @throws Exception
	 * @see map()
	 * @see is()
	 * @see find()
	 * @see filter()
	 * @see filterLambda()
	 */
	public function filterCallback($callback): Query
	{
		$found = new SplObjectStorage();
		$i     = 0;
		if (is_callable($callback)) {
			foreach ($this->matches as $item) {
				if ($callback($i++, $item) !== false) {
					$found->offsetSet($item);
				}
			}
		} else {
			throw new Exception('The specified callback is not callable.');
		}

		return $this->inst($found, null);
	}

	/**
	 * Run a function on each item in a set.
	 *
	 * The mapping callback can return anything. Whatever it returns will be
	 * stored as a match in the set, though. This means that afer a map call,
	 * there is no guarantee that the elements in the set will behave correctly
	 * with other DOMQuery functions.
	 *
	 * Callback rules:
	 * - If the callback returns NULL, the item will be removed from the array.
	 * - If the callback returns an array, the entire array will be stored in
	 *   the results.
	 * - If the callback returns anything else, it will be appended to the array
	 *   of matches.
	 *
	 * @param callback $callback
	 *  The function or callback to use. The callback will be passed two params:
	 *  - $index: The index position in the list of items wrapped by this object.
	 *  - $item: The current item.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object wrapping a list of whatever values were returned
	 *  by each run of the callback.
	 *
	 * @throws Exception
	 * @throws ParseException
	 * @see find()
	 * @see DOMQuery::get()
	 * @see filter()
	 */
	public function map($callback): Query
	{
		$found = new SplObjectStorage();

		if (is_callable($callback)) {
			$i = 0;
			foreach ($this->matches as $item) {
				$c = call_user_func($callback, $i, $item);
				if (isset($c)) {
					if (is_array($c) || $c instanceof Iterable) {
						foreach ($c as $retval) {
							if (! is_object($retval)) {
								$tmp              = new stdClass();
								$tmp->textContent = $retval;
								$retval           = $tmp;
							}
							$found->offsetSet($retval);
						}
					} else {
						if (! is_object($c)) {
							$tmp              = new stdClass();
							$tmp->textContent = $c;
							$c                = $tmp;
						}
						$found->offsetSet($c);
					}
				}
				++$i;
			}
		} else {
			throw new Exception('Callback is not callable.');
		}

		return $this->inst($found, null);
	}

	/**
	 * Narrow the items in this object down to only a slice of the starting items.
	 *
	 * @param integer $start
	 *  Where in the list of matches to begin the slice.
	 * @param integer $length
	 *  The number of items to include in the slice. If nothing is specified, the
	 *  all remaining matches (from $start onward) will be included in the sliced
	 *  list.
	 *
	 * @return DOMQuery
	 * @throws ParseException
	 * @see array_slice()
	 */
	public function slice($start, $length = 0): Query
	{
		$end   = $length;
		$found = new SplObjectStorage();
		if ($start >= $this->count()) {
			return $this->inst($found, null);
		}

		$i = $j = 0;
		foreach ($this->matches as $m) {
			if ($i >= $start) {
				if ($end > 0 && $j >= $end) {
					break;
				}
				$found->offsetSet($m);
				++$j;
			}
			++$i;
		}

		return $this->inst($found, null);
	}

	/**
	 * Run a callback on each item in the list of items.
	 *
	 * Rules of the callback:
	 * - A callback is passed two variables: $index and $item. (There is no
	 *   special treatment of $this, as there is in jQuery.)
	 *   - You will want to pass $item by reference if it is not an
	 *     object (DOMNodes are all objects).
	 * - A callback that returns FALSE will stop execution of the each() loop. This
	 *   works like break in a standard loop.
	 * - A TRUE return value from the callback is analogous to a continue statement.
	 * - All other return values are ignored.
	 *
	 * @param callback $callback
	 *  The callback to run.
	 *
	 * @return DOMQuery
	 *  The DOMQuery.
	 * @throws Exception
	 * @see filter()
	 * @see map()
	 * @see eachLambda()
	 */
	public function each($callback): Query
	{
		if (is_callable($callback)) {
			$i = 0;
			foreach ($this->matches as $item) {
				if (call_user_func($callback, $i, $item) === false) {
					return $this;
				}
				++$i;
			}
		} else {
			throw new Exception('Callback is not callable.');
		}

		return $this;
	}

	/**
	 * An each() iterator that takes a lambda function.
	 *
	 * @param string $lambda
	 *  The lambda function. This will be passed ($index, &$item).
	 *
	 * @return DOMQuery
	 *  The DOMQuery object.
	 * @throws Exception
	 * @deprecated
	 *   Relies on create_function(), which was removed in PHP 8.0, so this
	 *   method only works on PHP 7. Use each() with a closure instead. It
	 *   will be removed in 5.0.
	 * @see each()
	 * @see filterLambda()
	 * @see filterCallback()
	 * @see map()
	 */
	public function eachLambda($lambda): Query
	{
		if (!function_exists('create_function')) {
			throw new Exception('eachLambda() requires create_function(), which was removed in PHP 8.0. Use each() with a closure instead.');
		}

		$index = 0;
		foreach ($this->matches as $item) {
			$fn = create_function('$index, &$item', $lambda);
			if ($fn($index, $item) === false) {
				return $this;
			}
			++$index;
		}

		return $this;
	}

	/**
	 * Get the matches at even, zero-based positions: 0, 2, 4, etc.
	 *
	 * As in jQuery, and as filter(':even') does, so the first match is kept.
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping the matches at even positions.
	 * @throws ParseException
	 * @see    odd()
	 * @since  2.1
	 * @author eabrand
	 */
	public function even(): Query
	{
		return $this->everyOther(0);
	}

	/**
	 * Get the matches at odd, zero-based positions: 1, 3, 5, etc.
	 *
	 * As in jQuery, and as filter(':odd') does, so the first match is dropped.
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping the matches at odd positions.
	 * @throws ParseException
	 * @see    even()
	 * @since  2.1
	 * @author eabrand
	 */
	public function odd(): Query
	{
		return $this->everyOther(1);
	}

	/**
	 * Keep every second match, starting at the given zero-based position.
	 *
	 * @param int $offset
	 *  0 for the even positions, 1 for the odd.
	 *
	 * @return DOMQuery
	 * @throws ParseException
	 */
	private function everyOther(int $offset): Query
	{
		$found = new SplObjectStorage();
		$i     = 0;
		foreach ($this->matches as $m) {
			if ($i++ % 2 === $offset && $m->nodeType === XML_ELEMENT_NODE) {
				$found->offsetSet($m);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the first matching element.
	 *
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping all of the children.
	 * @throws ParseException
	 * @see    prev()
	 * @since  2.1
	 * @author eabrand
	 * @see    next()
	 */
	public function first(): Query
	{
		$found = new SplObjectStorage();
		foreach ($this->elements() as $m) {
			$found->offsetSet($m);
			break;
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the first child element of each matching element.
	 *
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping the first child element of each match.
	 * @throws ParseException
	 * @see    prev()
	 * @since  2.1
	 * @author eabrand
	 * @see    next()
	 */
	public function firstChild(): Query
	{
		$found = new SplObjectStorage();
		foreach ($this->matches as $m) {
			foreach ($m->childNodes as $c) {
				if ($c->nodeType === XML_ELEMENT_NODE) {
					$found->offsetSet($c);
					break;
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the last matching element.
	 *
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping all of the children.
	 * @throws ParseException
	 * @see    prev()
	 * @since  2.1
	 * @author eabrand
	 * @see    next()
	 */
	public function last(): Query
	{
		$found = new SplObjectStorage();
		$item  = null;
		foreach ($this->elements() as $m) {
			$item = $m;
		}
		if ($item) {
			$found->offsetSet($item);
		}

		return $this->inst($found, null);
	}

	/**
	 * Get the last child element of each matching element.
	 *
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping the last child element of each match.
	 * @throws ParseException
	 * @see    prev()
	 * @since  2.1
	 * @author eabrand
	 * @see    next()
	 */
	public function lastChild(): Query
	{
		$found = new SplObjectStorage();
		$item  = null;
		foreach ($this->matches as $m) {
			foreach ($m->childNodes as $c) {
				if ($c->nodeType === XML_ELEMENT_NODE) {
					$item = $c;
				}
			}
			if ($item) {
				$found->offsetSet($item);
				$item = null;
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get all siblings after an element until the selector is reached.
	 *
	 * For each element in the DOMQuery, get the siblings that appear after it,
	 * stopping before the first one that matches the selector.
	 *
	 * As in jQuery, a positional selector (`:first`, `:eq()`, ...) counts across the
	 * whole document, so `nextUntil(':eq(3)')` stops at the document's fourth element.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object, now containing the matching siblings.
	 * @throws ParseException
	 * @see    prevAll()
	 * @see    children()
	 * @see    siblings()
	 * @since  2.1
	 * @author eabrand
	 * @see    next()
	 */
	public function nextUntil($selector = null): Query
	{
		return $this->inDocumentOrder($this->walk('nextSibling', $selector, false));
	}

	/**
	 * Get the previous siblings for each element in the DOMQuery
	 * until the selector is reached.
	 *
	 * For each element in the DOMQuery, get the previous siblings, stopping
	 * before the first one that matches the selector. A positional selector
	 * counts across the whole document, as in nextUntil().
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object, now wrapping previous sibling elements.
	 * @throws ParseException
	 * @see    prev()
	 * @see    nextAll()
	 * @see    siblings()
	 * @see    contents()
	 * @see    children()
	 * @since  2.1
	 * @author eabrand
	 */
	public function prevUntil($selector = null): Query
	{
		return $this->inReverseDocumentOrder($this->walk('previousSibling', $selector, false));
	}

	/**
	 * Get all ancestors of each element in the DOMQuery until the selector is reached.
	 *
	 * The walk stops before the first ancestor that matches the selector. A positional
	 * selector counts across the whole document, as in nextUntil().
	 *
	 * @param string $selector
	 *  A valid CSS 3 Selector.
	 *
	 * @return DOMQuery
	 *  A DOMNode object containing the matching ancestors.
	 * @throws ParseException
	 * @see    siblings()
	 * @see    children()
	 * @since  2.1
	 * @author eabrand
	 * @see    parent()
	 */
	public function parentsUntil($selector = null): Query
	{
		return $this->inReverseDocumentOrder($this->walk('parentNode', $selector, false));
	}

	/**
	 * Reduce the matched set to just one.
	 *
	 * This will take a matched set and reduce it to just one item -- the item
	 * at the index specified. This is a destructive operation, and can be undone
	 * with {@link end()}.
	 *
	 * @param $index
	 *  The index of the element to keep. The rest will be
	 *  discarded.
	 *
	 * @return DOMQuery
	 * @throws ParseException
	 * @see is()
	 * @see end()
	 * @see get()
	 */
	public function eq($index): Query
	{
		return $this->inst($this->getNthMatch($index), null);
	}

	/**
	 * Filter a list to contain only items that do NOT match.
	 *
	 * @param string $selector
	 *  A selector to use as a negation filter. If the filter is matched, the
	 *  element will be removed from the list.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object with matching items filtered out.
	 * @throws ParseException
	 * @throws Exception
	 * @see find()
	 */
	public function not($selector): Query
	{
		$found = new SplObjectStorage();
		if ($selector instanceof DOMElement) {
			foreach ($this->matches as $m) {
				if ($m !== $selector) {
					$found->offsetSet($m);
				}
			}
		} elseif (is_array($selector)) {
			foreach ($this->matches as $m) {
				if (! in_array($m, $selector, true)) {
					$found->offsetSet($m);
				}
			}
		} else {
			$exclude = $selector instanceof SplObjectStorage ? $selector : (new NodeMatcher($selector))->matching($this->matches);
			foreach ($this->matches as $m) {
				if (! $exclude->offsetExists($m)) {
					$found->offsetSet($m);
				}
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Find the closest element matching the selector.
	 *
	 * This finds the closest match in the ancestry chain. It first checks the
	 * present element. If the present element does not match, this traverses up
	 * the ancestry chain (e.g. checks each parent) looking for an item that matches.
	 *
	 * It is provided for jQuery 1.3 compatibility. As in jQuery, a positional selector
	 * (`:first`, `:eq()`, ...) never matches.
	 *
	 * @param string $selector
	 *  A CSS Selector to match.
	 *
	 * @return DOMQuery
	 *  The set of matches.
	 * @throws ParseException
	 * @since 2.0
	 */
	public function closest($selector): Query
	{
		// As in jQuery, nothing is closest to an empty selector.
		if (empty($selector) || Util::selectorHasPositionalPseudoClass($selector)) {
			return $this->inst(new SplObjectStorage(), null);
		}

		return $this->inDocumentOrder($this->walk('parentNode', $selector, true, true));
	}

	/**
	 * Get the immediate parent of each element in the DOMQuery.
	 *
	 * If a selector is passed, this will return the nearest matching parent for
	 * each element in the DOMQuery. A positional selector is tested as in next().
	 *
	 * @param string $selector
	 *  A valid CSS3 selector.
	 *
	 * @return DOMQuery
	 *  A DOMNode object wrapping the matching parents.
	 * @throws Exception
	 * @see siblings()
	 * @see parents()
	 * @see children()
	 */
	public function parent($selector = null): Query
	{
		return $this->inDocumentOrder($this->walk('parentNode', $selector, true));
	}

	/**
	 * Get all ancestors of each element in the DOMQuery.
	 *
	 * If a selector is present, only matching ancestors will be retrieved.
	 *
	 * @param string $selector
	 *  A valid CSS 3 Selector.
	 *
	 * @return DOMQuery
	 *  A DOMNode object containing the matching ancestors.
	 * @throws ParseException
	 * @throws Exception
	 * @see children()
	 * @see parent()
	 * @see siblings()
	 */
	public function parents($selector = null): Query
	{
		return $this->inReverseDocumentOrder($this->walk('parentNode', null, false), $selector);
	}

	/**
	 * Get the next sibling of each element in the DOMQuery.
	 *
	 * If a selector is provided, the next matching sibling will be returned.
	 *
	 * A positional selector (`:first`, `:eq()`, ...) is tested against each sibling
	 * alone, so `:first` matches any of them and `:eq(1)` none. jQuery filters the
	 * combined results instead, which differs only for a set of more than one element.
	 *
	 * @param string $selector
	 *  A CSS3 selector.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object.
	 * @throws Exception
	 * @throws ParseException
	 * @see nextAll()
	 * @see prev()
	 * @see children()
	 * @see contents()
	 * @see parent()
	 * @see parents()
	 */
	public function next($selector = null): Query
	{
		return $this->inst($this->walk('nextSibling', $selector, true), null);
	}

	/**
	 * Get all siblings after an element.
	 *
	 * For each element in the DOMQuery, get all siblings that appear after
	 * it. If a selector is passed in, then only siblings that match the
	 * selector will be included.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object, now containing the matching siblings.
	 * @throws Exception
	 * @throws ParseException
	 * @see next()
	 * @see prevAll()
	 * @see children()
	 * @see siblings()
	 */
	public function nextAll($selector = null): Query
	{
		return $this->inDocumentOrder($this->walk('nextSibling', null, false), $selector);
	}

	/**
	 * Get the next sibling before each element in the DOMQuery.
	 *
	 * For each element in the DOMQuery, this retrieves the previous sibling
	 * (if any). If a selector is supplied, it retrieves the first matching
	 * sibling (if any is found). A positional selector is tested as in next().
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector.
	 *
	 * @return DOMQuery
	 *  A DOMNode object, now containing any previous siblings that have been
	 *  found.
	 * @throws Exception
	 * @throws ParseException
	 * @see prevAll()
	 * @see next()
	 * @see siblings()
	 * @see children()
	 */
	public function prev($selector = null): Query
	{
		return $this->inst($this->walk('previousSibling', $selector, true), null);
	}

	/**
	 * Get the previous siblings for each element in the DOMQuery.
	 *
	 * For each element in the DOMQuery, get all previous siblings. If a
	 * selector is provided, only matching siblings will be retrieved.
	 *
	 * @param string $selector
	 *  A valid CSS 3 selector.
	 *
	 * @return DOMQuery
	 *  The DOMQuery object, now wrapping previous sibling elements.
	 * @throws ParseException
	 * @throws Exception
	 * @see siblings()
	 * @see contents()
	 * @see children()
	 * @see prev()
	 * @see nextAll()
	 */
	public function prevAll($selector = null): Query
	{
		return $this->inReverseDocumentOrder($this->walk('previousSibling', null, false), $selector);
	}

	/**
	 * Get the children of the elements in the DOMQuery object.
	 *
	 * If a selector is provided, the list of children will be filtered through
	 * the selector.
	 *
	 * @param string $selector
	 *  A valid selector.
	 *
	 * @return DOMQuery
	 *  A DOMQuery wrapping all of the children.
	 * @throws ParseException
	 * @see parent()
	 * @see parents()
	 * @see next()
	 * @see prev()
	 * @see removeChildren()
	 */
	public function children($selector = null): Query
	{
		$children = new SplObjectStorage();
		foreach ($this->matches as $m) {
			foreach ($m->childNodes as $c) {
				if ($c->nodeType === XML_ELEMENT_NODE) {
					$children->offsetSet($c);
				}
			}
		}

		return $this->inst($this->filterCandidates($children, $selector), null);
	}

	/**
	 * Get all child nodes (not just elements) of all items in the matched set.
	 *
	 * It gets only the immediate children, not all nodes in the subtree.
	 *
	 * This does not process iframes. Xinclude processing is dependent on the
	 * DOM implementation and configuration.
	 *
	 * @return DOMQuery
	 *  A DOMNode object wrapping all child nodes for all elements in the
	 *  DOMNode object.
	 * @throws ParseException
	 * @see text()
	 * @see html()
	 * @see innerHTML()
	 * @see xml()
	 * @see innerXML()
	 * @see find()
	 */
	public function contents(): Query
	{
		$found = new SplObjectStorage();
		foreach ($this->matches as $m) {
			if (empty($m->childNodes)) {
				continue;
			}
			foreach ($m->childNodes as $c) {
				$found->offsetSet($c);
			}
		}

		return $this->inst($found, null);
	}

	/**
	 * Get a list of siblings for elements currently wrapped by this object.
	 *
	 * This will compile a list of every sibling of every element in the
	 * current list of elements.
	 *
	 * Note that if two siblings are present in the DOMQuery object to begin with,
	 * then both will be returned in the matched set, since they are siblings of each
	 * other. In other words,if the matches contain a and b, and a and b are siblings of
	 * each other, than running siblings will return a set that contains
	 * both a and b.
	 *
	 * @param string $selector
	 *  If the optional selector is provided, siblings will be filtered through
	 *  this expression.
	 *
	 * @return DOMQuery
	 *  The DOMQuery containing the matched siblings.
	 * @throws ParseException
	 * @throws ParseException
	 * @see parent()
	 * @see parents()
	 * @see contents()
	 * @see children()
	 */
	public function siblings($selector = null): Query
	{
		$found = new SplObjectStorage();
		foreach ($this->matches as $m) {
			$parent = $m->parentNode;
			foreach ($parent->childNodes as $n) {
				if ($n->nodeType === XML_ELEMENT_NODE && $n !== $m) {
					$found->offsetSet($n);
				}
			}
		}

		return $this->inDocumentOrder($found, $selector);
	}

	/**
	 * Walk from each matched element along a DOM axis, collecting the elements passed.
	 *
	 * @param string      $axis
	 *  The property to follow: 'nextSibling', 'previousSibling' or 'parentNode'.
	 * @param string|null $selector
	 *  An optional CSS selector. With $first, the element to collect; otherwise, the element
	 *  to stop before.
	 * @param bool        $first
	 *  Collect only the first element matching the selector (the first element, if there is
	 *  none), rather than every element up to the first match.
	 * @param bool        $inclusive
	 *  Start the walk at the matched element itself rather than at its neighbour.
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	private function walk(string $axis, $selector, bool $first, bool $inclusive = false): SplObjectStorage
	{
		$found   = new SplObjectStorage();
		$matcher = empty($selector) ? null : new NodeMatcher($selector);

		foreach ($this->matches as $m) {
			$n = $inclusive ? $m : $m->{$axis};
			for (; $n !== null && $n->nodeType !== XML_DOCUMENT_NODE; $n = $n->{$axis}) {
				if ($n->nodeType !== XML_ELEMENT_NODE) {
					continue;
				}

				if ($first) {
					if ($matcher === null || $matcher->matches($n)) {
						$found->offsetSet($n);
						break;
					}
					continue;
				}

				// Every element beyond one already collected was collected with it, up to the same stop.
				// jQuery tests the stop with .is(), so a positional stop counts across the document.
				if ($found->offsetExists($n) || ($matcher !== null && $matcher->matchesInDocument($n))) {
					break;
				}
				$found->offsetSet($n);
			}
		}

		return $found;
	}

	/**
	 * Wrap a traversal's results, optionally filtered by a selector, in document order.
	 *
	 * The results of a traversal are accumulated per starting element, so a set built from
	 * more than one would otherwise be grouped by starting element, as jQuery's never are.
	 *
	 * @param SplObjectStorage $nodes
	 * @param string|null      $selector
	 *
	 * @return Query
	 * @throws ParseException
	 */
	private function inDocumentOrder(SplObjectStorage $nodes, $selector = null): Query
	{
		return $this->inst($this->sortDocumentOrder($this->filterCandidates($nodes, $selector), false), null);
	}

	/**
	 * As inDocumentOrder(), in reverse document order, as jQuery returns the walks that head
	 * backwards: parents(), parentsUntil(), prevAll() and prevUntil().
	 *
	 * @param SplObjectStorage $nodes
	 * @param string|null      $selector
	 *
	 * @return Query
	 * @throws ParseException
	 */
	private function inReverseDocumentOrder(SplObjectStorage $nodes, $selector = null): Query
	{
		return $this->inst($this->sortDocumentOrder($this->filterCandidates($nodes, $selector), true), null);
	}

	/**
	 * @param SplObjectStorage $nodes
	 * @param bool             $reverse
	 *
	 * @return SplObjectStorage
	 */
	private function sortDocumentOrder(SplObjectStorage $nodes, bool $reverse): SplObjectStorage
	{
		// A single starting element's walk is already in order.
		if (count($nodes) < 2 || count($this->matches) < 2) {
			return $nodes;
		}

		$ordered = Util::sortDocumentOrder(iterator_to_array($nodes, false));
		if ($reverse) {
			$ordered = array_reverse($ordered);
		}

		$sorted = new SplObjectStorage();
		foreach ($ordered as $node) {
			$sorted->offsetSet($node);
		}

		return $sorted;
	}

	/**
	 * Reduce a set of candidates to those matching an optional selector, preserving their order.
	 *
	 * @param SplObjectStorage $candidates
	 * @param string           $selector
	 *
	 * @return SplObjectStorage
	 * @throws ParseException
	 */
	private function filterCandidates(SplObjectStorage $candidates, $selector): SplObjectStorage
	{
		if (empty($selector)) {
			return $candidates;
		}

		return (new NodeMatcher($selector))->filter($candidates);
	}
}
