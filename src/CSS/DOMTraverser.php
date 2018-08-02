<?php
/** @file
 * Traverse a DOM.
 */

namespace QueryPath\CSS;

use \QueryPath\CSS\DOMTraverser\Util;
use \QueryPath\CSS\DOMTraverser\PseudoClass;
use \QueryPath\CSS\DOMTraverser\PseudoElement;
use QueryPath\CSS\SimpleSelector;
use SplObjectStorage;

/**
 * Traverse a DOM, finding matches to the selector.
 *
 * This traverses a DOMDocument and attempts to find
 * matches to the provided selector.
 *
 * \b How this works
 *
 * This performs a bottom-up search. On the first pass,
 * it attempts to find all of the matching elements for the
 * last simple selector in a selector.
 *
 * Subsequent passes attempt to eliminate matches from the
 * initial matching set.
 *
 * Example:
 *
 * Say we begin with the selector `foo.bar baz`. This is processed
 * as follows:
 *
 * - First, find all baz elements.
 * - Next, for any baz element that does not have foo as an ancestor,
 *   eliminate it from the matches.
 * - Finally, for those that have foo as an ancestor, does that foo
 *   also have a class baz? If not, it is removed from the matches.
 *
 * \b Extrapolation
 *
 * Partial simple selectors are almost always expanded to include an
 * element.
 *
 * Examples:
 *
 * - `:first` is expanded to `*:first`
 * - `.bar` is expanded to `*.bar`.
 * - `.outer .inner` is expanded to `*.outer *.inner`
 *
 * The exception is that IDs are sometimes not expanded, e.g.:
 *
 * - `#myElement` does not get expanded
 * - `#myElement .class` \i may be expanded to `*#myElement *.class`
 *   (which will obviously not perform well).
 */
class DOMTraverser implements Traverser
{
    protected $matches     = [];
    protected $selector;
    protected $dom;
    protected $initialized = true;
    protected $psHandler;
    protected $scopeNode;

    /**
     * Build a new DOMTraverser.
     *
     * This requires a DOM-like object or collection of DOM nodes.
     *
     * @param \SPLObjectStorage $splos
     * @param bool $initialized
     * @param null $scopeNode
     */
    public function __construct(\SPLObjectStorage $splos, bool $initialized = false, $scopeNode = NULL)
    {
        $this->psHandler   = new PseudoClass();
        $this->initialized = $initialized;

        // Re-use the initial splos
        $this->matches = $splos;

        if (count($splos) !== 0) {
            $splos->rewind();
            $first = $splos->current();
            if ($first instanceof \DOMDocument) {
                $this->dom = $first;//->documentElement;
            } else {
                $this->dom = $first->ownerDocument;//->documentElement;
            }

            $this->scopeNode = $scopeNode;
            if (empty($scopeNode)) {
                $this->scopeNode = $this->dom->documentElement;
            }
        }

        // This assumes a DOM. Need to also accomodate the case
        // where we get a set of elements.
        /*
        $this->dom = $dom;
        $this->matches = new \SplObjectStorage();
        $this->matches->attach($this->dom);
         */
    }

    public function debug($msg)
    {
        fwrite(STDOUT, PHP_EOL . $msg);
    }

    /**
     * Given a selector, find the matches in the given DOM.
     *
     * This is the main function for querying the DOM using a CSS
     * selector.
     *
     * @param string $selector
     *   The selector.
     * @return DOMTraverser a list of matched
     *   DOMNode objects.
     * @throws ParseException
     */
    public function find($selector) : DOMTraverser
    {
        // Setup
        $handler = new Selector();
        $parser  = new Parser($selector, $handler);
        $parser->parse();
        $this->selector = $handler;

        //$selector = $handler->toArray();
        $found = $this->newMatches();
        foreach ($handler as $selectorGroup) {
            // Initialize matches if necessary.
            if ($this->initialized) {
                $candidates = $this->matches;
            } else {
                $candidates = $this->initialMatch($selectorGroup[0], $this->matches);
            }

            /** @var \DOMElement $candidate */
            foreach ($candidates as $candidate) {
                // fprintf(STDOUT, "Testing %s against %s.\n", $candidate->tagName, $selectorGroup[0]);
                if ($this->matchesSelector($candidate, $selectorGroup)) {
                    // $this->debug('Attaching ' . $candidate->nodeName);
                    $found->attach($candidate);
                }
            }
        }
        $this->setMatches($found);

        return $this;
    }

    public function matches()
    {
        return $this->matches;
    }

    /**
     * Check whether the given node matches the given selector.
     *
     * A selector is a group of one or more simple selectors combined
     * by combinators. This determines if a given selector
     * matches the given node.
     *
     * @attention
     * Evaluation of selectors is done recursively. Thus the length
     * of the selector is limited to the recursion depth allowed by
     * the PHP configuration. This should only cause problems for
     * absolutely huge selectors or for versions of PHP tuned to
     * strictly limit recursion depth.
     *
     * @param \DOMElement $node
     *   The DOMNode to check.
     * @param $selector
     * @return boolean
     *   A boolean TRUE if the node matches, false otherwise.
     */
    public function matchesSelector(\DOMElement $node, $selector)
    {
        return $this->matchesSimpleSelector($node, $selector, 0);
    }

    /**
     * Performs a match check on a SimpleSelector.
     *
     * Where matchesSelector() does a check on an entire selector,
     * this checks only a simple selector (plus an optional
     * combinator).
     *
     * @param \DOMElement $node
     * @param $selectors
     * @param $index
     * @return boolean
     *   A boolean TRUE if the node matches, false otherwise.
     * @throws NotImplementedException
     */
    public function matchesSimpleSelector(\DOMElement $node, $selectors, $index)
    {
        $selector = $selectors[$index];
        // Note that this will short circuit as soon as one of these
        // returns FALSE.
        $result = $this->matchElement($node, $selector->element, $selector->ns)
            && $this->matchAttributes($node, $selector->attributes)
            && $this->matchId($node, $selector->id)
            && $this->matchClasses($node, $selector->classes)
            && $this->matchPseudoClasses($node, $selector->pseudoClasses)
            && $this->matchPseudoElements($node, $selector->pseudoElements);

        $isNextRule = isset($selectors[++$index]);
        // If there is another selector, we process that if there a match
        // hasn't been found.
        /*
        if ($isNextRule && $selectors[$index]->combinator == SimpleSelector::anotherSelector) {
          // We may need to re-initialize the match set for the next selector.
          if (!$this->initialized) {
            $this->initialMatch($selectors[$index]);
          }
          if (!$result) fprintf(STDOUT, "Element: %s, Next selector: %s\n", $node->tagName, $selectors[$index]);
          return $result || $this->matchesSimpleSelector($node, $selectors, $index);
        }
        // If we have a match and we have a combinator, we need to
        // recurse up the tree.
        else*/
        if ($isNextRule && $result) {
            $result = $this->combine($node, $selectors, $index);
        }

        return $result;
    }

    /**
     * Combine the next selector with the given match
     * using the next combinator.
     *
     * If the next selector is combined with another
     * selector, that will be evaluated too, and so on.
     * So if this function returns TRUE, it means that all
     * child selectors are also matches.
     *
     * @param DOMNode $node
     *   The DOMNode to test.
     * @param array $selectors
     *   The array of simple selectors.
     * @param int $index
     *   The index of the current selector.
     * @return boolean
     *   TRUE if the next selector(s) match.
     */
    public function combine(\DOMElement $node, $selectors, $index)
    {
        $selector = $selectors[$index];
        //$this->debug(implode(' ', $selectors));
        switch ($selector->combinator) {
            case SimpleSelector::ADJACENT:
                return $this->combineAdjacent($node, $selectors, $index);
            case SimpleSelector::SIBLING:
                return $this->combineSibling($node, $selectors, $index);
            case SimpleSelector::DIRECT_DESCENDANT:
                return $this->combineDirectDescendant($node, $selectors, $index);
            case SimpleSelector::ANY_DESCENDANT:
                return $this->combineAnyDescendant($node, $selectors, $index);
            case SimpleSelector::ANOTHER_SELECTOR:
                // fprintf(STDOUT, "Next selector: %s\n", $selectors[$index]);
                return $this->matchesSimpleSelector($node, $selectors, $index);;
        }

        return false;
    }

    /**
     * Process an Adjacent Sibling.
     *
     * The spec does not indicate whether Adjacent should ignore non-Element
     * nodes, so we choose to ignore them.
     *
     * @param DOMNode $node
     *   A DOM Node.
     * @param array $selectors
     *   The selectors array.
     * @param int $index
     *   The current index to the operative simple selector in the selectors
     *   array.
     * @return boolean
     *   TRUE if the combination matches, FALSE otherwise.
     */
    public function combineAdjacent($node, $selectors, $index)
    {
        while (!empty($node->previousSibling)) {
            $node = $node->previousSibling;
            if ($node->nodeType == XML_ELEMENT_NODE) {
                //$this->debug(sprintf('Testing %s against "%s"', $node->tagName, $selectors[$index]));
                return $this->matchesSimpleSelector($node, $selectors, $index);
            }
        }

        return false;
    }

    /**
     * Check all siblings.
     *
     * According to the spec, this only tests elements LEFT of the provided
     * node.
     *
     * @param DOMNode $node
     *   A DOM Node.
     * @param array $selectors
     *   The selectors array.
     * @param int $index
     *   The current index to the operative simple selector in the selectors
     *   array.
     * @return boolean
     *   TRUE if the combination matches, FALSE otherwise.
     */
    public function combineSibling($node, $selectors, $index)
    {
        while (!empty($node->previousSibling)) {
            $node = $node->previousSibling;
            if ($node->nodeType == XML_ELEMENT_NODE && $this->matchesSimpleSelector($node, $selectors, $index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle a Direct Descendant combination.
     *
     * Check whether the given node is a rightly-related descendant
     * of its parent node.
     *
     * @param DOMNode $node
     *   A DOM Node.
     * @param array $selectors
     *   The selectors array.
     * @param int $index
     *   The current index to the operative simple selector in the selectors
     *   array.
     * @return boolean
     *   TRUE if the combination matches, FALSE otherwise.
     */
    public function combineDirectDescendant($node, $selectors, $index)
    {
        $parent = $node->parentNode;
        if (empty($parent)) {
            return false;
        }

        return $this->matchesSimpleSelector($parent, $selectors, $index);
    }

    /**
     * Handle Any Descendant combinations.
     *
     * This checks to see if there are any matching routes from the
     * selector beginning at the present node.
     *
     * @param DOMNode $node
     *   A DOM Node.
     * @param array $selectors
     *   The selectors array.
     * @param int $index
     *   The current index to the operative simple selector in the selectors
     *   array.
     * @return boolean
     *   TRUE if the combination matches, FALSE otherwise.
     */
    public function combineAnyDescendant($node, $selectors, $index)
    {
        while (!empty($node->parentNode)) {
            $node = $node->parentNode;

            // Catch case where element is child of something
            // else. This should really only happen with a
            // document element.
            if ($node->nodeType != XML_ELEMENT_NODE) {
                continue;
            }

            if ($this->matchesSimpleSelector($node, $selectors, $index)) {
                return true;
            }
        }
    }

    /**
     * Get the intial match set.
     *
     * This should only be executed when not working with
     * an existing match set.
     * @param \QueryPath\CSS\SimpleSelector $selector
     * @param SplObjectStorage $matches
     * @return SplObjectStorage
     */
    protected function initialMatch(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
    {
        $element = $selector->element;

        // If no element is specified, we have to start with the
        // entire document.
        if ($element === NULL) {
            $element = '*';
        }

        // We try to do some optimization here to reduce the
        // number of matches to the bare minimum. This will
        // reduce the subsequent number of operations that
        // must be performed in the query.

        // Experimental: ID queries use XPath to match, since
        // this should give us only a single matched element
        // to work with.
        if (/*$element == '*' &&*/
        !empty($selector->id)) {
            $initialMatches = $this->initialMatchOnID($selector, $matches);
        } // If a namespace is set, find the namespace matches.
        elseif (!empty($selector->ns)) {
            $initialMatches = $this->initialMatchOnElementNS($selector, $matches);
        }
        // If the element is a wildcard, using class can
        // substantially reduce the number of elements that
        // we start with.
        elseif ($element === '*' && !empty($selector->classes)) {
            $initialMatches = $this->initialMatchOnClasses($selector, $matches);
        } else {
            $initialMatches = $this->initialMatchOnElement($selector, $matches);
        }

        return $initialMatches;
    }

    /**
     * Shortcut for finding initial match by ID.
     *
     * If the element is set to '*' and an ID is
     * set, then this should be used to find by ID,
     * which will drastically reduce the amount of
     * comparison operations done in PHP.
     * @param \QueryPath\CSS\SimpleSelector $selector
     * @param SplObjectStorage $matches
     * @return SplObjectStorage
     */
    protected function initialMatchOnID(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
    {
        $id    = $selector->id;
        $found = $this->newMatches();

        // Issue #145: DOMXPath will through an exception if the DOM is
        // not set.
        if (!($this->dom instanceof \DOMDocument)) {
            return $found;
        }
        $baseQuery = ".//*[@id='{$id}']";
        $xpath     = new \DOMXPath($this->dom);

        // Now we try to find any matching IDs.
        /** @var \DOMElement $node */
        foreach ($matches as $node) {
            if ($node->getAttribute('id') === $id) {
                $found->attach($node);
            }

            $nl = $this->initialXpathQuery($xpath, $node, $baseQuery);
            if (!empty($nl) && $nl instanceof \DOMNodeList) {
                $this->attachNodeList($nl, $found);
            }
        }
        // Unset the ID selector.
        $selector->id = NULL;

        return $found;
    }

    /**
     * Shortcut for setting the intial match.
     *
     * This shortcut should only be used when the initial
     * element is '*' and there are classes set.
     *
     * In any other case, the element finding algo is
     * faster and should be used instead.
     * @param \QueryPath\CSS\SimpleSelector $selector
     * @param $matches
     * @return \SplObjectStorage
     */
    protected function initialMatchOnClasses(SimpleSelector $selector, SplObjectStorage $matches) : \SplObjectStorage
    {
        $found = $this->newMatches();

        // Issue #145: DOMXPath will through an exception if the DOM is
        // not set.
        if (!($this->dom instanceof \DOMDocument)) {
            return $found;
        }
        $baseQuery = './/*[@class]';
        $xpath     = new \DOMXPath($this->dom);

        // Now we try to find any matching IDs.
        /** @var \DOMElement $node */
        foreach ($matches as $node) {
            // Refactor me!
            if ($node->hasAttribute('class')) {
                $intersect = array_intersect($selector->classes, explode(' ', $node->getAttribute('class')));
                if (count($intersect) === count($selector->classes)) {
                    $found->attach($node);
                }
            }

            $nl = $this->initialXpathQuery($xpath, $node, $baseQuery);
            /** @var \DOMElement $subNode */
            foreach ($nl as $subNode) {
                $classes    = $subNode->getAttribute('class');
                $classArray = explode(' ', $classes);

                $intersect = array_intersect($selector->classes, $classArray);
                if (count($intersect) === count($selector->classes)) {
                    $found->attach($subNode);
                }
            }
        }

        // Unset the classes selector.
        $selector->classes = [];

        return $found;
    }

    /**
     * Internal xpath query.
     *
     * This is optimized for very specific use, and is not a general
     * purpose function.
     * @param \DOMXPath $xpath
     * @param \DOMElement $node
     * @param string $query
     * @return \DOMNodeList
     */
    private function initialXpathQuery(\DOMXPath $xpath, \DOMElement $node, string $query) : \DOMNodeList
    {
        // This works around a bug in which the document element
        // does not correctly search with the $baseQuery.
        if ($node->isSameNode($this->dom->documentElement)) {
            $query = mb_substr($query, 1);
        }

        return $xpath->query($query, $node);
    }

    /**
     * Shortcut for setting the initial match.
     *
     * @param $selector
     * @param $matches
     * @return \SplObjectStorage
     */
    protected function initialMatchOnElement(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
    {
        $element = $selector->element;
        if (NULL === $element) {
            $element = '*';
        }
        $found = $this->newMatches();
        /** @var \DOMDocument $node */
        foreach ($matches as $node) {
            // Capture the case where the initial element is the root element.
            if ($node->tagName === $element
                || ($element === '*' && $node->parentNode instanceof \DOMDocument)) {
                $found->attach($node);
            }
            $nl = $node->getElementsByTagName($element);
            if (!empty($nl) && $nl instanceof \DOMNodeList) {
                $this->attachNodeList($nl, $found);
            }
        }

        $selector->element = NULL;

        return $found;
    }

    /**
     * Get elements and filter by namespace.
     * @param \QueryPath\CSS\SimpleSelector $selector
     * @param SplObjectStorage $matches
     * @return SplObjectStorage
     */
    protected function initialMatchOnElementNS(SimpleSelector $selector, SplObjectStorage $matches) : SplObjectStorage
    {
        $ns = $selector->ns;

        $elements = $this->initialMatchOnElement($selector, $matches);

        // "any namespace" matches anything.
        if ($ns === '*') {
            return $elements;
        }

        // Loop through and make a list of items that need to be filtered
        // out, then filter them. This is required b/c ObjectStorage iterates
        // wrongly when an item is detached in an access loop.
        $detach = [];
        foreach ($elements as $node) {
            // This lookup must be done PER NODE.
            $nsuri = $node->lookupNamespaceURI($ns);
            if (empty($nsuri) || $node->namespaceURI !== $nsuri) {
                $detach[] = $node;
            }
        }
        foreach ($detach as $rem) {
            $elements->detach($rem);
        }
        $selector->ns = NULL;

        return $elements;
    }

    /**
     * Checks to see if the DOMNode matches the given element selector.
     *
     * This handles the following cases:
     *
     * - element (foo)
     * - namespaced element (ns|foo)
     * - namespaced wildcard (ns|*)
     * - wildcard (* or *|*)
     * @param \DOMElement $node
     * @param $element
     * @param null $ns
     * @return bool
     */
    protected function matchElement(\DOMElement $node, $element, $ns = NULL) : bool
    {
        if (empty($element)) {
            return true;
        }

        // Handle namespace.
        if (!empty($ns) && $ns !== '*') {
            // Check whether we have a matching NS URI.
            $nsuri = $node->lookupNamespaceURI($ns);
            if (empty($nsuri) || $node->namespaceURI !== $nsuri) {
                return false;
            }
        }

        // Compare local name to given element name.
        return $element === '*' || $node->localName === $element;
    }

    /**
     * Checks to see if the given DOMNode matches an "any element" (*).
     *
     * This does not handle namespaced whildcards.
     */
    /*
    protected function matchAnyElement($node) {
      $ancestors = $this->ancestors($node);

      return count($ancestors) > 0;
    }
     */

    /**
     * Get a list of ancestors to the present node.
     */
    protected function ancestors($node)
    {
        $buffer = [];
        $parent = $node;
        while (($parent = $parent->parentNode) !== NULL) {
            $buffer[] = $parent;
        }

        return $buffer;
    }

    /**
     * Check to see if DOMNode has all of the given attributes.
     *
     * This can handle namespaced attributes, including namespace
     * wildcards.
     * @param \DOMElement $node
     * @param $attributes
     * @return bool
     */
    protected function matchAttributes(\DOMElement $node, $attributes) : bool
    {
        if (empty($attributes)) {
            return true;
        }

        foreach ($attributes as $attr) {
            $val = isset($attr['value']) ? $attr['value'] : NULL;

            // Namespaced attributes.
            if (isset($attr['ns']) && $attr['ns'] !== '*') {
                $nsuri = $node->lookupNamespaceURI($attr['ns']);
                if (empty($nsuri) || !$node->hasAttributeNS($nsuri, $attr['name'])) {
                    return false;
                }
                $matches = Util::matchesAttributeNS($node, $attr['name'], $nsuri, $val, $attr['op']);
            } elseif (isset($attr['ns']) && $attr['ns'] === '*' && $node->hasAttributes()) {
                // Cycle through all of the attributes in the node. Note that
                // these are DOMAttr objects.
                $matches = false;
                $name    = $attr['name'];
                foreach ($node->attributes as $attrNode) {
                    if ($attrNode->localName === $name) {
                        $nsuri   = $attrNode->namespaceURI;
                        $matches = Util::matchesAttributeNS($node, $name, $nsuri, $val, $attr['op']);
                    }
                }
            } // No namespace.
            else {
                $matches = Util::matchesAttribute($node, $attr['name'], $val, $attr['op']);
            }

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check that the given DOMNode has the given ID.
     * @param \DOMElement $node
     * @param $id
     * @return bool
     */
    protected function matchId(\DOMElement $node, $id) : bool
    {
        if (empty($id)) {
            return true;
        }

        return $node->hasAttribute('id') && $node->getAttribute('id') === $id;
    }

    /**
     * Check that the given DOMNode has all of the given classes.
     * @param \DOMElement $node
     * @param $classes
     * @return bool
     */
    protected function matchClasses(\DOMElement $node, $classes) : bool
    {
        if (empty($classes)) {
            return true;
        }

        if (!$node->hasAttribute('class')) {
            return false;
        }

        $eleClasses = preg_split('/\s+/', $node->getAttribute('class'));
        if (empty($eleClasses)) {
            return false;
        }

        // The intersection should match the given $classes.
        $missing = array_diff($classes, array_intersect($classes, $eleClasses));

        return count($missing) === 0;
    }

    /**
     * @param \DOMElement $node
     * @param $pseudoClasses
     * @return bool
     * @throws NotImplementedException
     * @throws ParseException
     */
    protected function matchPseudoClasses(\DOMElement $node, $pseudoClasses): bool
    {
        $ret = true;
        foreach ($pseudoClasses as $pseudoClass) {
            $name = $pseudoClass['name'];
            // Avoid E_STRICT violation.
            $value = $pseudoClass['value'] ?? NULL;
            $ret   &= $this->psHandler->elementMatches($name, $node, $this->scopeNode, $value);
        }

        return $ret;
    }

    /**
     * Test whether the given node matches the pseudoElements.
     *
     * If any pseudo-elements are passed, this will test to see
     * <i>if conditions obtain that would allow the pseudo-element
     * to be created</i>. This does not modify the match in any way.
     * @param \DOMElement $node
     * @param $pseudoElements
     * @return bool
     * @throws NotImplementedException
     */
    protected function matchPseudoElements(\DOMElement $node, $pseudoElements) : bool
    {
        if (empty($pseudoElements)) {
            return true;
        }

        foreach ($pseudoElements as $pse) {
            switch ($pse) {
                case 'first-line':
                case 'first-letter':
                case 'before':
                case 'after':
                    return strlen($node->textContent) > 0;
                case 'selection':
                    throw new \QueryPath\CSS\NotImplementedException("::$pse is not implemented.");
            }
        }

        return false;
    }

    protected function newMatches()
    {
        return new \SplObjectStorage();
    }

    /**
     * Get the internal match set.
     * Internal utility function.
     */
    protected function getMatches()
    {
        return $this->matches();
    }

    /**
     * Set the internal match set.
     *
     * Internal utility function.
     */
    protected function setMatches($matches)
    {
        $this->matches = $matches;
    }

    /**
     * Attach all nodes in a node list to the given \SplObjectStorage.
     * @param \DOMNodeList $nodeList
     * @param \SplObjectStorage $splos
     */
    public function attachNodeList(\DOMNodeList $nodeList, \SplObjectStorage $splos)
    {
        foreach ($nodeList as $item) {
            $splos->attach($item);
        }
    }

    public function getDocument()
    {
        return $this->dom;
    }

}
