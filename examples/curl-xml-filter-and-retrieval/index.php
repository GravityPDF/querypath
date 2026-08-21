<?php
/**
 * Making REST-style requests with cURL and parsing the XML
 *
 * This example queries PubMed through the NCBI E-utilities API, which is a
 * textbook two-request REST flow:
 *
 *   1. `esearch` takes a search term and returns a list of record IDs.
 *   2. `esummary` takes those IDs and returns the records themselves.
 *
 * Both answer with XML, so QueryPath can read the response directly. The second
 * request depends on the first, which is the interesting part - the IDs are
 * pulled out of one document and fed into the next.
 *
 * The summary format is worth a look. Rather than naming each field with its own
 * tag, every field is an `<Item>` carrying a `Name` attribute:
 *
 *   <Item Name="Title" Type="String">...</Item>
 *
 * That is exactly what CSS attribute selectors are for - `Item[Name="Title"]`.
 *
 * cURL is used here instead of handing the URL straight to `qp()` so the request
 * itself can be controlled: a User-Agent, timeouts, and a retry when the API asks
 * us to slow down.
 *
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.ncbi.nlm.nih.gov/books/NBK25501/
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/* The API is happy with anonymous use at a few requests per second. */
$endpoint = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/';

$term = 'crispr gene editing';
$limit = 8;

/*
 * E-utilities asks callers to identify their application with a `tool`
 * parameter, and to add an `email` (and an API key) if you intend to make
 * requests in volume.
 */
$search_url = $endpoint . 'esearch.fcgi?' . http_build_query([
	'db' => 'pubmed',
	'term' => $term,
	'retmax' => $limit,
	'sort' => 'date',
	'tool' => 'querypath-example',
]);

echo '<h1>Recent papers on <em>' . htmlspecialchars($term) . '</em></h1>';

try {
	/* Request one: search, and collect the IDs it comes back with. */
	$results = qp(get($search_url));

	$ids = [];

	foreach ($results->find('IdList > Id') as $id) {
		$ids[] = $id->text();
	}

	if (count($ids) === 0) {
		echo '<p>No results found.</p>';
		exit;
	}

	printf(
		'<p>%s matched %s records; showing the %d most recent.</p>',
		htmlspecialchars($term),
		number_format((float) $results->top()->find('eSearchResult > Count')->text()),
		count($ids)
	);

	/*
	 * Request two: fetch the summaries for those IDs.
	 *
	 * E-utilities takes them as a comma-separated list, so all of the records
	 * arrive in a single response rather than one request each.
	 */
	$summary_url = $endpoint . 'esummary.fcgi?' . http_build_query([
		'db' => 'pubmed',
		'id' => implode(',', $ids),
		'tool' => 'querypath-example',
	]);

	$summaries = qp(get($summary_url));

	/* Each record is a <DocSum>. */
	echo '<ol>';

	foreach ($summaries->find('DocSum') as $record) {
		/*
		 * Fields are <Item> elements distinguished by their Name attribute, so
		 * an attribute selector picks out the one wanted. A field that is absent
		 * simply matches nothing and text() returns an empty string.
		 */
		$title = $record->branch()->find('Item[Name="Title"]')->text();
		$journal = $record->branch()->find('Item[Name="Source"]')->text();
		$date = $record->branch()->find('Item[Name="PubDate"]')->text();
		$doi = $record->branch()->find('Item[Name="DOI"]')->text();

		/*
		 * Authors are a nested list. The child combinator matters here: without
		 * it, the outer Item[Name="AuthorList"] would match as well.
		 */
		$authors = $record->branch()
			->find('Item[Name="AuthorList"] > Item[Name="Author"]')
			->textImplode(', ');

		echo '<li>';
		printf('<strong>%s</strong><br>', htmlspecialchars($title));
		printf('%s<br>', htmlspecialchars($authors !== '' ? $authors : 'No listed authors'));
		printf('<em>%s</em>, %s', htmlspecialchars($journal), htmlspecialchars($date));

		if ($doi !== '') {
			printf(' &middot; <a href="https://doi.org/%1$s">doi:%1$s</a>', htmlspecialchars($doi));
		}

		echo '</li>';
	}

	echo '</ol>';

	/* The raw XML behind the two requests, for reference. */
	echo '<h2>The XML</h2>';

	echo '<h3>Search</h3>';
	printf('<code>%s</code>', htmlspecialchars($search_url));
	echo '<pre><code>' . htmlspecialchars($results->top()->xml()) . '</code></pre>';

	echo '<h3>Summaries</h3>';
	printf('<code>%s</code>', htmlspecialchars($summary_url));
	echo '<pre><code>' . htmlspecialchars($summaries->top()->xml()) . '</code></pre>';
} catch (Exception $e) {
	echo $e->getMessage();
	exit(1);
}

/**
 * Make a GET request with cURL and return the body.
 *
 * A public API will occasionally ask you to slow down - E-utilities answers with
 * a 429 when you exceed its request rate, and a 503 when it is briefly
 * unavailable. Both are worth waiting out rather than treating as a failure,
 * particularly from a shared address where somebody else may have used up the
 * budget already.
 *
 * @param string $url
 * @param int    $attempts How many times to try before giving up.
 *
 * @return string
 * @throws RuntimeException
 */
function get($url, $attempts = 4)
{
	$ch = curl_init();

	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_HEADER => 0,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_USERAGENT => 'QueryPath/4.1 ( https://github.com/GravityPDF/querypath )',
	]);

	$status = 0;

	for ($attempt = 1; $attempt <= $attempts; $attempt++) {
		if ($attempt > 1) {
			/* Wait a little longer before each retry. */
			sleep(($attempt - 1) * 2);
		}

		$body = curl_exec($ch);

		if ($body === false) {
			$error = curl_error($ch);
			curl_close($ch);

			throw new RuntimeException($error);
		}

		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($status === 200) {
			curl_close($ch);

			return $body;
		}

		/* Anything else is a real failure - there is no point retrying it. */
		if ($status !== 429 && $status !== 503) {
			break;
		}
	}

	curl_close($ch);

	throw new RuntimeException(sprintf('%s returned HTTP %d', $url, $status));
}
