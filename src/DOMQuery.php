<?php
/**
 * @file
 * This houses the class formerly called QueryPath.
 *
 * As of QueryPath 3.0.0, the class was renamed QueryPath::DOMQuery. This
 * was done for a few reasons:
 * - The library has been refactored, and it made more sense to call the top
 *   level class QueryPath. This is not the top level class.
 * - There have been requests for a JSONQuery class, which would be the
 *   natural complement of DOMQuery.
 */

namespace QueryPath;

use QueryPath\CSS\DOMTraverser;
use \QueryPath\CSS\QueryPathEventHandler;
use \Masterminds\HTML5;
use QueryPath\Entities;
use QueryPath\Exception;
use QueryPath\Helpers\QueryChecks;
use QueryPath\Helpers\QueryFilters;
use QueryPath\Helpers\QueryMutators;

/**
 * The DOMQuery object is the primary tool in this library.
 *
 * To create a new DOMQuery, use QueryPath::with() or qp() function.
 *
 * If you are new to these documents, start at the QueryPath.php page.
 * There you will find a quick guide to the tools contained in this project.
 *
 * A note on serialization: Query uses DOM classes internally, and those
 * do not serialize well at all. In addition, DOMQuery may contain many
 * extensions, and there is no guarantee that extensions can serialize. The
 * moral of the story: Don't serialize DOMQuery.
 *
 * @see     qp()
 * @see     QueryPath.php
 * @ingroup querypath_core
 */
class DOMQuery extends DOM
{

    use QueryFilters, QueryMutators, QueryChecks;

    /**
     * The last array of matches.
     */
    protected $last = []; // Last set of matches.
    private $ext = []; // Extensions array.

    /**
     * The number of current matches.
     *
     * @see count()
     */
    public $length = 0;

    /**
     * Get the effective options for the current DOMQuery object.
     *
     * This returns an associative array of all of the options as set
     * for the current DOMQuery object. This includes default options,
     * options directly passed in via {@link qp()} or the constructor,
     * an options set in the QueryPath::Options object.
     *
     * The order of merging options is this:
     *  - Options passed in using qp() are highest priority, and will
     *    override other options.
     *  - Options set with QueryPath::Options will override default options,
     *    but can be overridden by options passed into qp().
     *  - Default options will be used when no overrides are present.
     *
     * This function will return the options currently used, with the above option
     * overriding having been calculated already.
     *
     * @return array
     *  An associative array of options, calculated from defaults and overridden
     *  options.
     * @see   qp()
     * @see   QueryPath::Options::set()
     * @see   QueryPath::Options::merge()
     * @since 2.0
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Select the root element of the document.
     *
     * This sets the current match to the document's root element. For
     * practical purposes, this is the same as:
     *
     * @code
     * qp($someDoc)->find(':root');
     * @endcode
     * However, since it doesn't invoke a parser, it has less overhead. It also
     * works in cases where the QueryPath has been reduced to zero elements (a
     * case that is not handled by find(':root') because there is no element
     * whose root can be found).
     *
     * @param string $selector
     *  A selector. If this is supplied, QueryPath will navigate to the
     *  document root and then run the query. (Added in QueryPath 2.0 Beta 2)
     * @return \QueryPath\DOMQuery
     *  The DOMQuery object, wrapping the root element (document element)
     *  for the current document.
     * @throws CSS\ParseException
     */
    public function top($selector = NULL): Query
    {
        return $this->inst($this->document->documentElement, $selector);
    }

    /**
     * Given a CSS Selector, find matching items.
     *
     * @param string $selector
     *   CSS 3 Selector
     * @return \QueryPath\DOMQuery
     * @see  filter()
     * @see  is()
     * @todo If a find() returns zero matches, then a subsequent find() will
     *   also return zero matches, even if that find has a selector like :root.
     *   The reason for this is that the {@link QueryPathEventHandler} does
     *   not set the root of the document tree if it cannot find any elements
     *   from which to determine what the root is. The workaround is to use
     *   {@link top()} to select the root element again.
     * @throws CSS\ParseException
     */
    public function find($selector): Query
    {
        $query = new DOMTraverser($this->matches);
        $query->find($selector);
        return $this->inst($query->matches(), NULL);
    }

    /**
     * @param $selector
     * @return $this
     * @throws CSS\ParseException
     */
    public function findInPlace($selector)
    {
        $query = new DOMTraverser($this->matches);
        $query->find($selector);
        $this->setMatches($query->matches());

        return $this;
    }

    /**
     * Execute an XPath query and store the results in the QueryPath.
     *
     * Most methods in this class support CSS 3 Selectors. Sometimes, though,
     * XPath provides a finer-grained query language. Use this to execute
     * XPath queries.
     *
     * Beware, though. DOMQuery works best on DOM Elements, but an XPath
     * query can return other nodes, strings, and values. These may not work with
     * other QueryPath functions (though you will be able to access the
     * values with {@link get()}).
     *
     * @param string $query
     *      An XPath query.
     * @param array $options
     *      Currently supported options are:
     *      - 'namespace_prefix': And XML namespace prefix to be used as the default. Used
     *      in conjunction with 'namespace_uri'
     *      - 'namespace_uri': The URI to be used as the default namespace URI. Used
     *      with 'namespace_prefix'
     * @return \QueryPath\DOMQuery
     *      A DOMQuery object wrapping the results of the query.
     * @see    find()
     * @author M Butcher
     * @author Xavier Prud'homme
     * @throws CSS\ParseException
     */
    public function xpath($query, $options = [])
    {
        $xpath = new \DOMXPath($this->document);

        // Register a default namespace.
        if (!empty($options['namespace_prefix']) && !empty($options['namespace_uri'])) {
            $xpath->registerNamespace($options['namespace_prefix'], $options['namespace_uri']);
        }

        $found = new \SplObjectStorage();
        foreach ($this->matches as $item) {
            $nl = $xpath->query($query, $item);
            if ($nl->length > 0) {
                for ($i = 0; $i < $nl->length; ++$i) {
                    $found->attach($nl->item($i));
                }
            }
        }

        return $this->inst($found, NULL);
    }

    /**
     * Get the number of elements currently wrapped by this object.
     *
     * Note that there is no length property on this object.
     *
     * @return int
     *  Number of items in the object.
     * @deprecated QueryPath now implements Countable, so use count().
     */
    public function size()
    {
        return $this->matches->count();
    }

    /**
     * Get the number of elements currently wrapped by this object.
     *
     * Since DOMQuery is Countable, the PHP count() function can also
     * be used on a DOMQuery.
     *
     * @code
     * <?php
     *  count(qp($xml, 'div'));
     * ?>
     * @endcode
     *
     * @return int
     *  The number of matches in the DOMQuery.
     */
    public function count(): int
    {
        return $this->matches->count();
    }

    /**
     * Get one or all elements from this object.
     *
     * When called with no paramaters, this returns all objects wrapped by
     * the DOMQuery. Typically, these are DOMElement objects (unless you have
     * used map(), xpath(), or other methods that can select
     * non-elements).
     *
     * When called with an index, it will return the item in the DOMQuery with
     * that index number.
     *
     * Calling this method does not change the DOMQuery (e.g. it is
     * non-destructive).
     *
     * You can use qp()->get() to iterate over all elements matched. You can
     * also iterate over qp() itself (DOMQuery implementations must be Traversable).
     * In the later case, though, each item
     * will be wrapped in a DOMQuery object. To learn more about iterating
     * in QueryPath, see {@link examples/techniques.php}.
     *
     * @param int $index
     *   If specified, then only this index value will be returned. If this
     *   index is out of bounds, a NULL will be returned.
     * @param boolean $asObject
     *   If this is TRUE, an SplObjectStorage object will be returned
     *   instead of an array. This is the preferred method for extensions to use.
     * @return mixed
     *   If an index is passed, one element will be returned. If no index is
     *   present, an array of all matches will be returned.
     * @see eq()
     * @see SplObjectStorage
     */
    public function get($index = NULL, $asObject = false)
    {
        if ($index !== NULL) {
            return ($this->count() > $index) ? $this->getNthMatch($index) : NULL;
        }
        // Retain support for legacy.
        if (!$asObject) {
            $matches = [];
            foreach ($this->matches as $m) {
                $matches[] = $m;
            }

            return $matches;
        }

        return $this->matches;
    }

    /**
     * Get the namespace of the current element.
     *
     * If QP is currently pointed to a list of elements, this will get the
     * namespace of the first element.
     */
    public function ns()
    {
        return $this->get(0)->namespaceURI;
    }

    /**
     * Get the DOMDocument that we currently work with.
     *
     * This returns the current DOMDocument. Any changes made to this document will be
     * accessible to DOMQuery, as both will share access to the same object.
     *
     * @return DOMDocument
     */
    public function document()
    {
        return $this->document;
    }

    /**
     * On an XML document, load all XIncludes.
     *
     * @return \QueryPath\DOMQuery
     */
    public function xinclude()
    {
        $this->document->xinclude();

        return $this;
    }

    /**
     * Get all current elements wrapped in an array.
     * Compatibility function for jQuery 1.4, but identical to calling {@link get()}
     * with no parameters.
     *
     * @return array
     *  An array of DOMNodes (typically DOMElements).
     */
    public function toArray()
    {
        return $this->get();
    }

    /**
     * Insert or retrieve a Data URL.
     *
     * When called with just $attr, it will fetch the result, attempt to decode it, and
     * return an array with the MIME type and the application data.
     *
     * When called with both $attr and $data, it will inject the data into all selected elements
     * So @code$qp->dataURL('src', file_get_contents('my.png'), 'image/png')@endcode will inject
     * the given PNG image into the selected elements.
     *
     * The current implementation only knows how to encode and decode Base 64 data.
     *
     * Note that this is known *not* to work on IE 6, but should render fine in other browsers.
     *
     * @param string $attr
     *    The name of the attribute.
     * @param mixed $data
     *    The contents to inject as the data. The value can be any one of the following:
     *    - A URL: If this is given, then the subsystem will read the content from that URL. THIS
     *    MUST BE A FULL URL, not a relative path.
     *    - A string of data: If this is given, then the subsystem will encode the string.
     *    - A stream or file handle: If this is given, the stream's contents will be encoded
     *    and inserted as data.
     *    (Note that we make the assumption here that you would never want to set data to be
     *    a URL. If this is an incorrect assumption, file a bug.)
     * @param string $mime
     *    The MIME type of the document.
     * @param resource $context
     *    A valid context. Use this only if you need to pass a stream context. This is only necessary
     *    if $data is a URL. (See {@link stream_context_create()}).
     * @return \QueryPath\DOMQuery|string
     *    If this is called as a setter, this will return a DOMQuery object. Otherwise, it
     *    will attempt to fetch data out of the attribute and return that.
     * @see   http://en.wikipedia.org/wiki/Data:_URL
     * @see   attr()
     * @since 2.1
     */
    public function dataURL($attr, $data = NULL, $mime = 'application/octet-stream', $context = NULL)
    {
        if (is_null($data)) {
            // Attempt to fetch the data
            $data = $this->attr($attr);
            if (empty($data) || is_array($data) || strpos($data, 'data:') !== 0) {
                return;
            }

            // So 1 and 2 should be MIME types, and 3 should be the base64-encoded data.
            $regex = '/^data:([a-zA-Z0-9]+)\/([a-zA-Z0-9]+);base64,(.*)$/';
            $matches = [];
            preg_match($regex, $data, $matches);

            if (!empty($matches)) {
                $result = [
                    'mime' => $matches[1] . '/' . $matches[2],
                    'data' => base64_decode($matches[3]),
                ];

                return $result;
            }
        } else {
            $attVal = QueryPath::encodeDataURL($data, $mime, $context);

            return $this->attr($attr, $attVal);
        }
    }

    /**
     * Sort the contents of the QueryPath object.
     *
     * By default, this does not change the order of the elements in the
     * DOM. Instead, it just sorts the internal list. However, if TRUE
     * is passed in as the second parameter then QueryPath will re-order
     * the DOM, too.
     *
     * @attention
     * DOM re-ordering is done by finding the location of the original first
     * item in the list, and then placing the sorted list at that location.
     *
     * The argument $compartor is a callback, such as a function name or a
     * closure. The callback receives two DOMNode objects, which you can use
     * as DOMNodes, or wrap in QueryPath objects.
     *
     * A simple callback:
     * @code
     * <?php
     * $comp = function (\DOMNode $a, \DOMNode $b) {
     *   if ($a->textContent == $b->textContent) {
     *     return 0;
     *   }
     *   return $a->textContent > $b->textContent ? 1 : -1;
     * };
     * $qp = QueryPath::with($xml, $selector)->sort($comp);
     * ?>
     * @endcode
     *
     * The above sorts the matches into lexical order using the text of each node.
     * If you would prefer to work with QueryPath objects instead of DOMNode
     * objects, you may prefer something like this:
     *
     * @code
     * <?php
     * $comp = function (\DOMNode $a, \DOMNode $b) {
     *   $qpa = qp($a);
     *   $qpb = qp($b);
     *
     *   if ($qpa->text() == $qpb->text()) {
     *     return 0;
     *   }
     *   return $qpa->text()> $qpb->text()? 1 : -1;
     * };
     *
     * $qp = QueryPath::with($xml, $selector)->sort($comp);
     * ?>
     * @endcode
     *
     * @param callback $comparator
     *   A callback. This will be called during sorting to compare two DOMNode
     *   objects.
     * @param boolean $modifyDOM
     *   If this is TRUE, the sorted results will be inserted back into
     *   the DOM at the position of the original first element.
     * @return \QueryPath\DOMQuery
     *   This object.
     * @throws CSS\ParseException
     */
    public function sort($comparator, $modifyDOM = false): Query
    {
        // Sort as an array.
        $list = iterator_to_array($this->matches);

        if (empty($list)) {
            return $this;
        }

        $oldFirst = $list[0];

        usort($list, $comparator);

        // Copy back into SplObjectStorage.
        $found = new \SplObjectStorage();
        foreach ($list as $node) {
            $found->attach($node);
        }
        //$this->setMatches($found);


        // Do DOM modifications only if necessary.
        if ($modifyDOM) {
            $placeholder = $oldFirst->ownerDocument->createElement('_PLACEHOLDER_');
            $placeholder = $oldFirst->parentNode->insertBefore($placeholder, $oldFirst);
            $len = count($list);
            for ($i = 0; $i < $len; ++$i) {
                $node = $list[$i];
                $node = $node->parentNode->removeChild($node);
                $placeholder->parentNode->insertBefore($node, $placeholder);
            }
            $placeholder->parentNode->removeChild($placeholder);
        }

        return $this->inst($found, NULL);
    }

    /**
     * Get an item's index.
     *
     * Given a DOMElement, get the index from the matches. This is the
     * converse of {@link get()}.
     *
     * @param DOMElement $subject
     *  The item to match.
     *
     * @return mixed
     *  The index as an integer (if found), or boolean FALSE. Since 0 is a
     *  valid index, you should use strong equality (===) to test..
     * @see get()
     * @see is()
     */
    public function index($subject)
    {
        $i = 0;
        foreach ($this->matches as $m) {
            if ($m === $subject) {
                return $i;
            }
            ++$i;
        }

        return false;
    }

    /**
     * The tag name of the first element in the list.
     *
     * This returns the tag name of the first element in the list of matches. If
     * the list is empty, an empty string will be used.
     *
     * @see replaceAll()
     * @see replaceWith()
     * @return string
     *  The tag name of the first element in the list.
     */
    public function tag()
    {
        return ($this->matches->count() > 0) ? $this->getFirstMatch()->tagName : '';
    }

    /**
     * Revert to the previous set of matches.
     *
     * <b>DEPRECATED</b> Do not use.
     *
     * This will revert back to the last set of matches (before the last
     * "destructive" set of operations). This undoes any change made to the set of
     * matched objects. Functions like find() and filter() change the
     * list of matched objects. The end() function will revert back to the last set of
     * matched items.
     *
     * Note that functions that modify the document, but do not change the list of
     * matched objects, are not "destructive". Thus, calling append('something')->end()
     * will not undo the append() call.
     *
     * Only one level of changes is stored. Reverting beyond that will result in
     * an empty set of matches. Example:
     *
     * @code
     * // The line below returns the same thing as qp(document, 'p');
     * qp(document, 'p')->find('div')->end();
     * // This returns an empty array:
     * qp(document, 'p')->end();
     * // This returns an empty array:
     * qp(document, 'p')->find('div')->find('span')->end()->end();
     * @endcode
     *
     * The last one returns an empty array because only one level of changes is stored.
     *
     * @return \QueryPath\DOMQuery
     *  A DOMNode object reflecting the list of matches prior to the last destructive
     *  operation.
     * @see        andSelf()
     * @see        add()
     * @deprecated This function will be removed.
     */
    public function end()
    {
        // Note that this does not use setMatches because it must set the previous
        // set of matches to empty array.
        $this->matches = $this->last;
        $this->last = new \SplObjectStorage();

        return $this;
    }

    /**
     * Combine the current and previous set of matched objects.
     *
     * Example:
     *
     * @code
     * qp(document, 'p')->find('div')->andSelf();
     * @endcode
     *
     * The code above will contain a list of all p elements and all div elements that
     * are beneath p elements.
     *
     * @see end();
     * @return \QueryPath\DOMQuery
     *  A DOMNode object with the results of the last two "destructive" operations.
     * @see add()
     * @see end()
     */
    public function andSelf()
    {
        // This is destructive, so we need to set $last:
        $last = $this->matches;

        foreach ($this->last as $item) {
            $this->matches->attach($item);
        }

        $this->last = $last;

        return $this;
    }

    /**
     * Set or get the markup for an element.
     *
     * If $markup is set, then the giving markup will be injected into each
     * item in the set. All other children of that node will be deleted, and this
     * new code will be the only child or children. The markup MUST BE WELL FORMED.
     *
     * If no markup is given, this will return a string representing the child
     * markup of the first node.
     *
     * <b>Important:</b> This differs from jQuery's html() function. This function
     * returns <i>the current node</i> and all of its children. jQuery returns only
     * the children. This means you do not need to do things like this:
     * @code$qp->parent()->html()@endcode.
     *
     * By default, this is HTML 4.01, not XHTML. Use {@link xml()} for XHTML.
     *
     * @param string $markup
     *  The text to insert.
     * @return mixed
     *  A string if no markup was passed, or a DOMQuery if markup was passed.
     * @throws Exception
     * @throws QueryPath
     * @see xml()
     * @see text()
     * @see contents()
     */
    public function html($markup = NULL)
    {
        if (isset($markup)) {

            if ($this->options['replace_entities']) {
                $markup = Entities::replaceAllEntities($markup);
            }

            // Parse the HTML and insert it into the DOM
            //$doc = DOMDocument::loadHTML($markup);
            $doc = $this->document->createDocumentFragment();
            $doc->appendXML($markup);
            $this->removeChildren();
            $this->append($doc);

            return $this;
        }
        $length = $this->matches->count();
        if ($length === 0) {
            return NULL;
        }
        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();

        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        // Added by eabrand.
        if (!$first->ownerDocument->documentElement) {
            return NULL;
        }

        if ($first instanceof \DOMDocument || $first->isSameNode($first->ownerDocument->documentElement)) {
            return $this->document->saveHTML();
        }

        // saveHTML cannot take a node and serialize it.
        return $this->document->saveXML($first);
    }

    /**
     * Write the QueryPath document to HTML5.
     *
     * See html()
     *
     * @param null $markup
     * @return null|DOMQuery|string
     * @throws QueryPath
     * @throws \QueryPath\Exception
     */
    public function html5($markup = NULL)
    {
        $html5 = new HTML5($this->options);

        // append HTML to existing
        if ($markup === NULL) {

            // Parse the HTML and insert it into the DOM
            $doc = $html5->loadHTMLFragment($markup);
            $this->removeChildren();
            $this->append($doc);

            return $this;
        }

        $length = $this->count();
        if ($length === 0) {
            return NULL;
        }
        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();

        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        // Added by eabrand.
        if (!$first->ownerDocument->documentElement) {
            return NULL;
        }

        if ($first instanceof \DOMDocument || $first->isSameNode($first->ownerDocument->documentElement)) {
            return $html5->saveHTML($this->document); //$this->document->saveHTML();
        }

        return $html5->saveHTML($first);
    }

    /**
     * Fetch the HTML contents INSIDE of the first DOMQuery item.
     *
     * <b>This behaves the way jQuery's @codehtml()@endcode function behaves.</b>
     *
     * This gets all children of the first match in DOMQuery.
     *
     * Consider this fragment:
     *
     * @code
     * <div>
     * test <p>foo</p> test
     * </div>
     * @endcode
     *
     * We can retrieve just the contents of this code by doing something like
     * this:
     * @code
     * qp($xml, 'div')->innerHTML();
     * @endcode
     *
     * This would return the following:
     * @codetest <p>foo</p> test@endcode
     *
     * @return string
     *  Returns a string representation of the child nodes of the first
     *  matched element.
     * @see   html()
     * @see   innerXML()
     * @see   innerXHTML()
     * @since 2.0
     */
    public function innerHTML()
    {
        return $this->innerXML();
    }

    /**
     * Fetch child (inner) nodes of the first match.
     *
     * This will return the children of the present match. For an example,
     * see {@link innerHTML()}.
     *
     * @see   innerHTML()
     * @see   innerXML()
     * @return string
     *  Returns a string of XHTML that represents the children of the present
     *  node.
     * @since 2.0
     */
    public function innerXHTML()
    {
        $length = $this->matches->count();
        if ($length === 0) {
            return NULL;
        }
        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();

        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        if (!$first->hasChildNodes()) {
            return '';
        }

        $buffer = '';
        foreach ($first->childNodes as $child) {
            $buffer .= $this->document->saveXML($child, LIBXML_NOEMPTYTAG);
        }

        return $buffer;
    }

    /**
     * Fetch child (inner) nodes of the first match.
     *
     * This will return the children of the present match. For an example,
     * see {@link innerHTML()}.
     *
     * @see   innerHTML()
     * @see   innerXHTML()
     * @return string
     *  Returns a string of XHTML that represents the children of the present
     *  node.
     * @since 2.0
     */
    public function innerXML()
    {
        $length = $this->matches->count();
        if ($length === 0) {
            return NULL;
        }
        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();

        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        if (!$first->hasChildNodes()) {
            return '';
        }

        $buffer = '';
        foreach ($first->childNodes as $child) {
            $buffer .= $this->document->saveXML($child);
        }

        return $buffer;
    }

    /**
     * Get child elements as an HTML5 string.
     *
     * TODO: This is a very simple alteration of innerXML. Do we need better
     * support?
     */
    public function innerHTML5()
    {
        $length = $this->matches->count();
        if ($length === 0) {
            return NULL;
        }
        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();

        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        if (!$first->hasChildNodes()) {
            return '';
        }

        $html5 = new HTML5($this->options);
        $buffer = '';
        foreach ($first->childNodes as $child) {
            $buffer .= $html5->saveHTML($child);
        }

        return $buffer;
    }

    /**
     * Retrieve the text of each match and concatenate them with the given separator.
     *
     * This has the effect of looping through all children, retrieving their text
     * content, and then concatenating the text with a separator.
     *
     * @param string $sep
     *  The string used to separate text items. The default is a comma followed by a
     *  space.
     * @param boolean $filterEmpties
     *  If this is true, empty items will be ignored.
     * @return string
     *  The text contents, concatenated together with the given separator between
     *  every pair of items.
     * @see   implode()
     * @see   text()
     * @since 2.0
     */
    public function textImplode($sep = ', ', $filterEmpties = true): string
    {
        $tmp = [];
        foreach ($this->matches as $m) {
            $txt = $m->textContent;
            $trimmed = trim($txt);
            // If filter empties out, then we only add items that have content.
            if ($filterEmpties) {
                if (strlen($trimmed) > 0) {
                    $tmp[] = $txt;
                }
            } // Else add all content, even if it's empty.
            else {
                $tmp[] = $txt;
            }
        }

        return implode($sep, $tmp);
    }

    /**
     * Get the text contents from just child elements.
     *
     * This is a specialized variant of textImplode() that implodes text for just the
     * child elements of the current element.
     *
     * @param string $separator
     *  The separator that will be inserted between found text content.
     * @return string
     *  The concatenated values of all children.
     * @throws CSS\ParseException
     */
    public function childrenText($separator = ' '): string
    {
        // Branch makes it non-destructive.
        return $this->branch()->xpath('descendant::text()')->textImplode($separator);
    }

    /**
     * Get or set the text contents of a node.
     *
     * @param string $text
     *  If this is not NULL, this value will be set as the text of the node. It
     *  will replace any existing content.
     * @return mixed
     *  A DOMQuery if $text is set, or the text content if no text
     *  is passed in as a pram.
     * @see html()
     * @see xml()
     * @see contents()
     */
    public function text($text = NULL)
    {
        if (isset($text)) {
            $this->removeChildren();
            foreach ($this->matches as $m) {
                $m->appendChild($this->document->createTextNode($text));
            }

            return $this;
        }
        // Returns all text as one string:
        $buf = '';
        foreach ($this->matches as $m) {
            $buf .= $m->textContent;
        }

        return $buf;
    }

    /**
     * Get or set the text before each selected item.
     *
     * If $text is passed in, the text is inserted before each currently selected item.
     *
     * If no text is given, this will return the concatenated text after each selected element.
     *
     * @code
     * <?php
     * $xml = '<?xml version="1.0"?><root>Foo<a>Bar</a><b/></root>';
     *
     * // This will return 'Foo'
     * qp($xml, 'a')->textBefore();
     *
     * // This will insert 'Baz' right before <b/>.
     * qp($xml, 'b')->textBefore('Baz');
     * ?>
     * @endcode
     *
     * @param string $text
     *  If this is set, it will be inserted before each node in the current set of
     *  selected items.
     * @return mixed
     *  Returns the DOMQuery object if $text was set, and returns a string (possibly empty)
     *  if no param is passed.
     * @throws Exception
     * @throws QueryPath
     */
    public function textBefore($text = NULL)
    {
        if (isset($text)) {
            $textNode = $this->document->createTextNode($text);

            return $this->before($textNode);
        }
        $buffer = '';
        foreach ($this->matches as $m) {
            $p = $m;
            while (isset($p->previousSibling) && $p->previousSibling->nodeType === XML_TEXT_NODE) {
                $p = $p->previousSibling;
                $buffer .= $p->textContent;
            }
        }

        return $buffer;
    }

    public function textAfter($text = NULL)
    {
        if (isset($text)) {
            $textNode = $this->document->createTextNode($text);

            return $this->after($textNode);
        }
        $buffer = '';
        foreach ($this->matches as $m) {
            $n = $m;
            while (isset($n->nextSibling) && $n->nextSibling->nodeType === XML_TEXT_NODE) {
                $n = $n->nextSibling;
                $buffer .= $n->textContent;
            }
        }

        return $buffer;
    }

    /**
     * Set or get the value of an element's 'value' attribute.
     *
     * The 'value' attribute is common in HTML form elements. This is a
     * convenience function for accessing the values. Since this is not  common
     * task on the server side, this method may be removed in future releases. (It
     * is currently provided for jQuery compatibility.)
     *
     * If a value is provided in the params, then the value will be set for all
     * matches. If no params are given, then the value of the first matched element
     * will be returned. This may be NULL.
     *
     * @deprecated Just use attr(). There's no reason to use this on the server.
     * @see        attr()
     * @param string $value
     * @return mixed
     *  Returns a DOMQuery if a string was passed in, and a string if no string
     *  was passed in. In the later case, an error will produce NULL.
     */
    public function val($value = NULL)
    {
        if (isset($value)) {
            $this->attr('value', $value);

            return $this;
        }

        return $this->attr('value');
    }

    /**
     * Set or get XHTML markup for an element or elements.
     *
     * This differs from {@link html()} in that it processes (and produces)
     * strictly XML 1.0 compliant markup.
     *
     * Like {@link xml()} and {@link html()}, this functions as both a
     * setter and a getter.
     *
     * This is a convenience function for fetching HTML in XML format.
     * It does no processing of the markup (such as schema validation).
     *
     * @param string $markup
     *  A string containing XML data.
     * @return mixed
     *  If markup is passed in, a DOMQuery is returned. If no markup is passed
     *  in, XML representing the first matched element is returned.
     * @see html()
     * @see innerXHTML()
     */
    public function xhtml($markup = NULL)
    {

        // XXX: This is a minor reworking of the original xml() method.
        // This should be refactored, probably.
        // See http://github.com/technosophos/querypath/issues#issue/10

        $omit_xml_decl = $this->options['omit_xml_declaration'];
        if ($markup === true) {
            // Basically, we handle the special case where we don't
            // want the XML declaration to be displayed.
            $omit_xml_decl = true;
        } elseif (isset($markup)) {
            return $this->xml($markup);
        }

        $length = $this->matches->count();
        if ($length === 0) {
            return NULL;
        }

        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();
        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        if ($first instanceof \DOMDocument || $first->isSameNode($first->ownerDocument->documentElement)) {

            // Has the unfortunate side-effect of stripping doctype.
            //$text = ($omit_xml_decl ? $this->document->saveXML($first->ownerDocument->documentElement, LIBXML_NOEMPTYTAG) : $this->document->saveXML(NULL, LIBXML_NOEMPTYTAG));
            $text = $this->document->saveXML(NULL, LIBXML_NOEMPTYTAG);
        } else {
            $text = $this->document->saveXML($first, LIBXML_NOEMPTYTAG);
        }

        // Issue #47: Using the old trick for removing the XML tag also removed the
        // doctype. So we remove it with a regex:
        if ($omit_xml_decl) {
            $text = preg_replace('/<\?xml\s[^>]*\?>/', '', $text);
        }

        // This is slightly lenient: It allows for cases where code incorrectly places content
        // inside of these supposedly unary elements.
        $unary = '/<(area|base|basefont|br|col|frame|hr|img|input|isindex|link|meta|param)(?(?=\s)([^>\/]+))><\/[^>]*>/i';
        $text = preg_replace($unary, '<\\1\\2 />', $text);

        // Experimental: Support for enclosing CDATA sections with comments to be both XML compat
        // and HTML 4/5 compat
        $cdata = '/(<!\[CDATA\[|\]\]>)/i';
        $replace = $this->options['escape_xhtml_js_css_sections'];
        $text = preg_replace($cdata, $replace, $text);

        return $text;
    }

    /**
     * Set or get the XML markup for an element or elements.
     *
     * Like {@link html()}, this functions in both a setter and a getter mode.
     *
     * In setter mode, the string passed in will be parsed and then appended to the
     * elements wrapped by this DOMNode object.When in setter mode, this parses
     * the XML using the DOMFragment parser. For that reason, an XML declaration
     * is not necessary.
     *
     * In getter mode, the first element wrapped by this DOMNode object will be
     * converted to an XML string and returned.
     *
     * @param string $markup
     *  A string containing XML data.
     * @return mixed
     *  If markup is passed in, a DOMQuery is returned. If no markup is passed
     *  in, XML representing the first matched element is returned.
     * @see xhtml()
     * @see html()
     * @see text()
     * @see content()
     * @see innerXML()
     */
    public function xml($markup = NULL)
    {
        $omit_xml_decl = $this->options['omit_xml_declaration'];
        if ($markup === true) {
            // Basically, we handle the special case where we don't
            // want the XML declaration to be displayed.
            $omit_xml_decl = true;
        } elseif (isset($markup)) {
            if ($this->options['replace_entities']) {
                $markup = Entities::replaceAllEntities($markup);
            }
            $doc = $this->document->createDocumentFragment();
            $doc->appendXML($markup);
            $this->removeChildren();
            $this->append($doc);

            return $this;
        }
        $length = $this->matches->count();
        if ($length === 0) {
            return NULL;
        }
        // Only return the first item -- that's what JQ does.
        $first = $this->getFirstMatch();

        // Catch cases where first item is not a legit DOM object.
        if (!($first instanceof \DOMNode)) {
            return NULL;
        }

        if ($first instanceof \DOMDocument || $first->isSameNode($first->ownerDocument->documentElement)) {

            return ($omit_xml_decl ? $this->document->saveXML($first->ownerDocument->documentElement) : $this->document->saveXML());
        }

        return $this->document->saveXML($first);
    }

    /**
     * Send the XML document to the client.
     *
     * Write the document to a file path, if given, or
     * to stdout (usually the client).
     *
     * This prints the entire document.
     *
     * @param string $path
     *  The path to the file into which the XML should be written. if
     *  this is NULL, data will be written to STDOUT, which is usually
     *  sent to the remote browser.
     * @param int $options
     *  (As of QueryPath 2.1) Pass libxml options to the saving mechanism.
     * @return \QueryPath\DOMQuery
     *  The DOMQuery object, unmodified.
     * @see xml()
     * @see innerXML()
     * @see writeXHTML()
     * @throws Exception
     *  In the event that a file cannot be written, an Exception will be thrown.
     */
    public function writeXML($path = NULL, $options = NULL)
    {
        if ($path === NULL) {
            print $this->document->saveXML(NULL, $options);
        } else {
            try {
                set_error_handler([IOException::class, 'initializeFromError']);
                $this->document->save($path, $options);
            } catch (Exception $e) {
                restore_error_handler();
                throw $e;
            }
            restore_error_handler();
        }

        return $this;
    }

    /**
     * Writes HTML to output.
     *
     * HTML is formatted as HTML 4.01, without strict XML unary tags. This is for
     * legacy HTML content. Modern XHTML should be written using {@link toXHTML()}.
     *
     * Write the document to stdout (usually the client) or to a file.
     *
     * @param string $path
     *  The path to the file into which the XML should be written. if
     *  this is NULL, data will be written to STDOUT, which is usually
     *  sent to the remote browser.
     * @return \QueryPath\DOMQuery
     *  The DOMQuery object, unmodified.
     * @see html()
     * @see innerHTML()
     * @throws Exception
     *  In the event that a file cannot be written, an Exception will be thrown.
     */
    public function writeHTML($path = NULL)
    {
        if ($path === NULL) {
            print $this->document->saveHTML();
        } else {
            try {
                set_error_handler(['\QueryPath\ParseException', 'initializeFromError']);
                $this->document->saveHTMLFile($path);
            } catch (Exception $e) {
                restore_error_handler();
                throw $e;
            }
            restore_error_handler();
        }

        return $this;
    }

    /**
     * Write the document to HTML5.
     *
     * This works the same as the other write* functions, but it encodes the output
     * as HTML5 with UTF-8.
     *
     * @see html5()
     * @see innerHTML5()
     * @throws Exception
     *  In the event that a file cannot be written, an Exception will be thrown.
     */
    public function writeHTML5($path = NULL)
    {
        $html5 = new HTML5();
        if ($path === NULL) {
            // Print the document to stdout.
            print $html5->saveHTML($this->document);

            return;
        }

        $html5->save($this->document, $path);
    }

    /**
     * Write an XHTML file to output.
     *
     * Typically, you should use this instead of {@link writeHTML()}.
     *
     * Currently, this functions identically to {@link toXML()} <i>except that</i>
     * it always uses closing tags (e.g. always @code<script></script>@endcode,
     * never @code<script/>@endcode). It will
     * write the file as well-formed XML. No XHTML schema validation is done.
     *
     * @see   writeXML()
     * @see   xml()
     * @see   writeHTML()
     * @see   innerXHTML()
     * @see   xhtml()
     * @param string $path
     *  The filename of the file to write to.
     * @return \QueryPath\DOMQuery
     *  Returns the DOMQuery, unmodified.
     * @throws Exception
     *  In the event that the output file cannot be written, an exception is
     *  thrown.
     * @since 2.0
     */
    public function writeXHTML($path = NULL)
    {
        return $this->writeXML($path, LIBXML_NOEMPTYTAG);
    }

    /**
     * Branch the base DOMQuery into another one with the same matches.
     *
     * This function makes a copy of the DOMQuery object, but keeps the new copy
     * (initially) pointed at the same matches. This object can then be queried without
     * changing the original DOMQuery. However, changes to the elements inside of this
     * DOMQuery will show up in the DOMQuery from which it is branched.
     *
     * Compare this operation with {@link cloneAll()}. The cloneAll() call takes
     * the current DOMNode object and makes a copy of all of its matches. You continue
     * to operate on the same DOMNode object, but the elements inside of the DOMQuery
     * are copies of those before the call to cloneAll().
     *
     * This, on the other hand, copies <i>the DOMQuery</i>, but keeps valid
     * references to the document and the wrapped elements. A new query branch is
     * created, but any changes will be written back to the same document.
     *
     * In practice, this comes in handy when you want to do multiple queries on a part
     * of the document, but then return to a previous set of matches. (see {@link QPTPL}
     * for examples of this in practice).
     *
     * Example:
     *
     * @code
     * <?php
     * $qp = qp( QueryPath::HTML_STUB);
     * $branch = $qp->branch();
     * $branch->find('title')->text('Title');
     * $qp->find('body')->text('This is the body')->writeHTML;
     * ?>
     * @endcode
     *
     * Notice that in the code, each of the DOMQuery objects is doing its own
     * query. However, both are modifying the same document. The result of the above
     * would look something like this:
     *
     * @code
     * <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
     * <html xmlns="http://www.w3.org/1999/xhtml">
     * <head>
     *    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"></meta>
     *    <title>Title</title>
     * </head>
     * <body>This is the body</body>
     * </html>
     * @endcode
     *
     * Notice that while $qp and $banch were performing separate queries, they
     * both modified the same document.
     *
     * In jQuery or a browser-based solution, you generally do not need a branching
     * function because there is (implicitly) only one document. In QueryPath, there
     * is no implicit document. Every document must be explicitly specified (and,
     * in most cases, parsed -- which is costly). Branching makes it possible to
     * work on one document with multiple DOMNode objects.
     *
     * @param string $selector
     *  If a selector is passed in, an additional {@link find()} will be executed
     *  on the branch before it is returned. (Added in QueryPath 2.0.)
     * @return \QueryPath\DOMQuery
     *  A copy of the DOMQuery object that points to the same set of elements that
     *  the original DOMQuery was pointing to.
     * @since 1.1
     * @see   cloneAll()
     * @see   find()
     * @throws CSS\ParseException
     */
    public function branch($selector = NULL)
    {
        $temp = QueryPath::with($this->matches, NULL, $this->options);
        //if (isset($selector)) $temp->find($selector);
        $temp->document = $this->document;
        if (isset($selector)) {
            $temp->findInPlace($selector);
        }

        return $temp;
    }

    /**
     * @param $matches
     * @param $selector
     * @return DOMQuery
     * @throws CSS\ParseException
     */
    protected function inst($matches, $selector): Query
    {
        $dolly = clone $this;
        $dolly->setMatches($matches);

        if (isset($selector)) {
            $dolly->findInPlace($selector);
        }

        return $dolly;
    }

    /**
     * Perform a deep clone of each node in the DOMQuery.
     *
     * @attention
     *   This is an in-place modification of the current QueryPath object.
     *
     * This does not clone the DOMQuery object, but instead clones the
     * list of nodes wrapped by the DOMQuery. Every element is deeply
     * cloned.
     *
     * This method is analogous to jQuery's clone() method.
     *
     * This is a destructive operation, which means that end() will revert
     * the list back to the clone's original.
     * @see qp()
     * @return \QueryPath\DOMQuery
     */
    public function cloneAll(): Query
    {
        $found = new \SplObjectStorage();
        foreach ($this->matches as $m) {
            $found->attach($m->cloneNode(true));
        }
        $this->setMatches($found);

        return $this;
    }

    /**
     * Clone the DOMQuery.
     *
     * This makes a deep clone of the elements inside of the DOMQuery.
     *
     * This clones only the QueryPathImpl, not all of the decorators. The
     * clone operator in PHP should handle the cloning of the decorators.
     */
    public function __clone()
    {
        //XXX: Should we clone the document?

        // Make sure we clone the kids.
        $this->cloneAll();
    }

    /**
     * Call extension methods.
     *
     * This function is used to invoke extension methods. It searches the
     * registered extenstensions for a matching function name. If one is found,
     * it is executed with the arguments in the $arguments array.
     *
     * @throws \ReflectionException
     * @throws QueryPath::Exception
     *  An exception is thrown if a non-existent method is called.
     * @throws Exception
     */
    public function __call($name, $arguments)
    {

        if (!ExtensionRegistry::$useRegistry) {
            throw new Exception("No method named $name found (Extensions disabled).");
        }

        // Loading of extensions is deferred until the first time a
        // non-core method is called. This makes constructing faster, but it
        // may make the first invocation of __call() slower (if there are
        // enough extensions.)
        //
        // The main reason for moving this out of the constructor is that most
        // new DOMQuery instances do not use extensions. Charging qp() calls
        // with the additional hit is not a good idea.
        //
        // Also, this will at least limit the number of circular references.
        if (empty($this->ext)) {
            // Load the registry
            $this->ext = ExtensionRegistry::getExtensions($this);
        }

        // Note that an empty ext registry indicates that extensions are disabled.
        if (!empty($this->ext) && ExtensionRegistry::hasMethod($name)) {
            $owner = ExtensionRegistry::getMethodClass($name);
            $method = new \ReflectionMethod($owner, $name);

            return $method->invokeArgs($this->ext[$owner], $arguments);
        }
        throw new Exception("No method named $name found. Possibly missing an extension.");
    }

    /**
     * Get an iterator for the matches in this object.
     *
     * @return Iterable
     *  Returns an iterator.
     */
    public function getIterator(): \Traversable
    {
        $i = new QueryPathIterator($this->matches);
        $i->options = $this->options;

        return $i;
    }
}
