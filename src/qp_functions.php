<?php
/**
 * QueryPath's global functions: qp(), htmlqp(), and html5qp().
 *
 * Each is a thin wrapper around the equivalent QueryPath factory method, and each is guarded by
 * function_exists() because the packages this one replaces declared the same names.
 *
 * @see https://github.com/GravityPDF/querypath/wiki/Getting-Started
 */

use QueryPath\DOMQuery;
use QueryPath\QueryPath;

if (! function_exists('qp')) {
	/**
	 * Build a new DOMQuery for an XML or XHTML document.
	 *
	 * This is the procedural entry point to the library, equivalent to QueryPath::with(). Prefer it
	 * over constructing a DOMQuery directly.
	 *
	 * ```php
	 * qp();                                   // a new empty document
	 * qp('path/to/file.xml');                 // from a file or URL
	 * qp('<html><body></body></html>');       // from a markup string
	 * qp(QueryPath::XHTML_STUB, 'title');     // from a stub, positioned at the title element
	 * ```
	 *
	 * $document accepts a markup string, a file path or URL, a DOMDocument, a DOMNode, a
	 * SimpleXMLElement, an array of DOMNodes, or another DOMQuery. Most of the library operates on
	 * elements, so other DOMNode types may not work with every method.
	 *
	 * For HTML, prefer html5qp(). For the full list of supported options, see the wiki.
	 *
	 * @param mixed       $document A document in one of the forms listed above.
	 * @param string|null $selector A CSS 3 selector.
	 * @param array       $options  An associative array of options.
	 *
	 * @return mixed|DOMQuery A DOMQuery, or another class if the QueryPath_class option was set.
	 *
	 * @see https://github.com/GravityPDF/querypath/wiki/Parser-Options
	 * @see https://github.com/GravityPDF/querypath/wiki/CSS-Selector-Reference
	 */
	function qp($document = null, $selector = null, array $options = [])
	{
		return QueryPath::with($document, $selector, $options);
	}
}

if (! function_exists('htmlqp')) {
	/**
	 * Build a new DOMQuery for a legacy HTML document, parsed by libxml.
	 *
	 * Equivalent to QueryPath::withHTML(). Valid XHTML parses fine through qp(), but libxml needs
	 * several settings adjusted before it handles real-world HTML reliably; this function applies
	 * them. Unless overridden, it sets:
	 *
	 * - ignore_parser_warnings: true
	 * - convert_to_encoding: ISO-8859-1
	 * - convert_from_encoding: auto
	 * - use_parser: html
	 *
	 * Parser warnings are also suppressed at the call site, so the application is not notified when
	 * one is emitted. Character set conversion requires the mbstring extension.
	 *
	 * For anything other than legacy documents, prefer html5qp().
	 *
	 * @param mixed       $document A document in any form accepted by qp().
	 * @param string|null $selector A CSS 3 selector.
	 * @param array       $options  An associative array of options.
	 *
	 * @return mixed|DOMQuery A DOMQuery, or another class if the QueryPath_class option was set.
	 *
	 * @see qp()
	 * @see https://github.com/GravityPDF/querypath/wiki/Parser-Options
	 */
	function htmlqp($document = null, $selector = null, array $options = [])
	{
		return QueryPath::withHTML($document, $selector, $options);
	}
}

if (! function_exists('html5qp')) {
	/**
	 * Build a new DOMQuery for an HTML5 document. This is the recommended way to parse HTML.
	 *
	 * Equivalent to QueryPath::withHTML5(). Parsing is handled by masterminds/html5 rather than
	 * libxml, which also copes well with pre-HTML5 markup — though very old HTML may have quirks.
	 *
	 * Because the document is parsed before QueryPath sees it, QueryPath's own parsing options are
	 * not consulted here. Any option supported by masterminds/html5 may be passed instead, and the
	 * QueryPath_class and output options still apply.
	 *
	 * @param mixed       $document A document in any form accepted by qp().
	 * @param string|null $selector A CSS 3 selector.
	 * @param array       $options  An associative array of options, passed on to masterminds/html5.
	 *
	 * @return mixed|DOMQuery A DOMQuery, or another class if the QueryPath_class option was set.
	 *
	 * @see qp()
	 * @see https://github.com/GravityPDF/querypath/wiki/Parser-Options
	 */
	function html5qp($document = null, $selector = null, array $options = [])
	{
		return QueryPath::withHTML5($document, $selector, $options);
	}
}
