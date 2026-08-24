<?php
/**
 * QueryPath builds, parses, searches, and modifies XML and HTML documents through a jQuery-like
 * fluent API.
 *
 * ```php
 * require_once __DIR__ . '/vendor/autoload.php';
 *
 * $xml = '<?xml version="1.0"?><test><foo id="myID"/></test>';
 *
 * // Procedural, a la jQuery:
 * qp($xml, '#myID')->append('<new><element/></new>')->top()->writeXML();
 *
 * // Or through the factory:
 * QueryPath::with($xml)->find('#myID')->append('<new><element/></new>')->top()->writeXML();
 * ```
 *
 * @author    M Butcher <matt@aleph-null.tv>
 * @license   MIT
 * @copyright Copyright (c) 2009-2012, Matt Butcher.
 * @see       https://github.com/GravityPDF/querypath/wiki/Getting-Started
 */

namespace QueryPath;

use Masterminds\HTML5;
use QueryPath\ExtensionRegistry;

/**
 * The top-level class for the library: document factories, extension management, and utilities.
 *
 * The factories all return a DOMQuery, which is where the bulk of the API lives.
 *
 * @see DOMQuery
 * @see https://github.com/GravityPDF/querypath/wiki/Getting-Started
 */
class QueryPath
{

	/**
	 * The version string for this version of QueryPath.
	 *
	 * Standard releases will be of the following form: <MAJOR>.<MINOR>[.<PATCH>][-STABILITY].
	 *
	 * Examples:
	 * - 2.0
	 * - 2.1.1
	 * - 2.0-alpha1
	 *
	 * Developer releases will always be of the form dev-<DATE>.
	 *
	 * @since 2.0
	 */
	public const VERSION = '3.2.2';

	/**
	 * Major version number.
	 *
	 * Examples:
	 * - 3
	 * - 4
	 *
	 * @since 3.0.1
	 */
	public const VERSION_MAJOR = 3;

	/**
	 * A stub HTML 4.01 document.
	 *
	 * For generating legacy HTML content. Prefer self::HTML5_STUB for new work.
	 *
	 * Use this stub with the HTML family of methods: html(), writeHTML(), innerHTML().
	 *
	 * @see self::HTML5_STUB
	 */
	public const HTML_STUB = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
  <html lang="en">
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Untitled</title>
  </head>
  <body></body>
  </html>';

	/**
	 * A stub HTML5 document. This is the stub to prefer for new work.
	 *
	 * Use it with html5qp() and the HTML5 family of methods: html5(), writeHTML5().
	 *
	 * ```php
	 * echo html5qp(QueryPath::HTML5_STUB, 'body')->append('<h1>Title</h1>')->top()->html5();
	 * ```
	 */
	public const HTML5_STUB = '<!DOCTYPE html>
    <html>
    <head>
    <title>Untitled</title>
    </head>
    <body></body>
    </html>';

	/**
	 * A stub XHTML document.
	 *
	 * XHTML is an XML format, so use the XML methods with this fragment: xml(), innerXML(),
	 * writeXML().
	 *
	 * ```php
	 * $qp = qp(QueryPath::XHTML_STUB); // a new XHTML document
	 * $qp->writeXML();                 // written as well-formed XHTML
	 * ```
	 *
	 * @since 2.0
	 */
	public const XHTML_STUB = '<?xml version="1.0"?>
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
  <html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Untitled</title>
  </head>
  <body></body>
  </html>';


	/**
	 * @param mixed $document
	 * @param string|null $selector
	 * @param array $options
	 *
	 * @return mixed|DOMQuery
	 *
	 * @see qp()
	 * @see htmlqp()
	 */
	public static function with($document = null, $selector = null, array $options = [])
	{
		$qpClass = $options['QueryPath_class'] ?? '\QueryPath\DOMQuery';

		return new $qpClass($document, $selector, $options);
	}

	/**
	 * @param mixed $source
	 * @param string|null $selector
	 * @param array $options
	 *
	 * @return mixed|DOMQuery
	 *
	 * @see qp()
	 */
	public static function withXML($source = null, $selector = null, array $options = [])
	{
		$options += [
			'use_parser' => 'xml',
		];

		return self::with($source, $selector, $options);
	}

	/**
	 * @param mixed $source
	 * @param string|null $selector
	 * @param array $options
	 *
	 * @return mixed|DOMQuery
	 *
	 * @see htmlqp()
	 */
	public static function withHTML($source = null, $selector = null, array $options = [])
	{
		// Need a way to force an HTML parse instead of an XML parse when the
		// doctype is XHTML, since many XHTML documents are not valid XML
		// (because of coding errors, not by design).

		$options += [
			'ignore_parser_warnings' => true,
			'convert_to_encoding'    => 'ISO-8859-1',
			'convert_from_encoding'  => 'auto',
			//'replace_entities' => TRUE,
			'use_parser'             => 'html',
			// This is stripping actually necessary low ASCII.
			//'strip_low_ascii' => TRUE,
		];

		return @self::with($source, $selector, $options);
	}

	/**
	 * Parse HTML5 documents.
	 *
	 * This uses HTML5-PHP to parse the document. In actuality, this parser does
	 * a fine job with pre-HTML5 documents in most cases, though really old HTML
	 * (like 2.0) may have some substantial quirks.
	 *
	 * Because the document is parsed before QueryPath sees it, QueryPath's own parsing options are
	 * not consulted here. Any option supported by masterminds/html5 may be passed instead, and is
	 * forwarded to it. QueryPath_class and the output options still apply.
	 *
	 * @see https://github.com/GravityPDF/querypath/wiki/Parser-Options
	 *
	 * @param mixed  $source
	 *   A document as an HTML string, or a path/URL. For compatibility with
	 *   existing functions, a DOMDocument, SimpleXMLElement, DOMNode or array
	 *   of DOMNodes will be passed through as well. However, these types are not
	 *   validated in any way.
	 *
	 * @param string|null $selector
	 *   A CSS3 selector.
	 *
	 * @param array  $options
	 *   An associative array of options, which is passed on into HTML5-PHP. Note
	 *   that the standard QueryPath options may be ignored for this function,
	 *   since it uses a different parser.
	 *
	 * @return mixed|DOMQuery
	 *
	 * @see html5qp()
	 */
	public static function withHTML5($source = null, $selector = null, array $options = [])
	{
		$qpClass = $options['QueryPath_class'] ?? '\QueryPath\DOMQuery';

		if (is_string($source)) {
			$html5 = new HTML5();
			if (strpos($source, '<') !== false && strpos($source, '>') !== false) {
				$source = $html5->loadHTML($source);
			} else {
				$source = $html5->load($source);
			}
		}

		return new $qpClass($source, $selector, $options);
	}

	/**
	 * Enable one or more extensions.
	 *
	 * Extensions add methods to every DOMQuery created afterwards, so enable them before building
	 * the objects that use them. Names are fully qualified class names.
	 *
	 * ```php
	 * QueryPath::enable(\QueryPath\Extension\QPXML::class);
	 *
	 * // or several at once
	 * QueryPath::enable([
	 *     \QueryPath\Extension\QPXML::class,
	 *     \QueryPath\Extension\QPXSL::class,
	 * ]);
	 * ```
	 *
	 * If you are not using an autoloader, `require` the files defining the extensions first.
	 *
	 * @param mixed $extensionNames
	 *   An extension class name, or an array of them.
	 *
	 * @see https://github.com/GravityPDF/querypath/wiki/Writing-Extensions
	 */
	public static function enable($extensionNames): void
	{
		if (is_array($extensionNames)) {
			foreach ($extensionNames as $extension) {
				ExtensionRegistry::extend($extension);
			}
		} else {
			ExtensionRegistry::extend($extensionNames);
		}
	}

	/**
	 * Get a list of all of the enabled extensions.
	 *
	 * ```php
	 * print_r(QueryPath::enabledExtensions());
	 * ```
	 *
	 * @return array
	 *   An array of extension class names.
	 *
	 * @see ExtensionRegistry
	 */
	public static function enabledExtensions(): array
	{
		return ExtensionRegistry::extensionNames();
	}


	/**
	 * A static function for transforming data into a Data URL.
	 *
	 * This can be used to create Data URLs for injection into CSS, JavaScript, or other
	 * non-XML/HTML content. If you are working with QP objects, you may want to use
	 * dataURL() instead.
	 *
	 * @param mixed    $data
	 *    The contents to inject as the data. The value can be any one of the following:
	 *    - A URL: If this is given, then the subsystem will read the content from that URL. THIS
	 *    MUST BE A FULL URL, not a relative path.
	 *    - A string of data: If this is given, then the subsystem will encode the string.
	 *    - A stream or file handle: If this is given, the stream's contents will be encoded
	 *    and inserted as data.
	 *    (Note that we make the assumption here that you would never want to set data to be
	 *    a URL. If this is an incorrect assumption, file a bug.)
	 * @param string   $mime
	 *    The MIME type of the document.
	 * @param resource $context
	 *    A valid context. Use this only if you need to pass a stream context. This is only necessary
	 *    if $data is a URL. (See {@link stream_context_create()}).
	 *
	 * @return string An encoded data URL.
	 */
	public static function encodeDataURL($data, $mime = 'application/octet-stream', $context = null): string
	{
		if (is_resource($data)) {
			$data = stream_get_contents($data);
		} elseif (filter_var($data, FILTER_VALIDATE_URL)) {
			$data = file_get_contents($data, false, $context);
		}

		$encoded = base64_encode($data);

		return 'data:' . $mime . ';base64,' . $encoded;
	}
}
