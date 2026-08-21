<?php
/**
 * Iterating over a set of matches
 *
 * A QueryPath wraps zero or more nodes, and most of its methods operate on the
 * whole set at once. Sometimes you need to work through the matches one at a
 * time instead. This example shows five ways of doing that.
 *
 * Keep in mind that PHP hands objects around by handle, so a change made to an
 * element inside a loop is reflected in the QueryPath it came from. There is no
 * need to put anything back afterwards.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL (The GNU Lesser GPL) or an MIT-like license.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$xml = '<?xml version="1.0"?>
<data>
	<li>One</li>
	<li>Two</li>
	<li>Three</li>
	<li>Four</li>
	<li>Five</li>
</data>';

try {
	$qp = qp($xml, 'li');

	/*
	 * 1. foreach over the QueryPath itself.
	 *
	 * QueryPath is iterable, and yields a new DOMQuery for each match, so the
	 * full API is available inside the loop. This is usually what you want.
	 */
	echo '1. foreach over the QueryPath' . PHP_EOL;

	foreach ($qp as $item) {
		echo '   ' . $item->tag() . ': ' . $item->text() . PHP_EOL;
	}

	/*
	 * 2. foreach over the raw DOM nodes.
	 *
	 * get() with no arguments returns the underlying DOMElement objects. Use
	 * this when you want to drop down to the DOM API and skip the QueryPath
	 * wrapper entirely.
	 */
	echo PHP_EOL . '2. foreach over the DOM nodes' . PHP_EOL;

	foreach ($qp->get() as $element) {
		echo '   ' . $element->tagName . ': ' . $element->textContent . PHP_EOL;
	}

	/*
	 * 3. each() with a closure.
	 *
	 * The callback receives the index and the DOMElement, and returning false
	 * stops the loop early - handy when you are searching rather than visiting.
	 * each() returns the same QueryPath, so it can be used mid-chain.
	 */
	echo PHP_EOL . '3. each() with a closure' . PHP_EOL;

	$qp->each(function ($index, $element) {
		echo '   ' . $index . ' => ' . $element->textContent . PHP_EOL;
	});

	/*
	 * 4. each() with a named function.
	 *
	 * Anything PHP accepts as a callable works, so a function name or an
	 * [$object, 'method'] pair can be reused across several calls.
	 */
	echo PHP_EOL . '4. each() with a named function' . PHP_EOL;

	$qp->each('printItem');

	/*
	 * 5. Index-based access.
	 *
	 * count() reports how many matches there are, and get($i) returns the DOM
	 * node at that position. Use eq($i) instead if you want a QueryPath back.
	 */
	echo PHP_EOL . '5. By index' . PHP_EOL;

	for ($i = 0; $i < $qp->count(); $i++) {
		echo '   ' . $qp->get($i)->textContent . PHP_EOL;
	}

	/*
	 * Changes made during a loop stick, because the loop is working on the
	 * document itself rather than on a copy of it.
	 */
	echo PHP_EOL . 'Matches can be modified in place:' . PHP_EOL;

	$qp->each(function ($index, $element) {
		$element->setAttribute('data-position', $index + 1);
	});

	echo $qp->top()->xml();
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}

/**
 * Print a single match. Used by the each() example above.
 *
 * @param int         $index
 * @param \DOMElement $element
 *
 * @return void
 */
function printItem($index, $element)
{
	echo '   ' . $index . ' => ' . $element->textContent . PHP_EOL;
}
