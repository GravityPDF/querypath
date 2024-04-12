<?php
/**
 * Filtering by Text Content.
 *
 * This example shows how to filter and match HTML by its content
 * The `:contains()` pseudo-class performs a substring match, and it
 * a simple way to achieve this.
 *
 * For more powerful filtering, you can pass your own callback to `filterCallback()`
 * to determine if an item should be kept in the QueryPath matches, so it can be
 * manipulated further, or excluded.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

try {
	$qp = html5qp('https://www.php.net');

	echo '<h1>Filtering Content</h1>';
	echo '<h2>PHP Releases</h2>';

	/* Get any posts containing the word 'Release' */
	echo $qp->find('h2.newstitle a:contains(Release)')
		->textImplode('<br>' . PHP_EOL);

	echo '<h2>PHP news in the past 30 days...</h2>';

	echo $qp->find('header.title')
		->filterCallback(function ($index, $item) {
			/*
			 * Returns TRUE to keep current $item in matches, or FALSE to remove
			 *
			 * $item is a DOMNode (actually, a DOMElement). So if we wanted to do QueryPath
			 * manipulations on it, you can pass it to html5qp()
			 */

			/* Get the current post datetime */
			$datetime = new DateTimeImmutable(html5qp($item, 'time')->attr('datetime'));

			/* Keep any posts less than 30 days old */
			return $datetime > (new DateTimeImmutable('-30 days'));
		})
		->find('a')
		->textImplode('<br>' . PHP_EOL);
} catch (\QueryPath\Exception $e) {
	die($e->getMessage());
}
