<?php

namespace QueryPath;


use DOMNode;
use QueryPath\CSS\DOMTraverser;
use QueryPath\Entities;

/**
 * Class DOM
 *
 * @package QueryPath
 *
 * @property \Traversable|array|\SplObjectStorage matches
 */
abstract class DOM implements Query, \IteratorAggregate, \Countable
{

    /**
     * The array of matches.
     */
    protected $matches = [];

    /**
     * Default parser flags.
     *
     * These are flags that will be used if no global or local flags override them.
     *
     * @since 2.0
     */
    public const DEFAULT_PARSER_FLAGS = NULL;

    public const JS_CSS_ESCAPE_CDATA             = '\\1';
    public const JS_CSS_ESCAPE_CDATA_CCOMMENT    = '/* \\1 */';
    public const JS_CSS_ESCAPE_CDATA_DOUBLESLASH = '// \\1';
    public const JS_CSS_ESCAPE_NONE              = '';

    protected $errTypes = 771; //E_ERROR; | E_USER_ERROR;

    protected $document;
    /**
     * The base DOMDocument.
     */
    protected $options = [
        'parser_flags'                 => NULL,
        'omit_xml_declaration'         => false,
        'replace_entities'             => false,
        'exception_level'              => 771, // E_ERROR | E_USER_ERROR | E_USER_WARNING | E_WARNING
        'ignore_parser_warnings'       => false,
        'escape_xhtml_js_css_sections' => self::JS_CSS_ESCAPE_CDATA_CCOMMENT,
    ];

    /**
     * Constructor.
     *
     * Typically, a new DOMQuery is created by QueryPath::with(), QueryPath::withHTML(),
     * qp(), or htmlqp().
     *
     * @param mixed $document
     *   A document-like object.
     * @param string $string
     *   A CSS 3 Selector
     * @param array $options
     *   An associative array of options.
     * @see qp()
     * @throws Exception
     */
    public function __construct($document = NULL, $string = NULL, $options = [])
    {
        $string = trim($string);
        $this->options = $options + Options::get() + $this->options;

        $parser_flags = $options['parser_flags'] ?? self::DEFAULT_PARSER_FLAGS;
        if (!empty($this->options['ignore_parser_warnings'])) {
            // Don't convert parser warnings into exceptions.
            $this->errTypes = 257; //E_ERROR | E_USER_ERROR;
        } elseif (isset($this->options['exception_level'])) {
            // Set the error level at which exceptions will be thrown. By default,
            // QueryPath will throw exceptions for
            // E_ERROR | E_USER_ERROR | E_WARNING | E_USER_WARNING.
            $this->errTypes = $this->options['exception_level'];
        }

        // Empty: Just create an empty QP.
        if (empty($document)) {
            $this->document = isset($this->options['encoding']) ? new \DOMDocument('1.0',
                $this->options['encoding']) : new \DOMDocument();
            $this->setMatches(new \SplObjectStorage());
        } // Figure out if document is DOM, HTML/XML, or a filename
        elseif (is_object($document)) {

            // This is the most frequent object type.
            if ($document instanceof \SplObjectStorage) {
                $this->matches = $document;
                if ($document->count() !== 0) {
                    $first = $this->getFirstMatch();
                    if (!empty($first->ownerDocument)) {
                        $this->document = $first->ownerDocument;
                    }
                }
            } elseif ($document instanceof self) {
                //$this->matches = $document->get(NULL, TRUE);
                $this->setMatches($document->get(NULL, true));
                if ($this->matches->count() > 0) {
                    $this->document = $this->getFirstMatch()->ownerDocument;
                }
            } elseif ($document instanceof \DOMDocument) {
                $this->document = $document;
                //$this->matches = $this->matches($document->documentElement);
                $this->setMatches($document->documentElement);
            } elseif ($document instanceof \DOMNode) {
                $this->document = $document->ownerDocument;
                //$this->matches = array($document);
                $this->setMatches($document);
            } elseif ($document instanceof \Masterminds\HTML5) {
                $this->document = $document;
                $this->setMatches($document->documentElement);
            } elseif ($document instanceof \SimpleXMLElement) {
                $import = dom_import_simplexml($document);
                $this->document = $import->ownerDocument;
                //$this->matches = array($import);
                $this->setMatches($import);
            } else {
                throw new \QueryPath\Exception('Unsupported class type: ' . get_class($document));
            }
        } elseif (is_array($document)) {
            //trigger_error('Detected deprecated array support', E_USER_NOTICE);
            if (!empty($document) && $document[0] instanceof \DOMNode) {
                $found = new \SplObjectStorage();
                foreach ($document as $item) {
                    $found->attach($item);
                }
                //$this->matches = $found;
                $this->setMatches($found);
                $this->document = $this->getFirstMatch()->ownerDocument;
            }
        } elseif ($this->isXMLish($document)) {
            // $document is a string with XML
            $this->document = $this->parseXMLString($document);
            $this->setMatches($this->document->documentElement);
        } else {

            // $document is a filename
            $context = empty($options['context']) ? NULL : $options['context'];
            $this->document = $this->parseXMLFile($document, $parser_flags, $context);
            $this->setMatches($this->document->documentElement);
        }

        // Globally set the output option.
        $this->document->formatOutput = true;
        if (isset($this->options['format_output']) && $this->options['format_output'] === false) {
            $this->document->formatOutput = false;
        }

        // Do a find if the second param was set.
        if (strlen($string) > 0) {
            // We don't issue a find because that creates a new DOMQuery.
            //$this->find($string);

            $query = new DOMTraverser($this->matches);
            $query->find($string);
            $this->setMatches($query->matches());
        }
    }

    private function parseXMLString($string, $flags = NULL)
    {
        $document = new \DOMDocument('1.0');
        $lead = strtolower(substr($string, 0, 5)); // <?xml
        try {
            set_error_handler([ParseException::class, 'initializeFromError'], $this->errTypes);

            if (isset($this->options['convert_to_encoding'])) {
                // Is there another way to do this?

                $from_enc = $this->options['convert_from_encoding'] ?? 'auto';
                $to_enc = $this->options['convert_to_encoding'];

                if (function_exists('mb_convert_encoding')) {
                    $string = mb_convert_encoding($string, $to_enc, $from_enc);
                }

            }

            // This is to avoid cases where low ascii digits have slipped into HTML.
            // AFAIK, it should not adversly effect UTF-8 documents.
            if (!empty($this->options['strip_low_ascii'])) {
                $string = filter_var($string, FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW);
            }

            // Allow users to override parser settings.
            $useParser = '';
            if (!empty($this->options['use_parser'])) {
                $useParser = strtolower($this->options['use_parser']);
            }

            // If HTML parser is requested, we use it.
            if ($useParser === 'html') {
                $document->loadHTML($string);
            } // Parse as XML if it looks like XML, or if XML parser is requested.
            elseif ($lead === '<?xml' || $useParser === 'xml') {
                if ($this->options['replace_entities']) {
                    $string = Entities::replaceAllEntities($string);
                }
                $document->loadXML($string, $flags);
            } // In all other cases, we try the HTML parser.
            else {
                $document->loadHTML($string);
            }
        } // Emulate 'finally' behavior.
        catch (Exception $e) {
            restore_error_handler();
            throw $e;
        }
        restore_error_handler();

        if (empty($document)) {
            throw new \QueryPath\ParseException('Unknown parser exception.');
        }

        return $document;
    }

    /**
     * EXPERT: Be very, very careful using this.
     * A utility function for setting the current set of matches.
     * It makes sure the last matches buffer is set (for end() and andSelf()).
     *
     * @since 2.0
     * @param $matches
     */
    public function setMatches($matches)
    {
        // This causes a lot of overhead....
        //if ($unique) $matches = self::unique($matches);
        $this->last = $this->matches;

        // Just set current matches.
        if ($matches instanceof \SplObjectStorage) {
            $this->matches = $matches;
        } // This is likely legacy code that needs conversion.
        elseif (is_array($matches)) {
            trigger_error('Legacy array detected.');
            $tmp = new \SplObjectStorage();
            foreach ($matches as $m) {
                $tmp->attach($m);
            }
            $this->matches = $tmp;
        }
        // For non-arrays, try to create a new match set and
        // add this object.
        else {
            $found = new \SplObjectStorage();
            if (isset($matches)) {
                $found->attach($matches);
            }
            $this->matches = $found;
        }

        // EXPERIMENTAL: Support for qp()->length.
        $this->length = $this->matches->count();
    }

    /**
     * A depth-checking function. Typically, it only needs to be
     * invoked with the first parameter. The rest are used for recursion.
     *
     * @see deepest();
     * @param DOMNode $ele
     *  The element.
     * @param int $depth
     *  The depth guage
     * @param mixed $current
     *  The current set.
     * @param DOMNode $deepest
     *  A reference to the current deepest node.
     * @return array
     *  Returns an array of DOM nodes.
     */
    protected function deepestNode(\DOMNode $ele, $depth = 0, $current = NULL, &$deepest = NULL)
    {
        // FIXME: Should this use SplObjectStorage?
        if (!isset($current)) {
            $current = [$ele];
        }
        if (!isset($deepest)) {
            $deepest = $depth;
        }
        if ($ele->hasChildNodes()) {
            foreach ($ele->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $current = $this->deepestNode($child, $depth + 1, $current, $deepest);
                }
            }
        } elseif ($depth > $deepest) {
            $current = [$ele];
            $deepest = $depth;
        } elseif ($depth === $deepest) {
            $current[] = $ele;
        }

        return $current;
    }

    /**
     * Prepare an item for insertion into a DOM.
     *
     * This handles a variety of boilerplate tasks that need doing before an
     * indeterminate object can be inserted into a DOM tree.
     * - If item is a string, this is converted into a document fragment and returned.
     * - If item is a DOMQuery, then all items are retrieved and converted into
     *   a document fragment and returned.
     * - If the item is a DOMNode, it is imported into the current DOM if necessary.
     * - If the item is a SimpleXMLElement, it is converted into a DOM node and then
     *   imported.
     *
     * @param mixed $item
     *  Item to prepare for insert.
     * @return mixed
     *  Returns the prepared item.
     * @throws QueryPath::Exception
     *  Thrown if the object passed in is not of a supprted object type.
     * @throws Exception
     */
    protected function prepareInsert($item)
    {
        if (empty($item)) {
            return NULL;
        }

        if (is_string($item)) {
            // If configured to do so, replace all entities.
            if ($this->options['replace_entities']) {
                $item = Entities::replaceAllEntities($item);
            }

            $frag = $this->document->createDocumentFragment();
            try {
                set_error_handler([ParseException::class, 'initializeFromError'], $this->errTypes);
                $frag->appendXML($item);
            } // Simulate a finally block.
            catch (Exception $e) {
                restore_error_handler();
                throw $e;
            }
            restore_error_handler();

            return $frag;
        }

        if ($item instanceof self) {
            if ($item->count() === 0) {
                return NULL;
            }

            $frag = $this->document->createDocumentFragment();
            foreach ($item->matches as $m) {
                $frag->appendXML($item->document->saveXML($m));
            }

            return $frag;
        }

        if ($item instanceof \DOMNode) {
            if ($item->ownerDocument !== $this->document) {
                // Deep clone this and attach it to this document
                $item = $this->document->importNode($item, true);
            }

            return $item;
        }

        if ($item instanceof \SimpleXMLElement) {
            $element = dom_import_simplexml($item);

            return $this->document->importNode($element, true);
        }
        // What should we do here?
        //var_dump($item);
        throw new \QueryPath\Exception('Cannot prepare item of unsupported type: ' . gettype($item));
    }

    /**
     * Convenience function for getNthMatch(0).
     */
    protected function getFirstMatch()
    {
        $this->matches->rewind();

        return $this->matches->current();
    }

    /**
     * Parse an XML or HTML file.
     *
     * This attempts to autodetect the type of file, and then parse it.
     *
     * @param string $filename
     *  The file name to parse.
     * @param int $flags
     *  The OR-combined flags accepted by the DOM parser. See the PHP documentation
     *  for DOM or for libxml.
     * @param resource $context
     *  The stream context for the file IO. If this is set, then an alternate
     *  parsing path is followed: The file is loaded by PHP's stream-aware IO
     *  facilities, read entirely into memory, and then handed off to
     *  {@link parseXMLString()}. On large files, this can have a performance impact.
     * @throws \QueryPath\ParseException
     *  Thrown when a file cannot be loaded or parsed.
     */
    private function parseXMLFile($filename, $flags = NULL, $context = NULL)
    {

        // If a context is specified, we basically have to do the reading in
        // two steps:
        if (!empty($context)) {
            try {
                set_error_handler(['\QueryPath\ParseException', 'initializeFromError'], $this->errTypes);
                $contents = file_get_contents($filename, false, $context);
            }
                // Apparently there is no 'finally' in PHP, so we have to restore the error
                // handler this way:
            catch (Exception $e) {
                restore_error_handler();
                throw $e;
            }
            restore_error_handler();

            if ($contents == false) {
                throw new \QueryPath\ParseException(sprintf('Contents of the file %s could not be retrieved.',
                    $filename));
            }

            return $this->parseXMLString($contents, $flags);
        }

        $document = new \DOMDocument();
        $lastDot = strrpos($filename, '.');

        $htmlExtensions = [
            '.html' => 1,
            '.htm'  => 1,
        ];

        // Allow users to override parser settings.
        if (empty($this->options['use_parser'])) {
            $useParser = '';
        } else {
            $useParser = strtolower($this->options['use_parser']);
        }

        $ext = $lastDot !== false ? strtolower(substr($filename, $lastDot)) : '';

        try {
            set_error_handler([ParseException::class, 'initializeFromError'], $this->errTypes);

            // If the parser is explicitly set to XML, use that parser.
            if ($useParser === 'xml') {
                $document->load($filename, $flags);
            } // Otherwise, see if it looks like HTML.
            elseif ($useParser === 'html' || isset($htmlExtensions[$ext])) {
                // Try parsing it as HTML.
                $document->loadHTMLFile($filename);
            } // Default to XML.
            else {
                $document->load($filename, $flags);
            }

        } // Emulate 'finally' behavior.
        catch (Exception $e) {
            restore_error_handler();
            throw $e;
        }
        restore_error_handler();

        return $document;
    }

    /**
     * Determine whether a given string looks like XML or not.
     *
     * Basically, this scans a portion of the supplied string, checking to see
     * if it has a tag-like structure. It is possible to "confuse" this, which
     * may subsequently result in parse errors, but in the vast majority of
     * cases, this method serves as a valid inicator of whether or not the
     * content looks like XML.
     *
     * Things that are intentional excluded:
     * - plain text with no markup.
     * - strings that look like filesystem paths.
     *
     * Subclasses SHOULD NOT OVERRIDE THIS. Altering it may be altering
     * core assumptions about how things work. Instead, classes should
     * override the constructor and pass in only one of the parsed types
     * that this class expects.
     */
    protected function isXMLish($string)
    {
        return (strpos($string, '<') !== false && strpos($string, '>') !== false);
    }

    /**
     * A utility function for retriving a match by index.
     *
     * The internal data structure used in DOMQuery does not have
     * strong random access support, so we suppliment it with this method.
     *
     * @param $index
     * @return object|void
     */
    protected function getNthMatch(int $index)
    {
        if ($index < 0 || $index > $this->matches->count()) {
            return;
        }

        $i = 0;
        foreach ($this->matches as $m) {
            if ($i++ === $index) {
                return $m;
            }
        }
    }
}