<?php
/**
 * Load XML from a URL, parse the data, and output into a HTML template
 *
 * @author  Original: Emily Brand
 * @author  Updated by: Jake Jackson
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

// The document skeleton
$qp = html5qp(__DIR__ . '/template.html', 'body');

$key = $_GET['key'] ?? '';

// Only display jQuery methods from these categories
$categories = [
	'traversing/tree-traversal'          => 'Tree Traversal',
	'selectors/child-filter-selectors'  => 'Child Filter',
	'selectors/attribute-selectors'     => 'Attribute',
	'selectors/content-filter-selector' => 'Content Filter',
	'selectors/basic-filter-selectors'  => 'Basic Filter',
	'selectors/hierarchy-selectors'     => 'Hierarchy',
	'selectors/basic-css-selectors'     => 'Basic',
	'traversing/filtering'               => 'Filtering',
	'traversing/miscellaneous-traversal' => 'Miscellaneous Traversing',
	'manipulation/dom-insertion-outside'   => 'DOM Insertion, Outside',
	'manipulation/dom-insertion-inside'    => 'DOM Insertion, Inside',
	'manipulation/style-properties'        => 'Style Properties',
];

$jquery = [];

try {
	// Search through the xml file to find all entries of jQuery entities
	foreach (qp('https://api.jquery.com/resources/api.xml', 'entry') as $entry) {
		foreach ($entry->find('category') as $item) {
			$category = $categories[ $item->attr('slug') ] ?? '';
			if ($category) {
				$jquery[ $entry->attr('name') ] = [
					'longdesc' => $entry->find('longdesc')->innerXML(),
					'name'     => sprintf('%s: %s', $category, $entry->attr('name')),
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
	$qp->top()->find('#righttext')->append($jquery[ $key ]['longdesc']);

	$qp->top()->find('#current-year')->text(date('Y'));

	// Write the document
	$qp->writeHTML5();
} catch (\QueryPath\Exception $e) {
	die($e->getMessage());
}
