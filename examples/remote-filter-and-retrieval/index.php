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
	$page = random_int(0, 288);
	$word = random_int(0, 7);

	// Load a random page from the Urban Dictionary
	$qp = html5qp('https://www.urbandictionary.com/?page=' . $page);

	// Select a random word/definition out of the 7 shown on the loaded page
	// Get the Word
	printf(
		'<h2>Word: %s</h2>',
		$qp->find('.word')
			->eq($word)
			->text()
	);

	$qp->top();

	// Get the definition
	echo 'Definition: ' .
		$qp->find('.meaning')
			->eq($word)
			->text();
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	die($e->getMessage());
} catch (Exception $e) {
	// Handle the random_int() exception
	die($e->getMessage());
}
