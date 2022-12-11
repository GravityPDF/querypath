<?php
/**
 * Example of grabbing and parsing Linked Data from DBPedia.
 *
 * This example illustrates how QueryPath can be used to do the following:
 *
 * - Make a robust HTTP connection to a remote server to fetch data.
 * - Using context to control the underlying stream.
 * - Working with Linked Data.
 * - Work with XML Namespaces in documents.
 *   * Using namespaces to access elements in selectors
 *   * Using namespaces to access attributes in selectors
 *   * Using namespaces to access attributes in XML methods.
 *
 * The code here connects to the DBPedia server and looks up the Linked
 * Data stored there for a particular Wikipedia entry (any Wikipedia
 * wiki name should work here).
 *
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     http://www.w3.org/DesignIssues/LinkedData.html
 * @see     http://dbpedia.org
 * @see     sparql.php
 * @see     musicbrainz.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// The URL to look up:
$url = 'https://dbpedia.org/resource/Ben_Sealey';

// HTTP headers:
$headers = [
	'Accept: application/rdf,application/rdf+xml;q=0.9,*/*;q=0.8',
	'Accept-Language: en-us,en',
	'Accept-Charset: ISO-8859-1,utf-8',
	'User-Agent: QueryPath/1.2',
];

// The context options:
$options = [
	'http' => [
		'method' => 'GET',
		'protocol_version' => 1.1,
		'header' => implode("\r\n", $headers),
	],
];

// Create a stream context that will tell QueryPath how to load the file.
$context = stream_context_create($options);

// Fetch the URL and select all rdf:Description elements.
// (Note that | is the CSS 3 equiv of colons for namespacing.)
// To add the context, we pass it in as an option to QueryPath.
$qp = qp($url, 'rdf|Description', ['context' => $context]);

printf('There are %d descriptions in this record.<br>' . PHP_EOL, $qp->count());

// Here, we use foaf|* to select all elements in the FOAF namespace.
printf('There are %d DBO items in this record.<br><br>' . PHP_EOL, $qp->top()->find('dbo|*')->count());

// Standard pseudo-classes that are not HTML specific can be used on namespaced elements, too.
echo 'About (RDFS): ' . $qp->top()->find('rdfs|label:first-of-type')->text() . '<br>' . PHP_EOL;
echo 'About (FOAF): ' . $qp->top()->find('foaf|name:first-of-type')->text() . '<br>' . PHP_EOL;

// Namespaced attributes can be retrieved using the same sort of delimiting.
echo PHP_EOL . '<br>Comment:<br>' . PHP_EOL;
echo $qp->top()->find('rdfs|comment[xml|lang="en"]')->text();
echo '<br>' . PHP_EOL;

$qp->top();

echo PHP_EOL . '<br>Other Sites:<br>' . PHP_EOL;
foreach ($qp as $item) {
	echo $item->attr('rdf:about') . '<br>' . PHP_EOL;
}
