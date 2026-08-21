<?php
/**
 * Load XML from a URL, parse the data, and output into a HTML template
 *
 * @author  Emily Brand
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 *
 * @internal IMPORTANT: if you don't trust the source of the data being loaded make sure to sanitize the output
 *
 * @see https://api.jquery.com/resources/api.xml
 * @see https://github.com/symfony/html-sanitizer
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Add the link & class to each key to show in the left div.
 *
 * @param string $name
 *
 * @return string
 */
function addClasses(string $name): string
{
	return '<a href="' . $_SERVER['PHP_SELF'] . '?key=' . htmlentities($name) . '"><span class="keyname">' . htmlentities($name) . '</span></a><br />';
}

/**
 * Fetch a remote XML document and parse it into a DOMDocument.
 *
 * QueryPath will happily fetch a URL for you - `qp($url)` is all it takes, and
 * a stream context passed as the `context` option covers most HTTP needs.
 *
 * Doing the two steps by hand, as here, buys two things that matter when the
 * document is coming off somebody else's server:
 *
 *  - A User-Agent header. Plenty of hosts reject requests that do not send one.
 *  - Recovery mode. Real-world feeds are sometimes truncated or malformed, and
 *    `DOMDocument::$recover` salvages the part that did parse instead of
 *    throwing the whole response away. (At the time of writing jQuery's own
 *    api.xml is served truncated, so without this the example returns nothing.)
 *
 * QueryPath accepts the resulting DOMDocument directly.
 *
 * @param string $url
 *
 * @return \DOMDocument
 */
function fetchXML(string $url): \DOMDocument
{
	$context = stream_context_create([
		'http' => [
			'header' => 'User-Agent: QueryPath (+https://github.com/GravityPDF/querypath)',
		],
	]);

	$xml = file_get_contents($url, false, $context);

	if ($xml === false) {
		throw new RuntimeException('Could not fetch ' . $url);
	}

	$document = new DOMDocument();
	$document->recover = true;

	/* Collect libxml's complaints rather than letting them reach the output. */
	$previous = libxml_use_internal_errors(true);
	$document->loadXML($xml);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	return $document;
}

try {
	// The document skeleton
	$qp = html5qp(__DIR__ . '/template.html', 'body');

	$key = $_GET['key'] ?? '';

	// Only display jQuery methods from these categories
	$categories = [
		'traversing/tree-traversal' => 'Tree Traversal',
		'selectors/child-filter-selectors' => 'Child Filter',
		'selectors/attribute-selectors' => 'Attribute',
		'selectors/content-filter-selector' => 'Content Filter',
		'selectors/basic-filter-selectors' => 'Basic Filter',
		'selectors/hierarchy-selectors' => 'Hierarchy',
		'selectors/basic-css-selectors' => 'Basic',
		'traversing/filtering' => 'Filtering',
		'traversing/miscellaneous-traversal' => 'Miscellaneous Traversing',
		'manipulation/dom-insertion-outside' => 'DOM Insertion, Outside',
		'manipulation/dom-insertion-inside' => 'DOM Insertion, Inside',
		'manipulation/style-properties' => 'Style Properties',
	];

	$jquery = [];

	// Search through the xml file to find all entries of jQuery entities
	foreach (qp(fetchXML('https://api.jquery.com/resources/api.xml'), 'entry') as $entry) {
		foreach ($entry->find('category') as $item) {
			$category = $categories[$item->attr('slug')] ?? '';
			if ($category) {
				$jquery[$entry->attr('name')] = [
					'longdesc' => $entry->find('longdesc')->innerXML(),
					'name' => sprintf('%s: %s', $category, $entry->attr('name')),
				];

				break;
			}
		}
	}

	// Map the keys & sort them
	$jqueryKeys = array_keys($jquery);
	sort($jqueryKeys);

	$links = array_map('addClasses', $jqueryKeys);
	// Add the keys to the nav bar
	$sidebar = $qp->find('#leftbody');
	foreach ($links as $link) {
		$sidebar->append($link);
	}

	// Add the description to the main window if the key exists
	$key = isset($jquery[$key]) ? $key : $jqueryKeys[0];

	$qp->top()->find('#rightfunction')->text('Function: ' . ucfirst($key));
	$qp->top()->find('#rightdesc')->remove();
	$qp->top()->find('#righttitle')->text('jQuery Documentation');
	$qp->top()->find('#righttext')->append($jquery[$key]['longdesc']);

	$qp->top()->find('#current-year')->text(date('Y'));

	// Write the document
	$qp->writeHTML5();
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
} catch (RuntimeException $e) {
	// Handle a failed HTTP request
	echo $e->getMessage();
	exit(1);
}
