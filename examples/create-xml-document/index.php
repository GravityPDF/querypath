<?php
/**
 * Using QueryPath.
 *
 * This file contains an example of how QueryPath can be used
 * to generate XML.
 *
 * QueryPath's ability to handle arbitrary XML comes in handy. Fragments of HTML
 * can be composed as external XML documents, and then inserted selectively into
 * an HTML document as needed. Just remember: Every XML document (even just a
 * string) needs to begin with the XML declaration: `<?xml version="1.0"?>`
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/*
 * Create a new XML document wrapped in a QueryPath.
 *  By default, it will point to the root element `<author />`
 */

//TODO
// Use QueryPath::withXML() will allow you to omit the XML declaration "<?xml version="1.0"?\>"
// 	\QueryPath\QueryPath::withXML('<author/>')
//		->append('<lastName>Wiseman</lastName>')
//		->writeXML();

try {
	qp('<?xml version="1.0"?><author></author>')
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
	die($e->getMessage());
}
