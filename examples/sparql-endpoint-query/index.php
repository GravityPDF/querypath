<?php
/**
 * Querying a SPARQL endpoint
 *
 * SPARQL is the query language of the semantic web, and public endpoints such as
 * DBpedia will answer a query with an XML document. That makes QueryPath a
 * natural fit: build the request, hand the URL to `qp()`, and traverse the result.
 *
 * This example runs a query against DBpedia and renders the response as an HTML
 * table. Nothing about it is DBpedia-specific - the SPARQL Query Results XML
 * Format is a W3C standard, so the same parsing code works against any endpoint.
 *
 * The query is sent as a GET request here because that is what DBpedia expects.
 * POST works too - build a stream context and pass it to QueryPath as the
 * `context` option, as shown in the `http-stream-xml-namespaces-and-linked-data`
 * example.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.w3.org/TR/rdf-sparql-XMLres/
 * @see     https://dbpedia.org/sparql
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/* DBpedia's public SPARQL endpoint. */
$endpoint = 'https://dbpedia.org/sparql';

/* Ask for every label DBpedia holds for The Beatles, and the language of each. */
$sparql = '
	PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

	SELECT ?label (lang(?label) AS ?language)
	WHERE {
		<http://dbpedia.org/resource/The_Beatles> rdfs:label ?label
	}
	ORDER BY ?language
';

/*
 * Build the request. The `format` parameter is what asks the endpoint for XML
 * rather than its default HTML result browser.
 */
$url = $endpoint . '?' . http_build_query([
	'query' => $sparql,
	'format' => 'application/sparql-results+xml',
]);

echo '<h1>Querying a SPARQL endpoint</h1>';

echo '<h2>The query</h2>';
echo '<pre><code>' . htmlspecialchars(trim($sparql)) . '</code></pre>';

try {
	/*
	 * Retrieve and parse the response in one step - qp() will fetch a URL just
	 * as happily as it reads a local file or a string.
	 */
	$qp = qp($url);

	/*
	 * A SPARQL result document has two halves:
	 *
	 *   <head>    one <variable name="..."/> per column
	 *   <results> one <result> per row, each holding a <binding name="..."/> per cell
	 *
	 * Start with the column names.
	 */
	$columns = [];

	foreach ($qp->find('head > variable') as $variable) {
		$columns[] = $variable->attr('name');
	}

	if (count($columns) === 0) {
		echo '<p>The endpoint returned no columns.</p>';
		exit;
	}

	/*
	 * Then the rows. Bindings are keyed by name rather than by position, and a
	 * row may omit a binding entirely when the value is unbound, so index each
	 * row by name instead of relying on the order the cells arrive in.
	 */
	$rows = [];

	foreach ($qp->top()->find('results > result') as $result) {
		$row = [];

		foreach ($result->children('binding') as $binding) {
			$row[$binding->attr('name')] = $binding->text();
		}

		$rows[] = $row;
	}

	echo '<h2>' . count($rows) . ' result(s)</h2>';

	/* Render the table. */
	echo '<table border="1" cellpadding="4" cellspacing="0">';

	echo '<tr>';
	foreach ($columns as $column) {
		echo '<th>' . htmlspecialchars($column) . '</th>';
	}
	echo '</tr>';

	foreach ($rows as $row) {
		echo '<tr>';

		foreach ($columns as $column) {
			/* An unbound value is simply missing from the row. */
			echo '<td>' . htmlspecialchars($row[$column] ?? '') . '</td>';
		}

		echo '</tr>';
	}

	echo '</table>';
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}
