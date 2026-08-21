<?php
/**
 * Urban Dictionary Random Word Generator
 *
 * @author  Emily Brand
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 *
 * @see https://www.urbandictionary.com/
 */
require_once __DIR__ . '/../../vendor/autoload.php';

echo '<h1>Urban Dictionary Random Word Generator</h1>';

try {
	/* Urban Dictionary paginates its front page; the first hundred always exist. */
	$page = random_int(1, 100);
	$url = 'https://www.urbandictionary.com/?page=' . $page;

	/*
	 * Identify the request with a User-Agent - many hosts, Urban Dictionary
	 * included, reject requests that do not send one.
	 *
	 * Note that html5qp() handed a URL fetches it through masterminds/html5,
	 * which does not take a stream context. Fetching the page first and passing
	 * the markup as a string keeps control of the request. (qp() and htmlqp()
	 * do accept a stream context, via the 'context' option - see the
	 * parsing-rss-feed example.)
	 */
	$context = stream_context_create([
		'http' => [
			'header' => 'User-Agent: QueryPath (+https://github.com/GravityPDF/querypath)',
		],
	]);

	// Load a random page from the Urban Dictionary
	$html = file_get_contents($url, false, $context);

	if ($html === false) {
		echo 'Could not reach the Urban Dictionary.';
		exit(1);
	}

	$qp = html5qp($html);

	/*
	 * Count the definitions on the page rather than assuming how many there are.
	 * A site can change how much it lists per page at any time, and picking an
	 * index past the end of the match set gets you an empty string rather than
	 * an error.
	 */
	$total = $qp->find('.word')->count();

	if ($total === 0) {
		echo 'Found no definitions on the page - the site markup has probably changed.';
		exit(1);
	}

	// Pick one of them at random
	$word = random_int(0, $total - 1);

	// Get the word
	printf(
		'<h2>Word %d of %d: %s</h2>',
		$word + 1,
		$total,
		$qp->top()
			->find('.word')
			->eq($word)
			->text()
	);

	// Get the definition that goes with it
	$definition = $qp->top()
		->find('.meaning')
		->eq($word)
		->text();

	/*
	 * Guard the assumption that a .word has a matching .meaning. text() returns
	 * an empty string when a selector matches nothing, so without this an
	 * unexpected page layout would look like a definition that happens to be
	 * blank rather than a broken selector.
	 */
	if (trim($definition) === '') {
		echo 'Found a word with no definition - the site markup has probably changed.';
		exit(1);
	}

	echo 'Definition: ' . $definition;

	printf('<p>Source: <a href="%1$s">%1$s</a></p>', htmlspecialchars($url));
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
} catch (Exception $e) {
	// Handle the random_int() exception
	echo $e->getMessage();
	exit(1);
}
