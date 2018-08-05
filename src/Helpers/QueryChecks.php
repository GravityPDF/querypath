<?php

namespace QueryPath\Helpers;

use QueryPath\CSS\ParseException;
use QueryPath\DOMQuery;
use QueryPath\Exception;
use QueryPath\Query;

/**
 * Trait QueryChecks
 *
 * @package QueryPath\Helpers
 *
 * @property array matches
 */
trait QueryChecks
{

    /**
     * Given a selector, this checks to see if the current set has one or more matches.
     *
     * Unlike jQuery's version, this supports full selectors (not just simple ones).
     *
     * @param string|\DOMNode $selector
     *   The selector to search for. As of QueryPath 2.1.1, this also supports passing a
     *   DOMNode object.
     * @return boolean
     *   TRUE if one or more elements match. FALSE if no match is found.
     * @see get()
     * @see eq()
     * @throws Exception
     * @throws Exception
     */
    public function is($selector): bool
    {
        if (is_object($selector)) {
            if ($selector instanceof \DOMNode) {
                return count($this->matches) === 1 && $selector->isSameNode($this->get(0));
            }

            if ($selector instanceof \Traversable) {
                if (count($selector) !== count($this->matches)) {
                    return false;
                }
                // Without $seen, there is an edge case here if $selector contains the same object
                // more than once, but the counts are equal. For example, [a, a, a, a] will
                // pass an is() on [a, b, c, d]. We use the $seen SPLOS to prevent this.
                $seen = new \SplObjectStorage();
                foreach ($selector as $item) {
                    if (!$this->matches->contains($item) || $seen->contains($item)) {
                        return false;
                    }
                    $seen->attach($item);
                }

                return true;
            }
            throw new Exception('Cannot compare an object to a DOMQuery.');
        }

        return $this->branch($selector)->count() > 0;
    }

    /**
     * Reduce the elements matched by DOMQuery to only those which contain the given item.
     *
     * There are two ways in which this is different from jQuery's implementation:
     * - We allow ANY DOMNode, not just DOMElements. That means this will work on
     *   processor instructions, text nodes, comments, etc.
     * - Unlike jQuery, this implementation of has() follows QueryPath standard behavior
     *   and modifies the existing object. It does not create a brand new object.
     *
     * @param mixed $contained
     *     - If $contained is a CSS selector (e.g. '#foo'), this will test to see
     *     if the current DOMQuery has any elements that contain items that match
     *     the selector.
     *     - If $contained is a DOMNode, then this will test to see if THE EXACT DOMNode
     *     exists in the currently matched elements. (Note that you cannot match across DOM trees, even if it is the
     *     same document.)
     * @since  2.1
     * @author eabrand
     * @todo   It would be trivially easy to add support for iterating over an array or Iterable of DOMNodes.
     * @return DOMQuery
     * @throws ParseException
     */
    public function has($contained): Query
    {
        /*
    if (count($this->matches) == 0) {
      return false;
    }
     */
        $found = new \SplObjectStorage();

        // If it's a selector, we just get all of the DOMNodes that match the selector.
        $nodes = [];
        if (is_string($contained)) {
            // Get the list of nodes.
            $nodes = $this->branch($contained)->get();
        } elseif ($contained instanceof \DOMNode) {
            // Make a list with one node.
            $nodes = [$contained];
        }

        // Now we go through each of the nodes that we are testing. We want to find
        // ALL PARENTS that are in our existing DOMQuery matches. Those are the
        // ones we add to our new matches.
        foreach ($nodes as $original_node) {
            $node = $original_node;
            while (!empty($node)/* && $node != $node->ownerDocument*/) {
                if ($this->matches->contains($node)) {
                    $found->attach($node);
                }
                $node = $node->parentNode;
            }
        }

        return $this->inst($found, NULL);
    }

    /**
     * Returns TRUE if any of the elements in the DOMQuery have the specified class.
     *
     * @param string $class
     *  The name of the class.
     * @return boolean
     *  TRUE if the class exists in one or more of the elements, FALSE otherwise.
     * @see addClass()
     * @see removeClass()
     */
    public function hasClass($class): bool
    {
        foreach ($this->matches as $m) {
            if ($m->hasAttribute('class')) {
                $vals = explode(' ', $m->getAttribute('class'));
                if (in_array($class, $vals)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check to see if the given attribute is present.
     *
     * This returns TRUE if <em>all</em> selected items have the attribute, or
     * FALSE if at least one item does not have the attribute.
     *
     * @param string $attrName
     *  The attribute name.
     * @return boolean
     *  TRUE if all matches have the attribute, FALSE otherwise.
     * @since 2.0
     * @see   attr()
     * @see   hasClass()
     */
    public function hasAttr($attrName): bool
    {
        foreach ($this->matches as $match) {
            if (!$match->hasAttribute($attrName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove the named attribute from all elements in the current DOMQuery.
     *
     * This will remove any attribute with the given name. It will do this on each
     * item currently wrapped by DOMQuery.
     *
     * As is the case in jQuery, this operation is not considered destructive.
     *
     * @param string $name
     *  Name of the parameter to remove.
     * @return \QueryPath\DOMQuery
     *  The DOMQuery object with the same elements.
     * @see attr()
     */
    public function removeAttr($name): Query
    {
        foreach ($this->matches as $m) {
            $m->removeAttribute($name);
        }

        return $this;
    }
}