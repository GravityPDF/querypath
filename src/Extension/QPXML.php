<?php
/** @file
 * XML extensions. See QPXML.
 */

namespace QueryPath\Extension;

use QueryPath\DOMQuery;
use QueryPath\Exception;
use QueryPath\Query;
use QueryPath\QueryPath;
use QueryPath\Extension;

/**
 * Provide QueryPath with additional XML tools.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @author  Xander Guzman <theshadow@shadowpedia.info>
 * @license MIT
 * @see     QueryPath::Extension
 * @see     QueryPath::ExtensionRegistry::extend()
 * @see     QPXML
 * @ingroup querypath_extensions
 */
class QPXML implements Extension
{

	protected $qp;

	public function __construct(Query $qp)
	{
		$this->qp = $qp;
	}

	public function schema($file)
	{
		$doc = $this->qp->branch()->top()->get(0)->ownerDocument;

		if (! $doc->schemaValidate($file)) {
			throw new Exception('Document did not validate against the schema.');
		}
	}

	/**
	 * Get or set a CDATA section.
	 *
	 * If this is given text, it will create a CDATA section in each matched element,
	 * setting that item's value to $text.
	 *
	 * If no parameter is passed in, this will return the first CDATA section that it
	 * finds in the matched elements.
	 *
	 * @param string $text
	 *  The text data to insert into the current matches. If this is NULL, then the first
	 *  CDATA will be returned.
	 *
	 * @return mixed
	 *  If $text is not NULL, this will return a {@link QueryPath}. Otherwise, it will
	 *  return a string. If no CDATA is found, this will return NULL.
	 * @see comment()
	 * @see QueryPath::text()
	 * @see QueryPath::html()
	 */
	public function cdata($text = null)
	{
		if (isset($text)) {
			// Add this text as CDATA in the current elements.
			foreach ($this->qp->get() as $element) {
				$cdata = $element->ownerDocument->createCDATASection($text);
				$element->appendChild($cdata);
			}

			return $this->qp;
		}

		return $this->firstChildText(XML_CDATA_SECTION_NODE);
	}

	/**
	 * Get or set a comment.
	 *
	 * This function is used to get or set comments in an XML or HTML document.
	 * If a $text value is passed in (and is not NULL), then this will add a comment
	 * (with the value $text) to every match in the set.
	 *
	 * If no text is passed in, this will return the first comment in the set of matches.
	 * If no comments are found, NULL will be returned.
	 *
	 * @param string $text
	 *  The text of the comment. If set, a new comment will be created in every item
	 *  wrapped by the current {@link QueryPath}.
	 *
	 * @return mixed
	 *  If $text is set, this will return a {@link QueryPath}. If no text is set, this
	 *  will search for a comment and attempt to return the string value of the first
	 *  comment it finds. If no comment is found, NULL will be returned.
	 * @see cdata()
	 */
	public function comment($text = null)
	{
		if (isset($text)) {
			foreach ($this->qp->get() as $element) {
				$comment = $element->ownerDocument->createComment($text);
				$element->appendChild($comment);
			}

			return $this->qp;
		}

		return $this->firstChildText(XML_COMMENT_NODE);
	}

	/**
	 * Get or set a processing instruction.
	 *
	 * If $text is passed in (and is not NULL), this will add a processing instruction
	 * with the target $prefix and the data $text to every match in the set.
	 *
	 * If no text is passed in, this will return the data of the first processing
	 * instruction in the set of matches, limited to those with the target $prefix
	 * if one is given. If none is found, NULL will be returned.
	 *
	 * @param string $prefix
	 *  The target of the processing instruction (the `xml-stylesheet` in
	 *  `<?xml-stylesheet ...?>`). When getting, NULL matches any target.
	 * @param string $text
	 *  The data of the processing instruction. If set, a new processing instruction
	 *  will be created in every item wrapped by the current {@link QueryPath}.
	 *
	 * @return mixed
	 *  If $text is set, this will return a {@link QueryPath}. If no text is set, this
	 *  will return the string value of the first matching processing instruction, or
	 *  NULL if none is found.
	 * @see comment()
	 */
	public function pi($prefix = null, $text = null)
	{
		if (isset($text)) {
			foreach ($this->qp->get() as $element) {
				$comment = $element->ownerDocument->createProcessingInstruction($prefix, $text);
				$element->appendChild($comment);
			}

			return $this->qp;
		}

		return $this->firstChildText(XML_PI_NODE, $prefix);
	}

	/**
	 * Get the text of the first child node of the given type in the set of matches.
	 *
	 * @param int $nodeType
	 *  One of the XML_*_NODE constants.
	 * @param string $target
	 *  If set, only processing instructions with this target match.
	 *
	 * @return string|null
	 */
	private function firstChildText($nodeType, $target = null)
	{
		foreach ($this->qp->get() as $ele) {
			foreach ($ele->childNodes as $node) {
				if ($node->nodeType === $nodeType && ($target === null || $node->target === $target)) {
					return $node->textContent;
				}
			}
		}

		return null;
	}

	public function toXml()
	{
		return $this->qp->document()->saveXml();
	}

	/**
	 * Create a NIL element.
	 *
	 * @param string $text
	 * @param string $value
	 *
	 * @reval object $element
	 */
	public function createNilElement($text, $value)
	{
		$value   = ($value) ? 'true' : 'false';
		$element = $this->qp->createElement($text);
		$element->attr('xsi:nil', $value);

		return $element;
	}

	/**
	 * Create an element with the given namespace.
	 *
	 * @param string $text
	 * @param string $nsUri
	 *   The namespace URI for the given element.
	 *
	 * @return DOMQuery
	 */
	public function createElement($text, $nsUri = null)
	{
		if (isset($text)) {
			foreach ($this->qp->get() as $element) {
				if ($nsUri === null && strpos($text, ':') !== false) {
					$ns    = strstr($text, ':', true);
					$nsUri = $element->ownerDocument->lookupNamespaceURI($ns);

					if ($nsUri === null) {
						throw new Exception("Undefined namespace for: " . $text);
					}
				}

				$node = null;
				if ($nsUri !== null) {
					$node = $element->ownerDocument->createElementNS(
						$nsUri,
						$text
					);
				} else {
					$node = $element->ownerDocument->createElement($text);
				}

				return QueryPath::with($node);
			}
		}

		return new DOMQuery();
	}

	/**
	 * Append an element.
	 *
	 * @param string $text
	 *
	 * @return DOMQuery
	 */
	public function appendElement($text)
	{
		if (isset($text)) {
			foreach ($this->qp->get() as $element) {
				$node = $this->qp->createElement($text);
				QueryPath::with($element)->append($node);
			}
		}

		return $this->qp;
	}
}
