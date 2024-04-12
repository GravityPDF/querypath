<?php
/**
 * Retrieving remote RSS feeds.
 *
 * This file contains an example of how QueryPath can be used
 * to retrieve and parse a remote RSS feed. If PHP is configured to allow
 * HTTP URLs for remote hosts in file manipulation functions, you can use
 * QueryPath to retrieve the remote file and parse it.
 *
 * In this example, we grab the RSS feed from remote server and
 * parse it. From there, we make a list of hyperlinks, one for each item in
 * the original feed.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 */
require_once __DIR__ . '/../../vendor/autoload.php';

// The URL of the remote RSS feed.
$remote = 'https://en.wikipedia.org/w/index.php?title=Special:NewPages&feed=rss';

try {
	// We will write the results into this document.
	$qp = html5qp(\QueryPath\QueryPath::HTML5_STUB, 'title')
		->text('New Wikipedia Pages')
		->top('body')
		->append('<h1>New Wikipedia Pages</h1>')
		->append('<ul/>')
		->children('ul');

	// Load the remote document and loop through all the items.
	foreach (qp($remote, 'channel>item') as $item) {
		// Get title and link.
		$title = $item->find('title')->text();
		$link = $item->find('link')->text();

		$list = html5qp('<li/>', 'li')
			->append('<a/>')
			->find('a')
			->attr('href', $link)
			->text($title);

		// Add it to the output document.
		$qp->append($list->top()->innerHTML5());
	}

	// Write the results.
	$qp->writeHTML5();
} catch (Exception $e) {
	die($e->getMessage());
}
