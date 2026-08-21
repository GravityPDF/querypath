<?php
/**
 * Using QueryPath.
 *
 * This file contains an example of how QueryPath can be used
 * to generate XML.
 *
 * QueryPath's ability to handle arbitrary XML comes in handy. Fragments of HTML
 * can be composed as external XML documents, and then inserted selectively into
 * an HTML document as needed.
 *
 * Note that `qp()` inspects its input to decide whether it is looking at XML or
 * HTML, so an XML string handed to it has to begin with the XML declaration
 * `<?xml version="1.0"?>`. `QueryPath::withXML()`, used below, always parses as
 * XML, so the declaration is optional there.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/*
 * Create a new XML document wrapped in a QueryPath.
 *
 * By default, the QueryPath points at the root element - here, `<author />`.
 *
 * `QueryPath::withXML()` is used rather than `qp()` because it parses its input
 * as XML unconditionally, so the leading XML declaration can be left off. Pass
 * the same string to `qp()` and the declaration is required, because `qp()`
 * decides how to treat the input by looking at it.
 */

try {
	\QueryPath\QueryPath::withXML('<author></author>')
		// Add a new last name inside of author.
		->append('<lastName>Wiseman</lastName>')
		// Select all of the children of <author/>. In this case,
		// that is <lastName/>
		->children()
		// Oh, wait... we wanted last name to be inside of a <name/>
		// element. Use wrap to wrap the current element in something:
		->wrap('<name/>')
		// And before last name, we want to add first name.
		->before('<firstName/>')
		// Select first name
		->prev()
		// Set the text of first name
		->text('Simon')
		// And then after first name, add the patronymic
		->after('<middleName>J.</middleName>')
		// Now go back to the root element, the top of the document.
		->top()
		// Add another tag -- origin.
		->append('<origin>Australia</origin>')
		// turn the QueryPath contents back into a string. Since we are
		// at the top of the document, the whole document will be converted
		// to a string.
		->writeXML();
} catch (\QueryPath\Exception $e) {
	echo $e->getMessage();
	exit(1);
}
