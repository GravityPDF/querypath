<?php
/**
 * Generating an RSS feed
 *
 * QueryPath is just as happy writing XML as it is reading it, which makes it a
 * convenient way to render a feed without hand-assembling strings (and without
 * having to remember to escape everything yourself - `text()` does that for you).
 *
 * The approach used here is a simple form of templating: keep a stub for the
 * channel and a stub for a single item, then merge data into a fresh copy of the
 * item stub for each record and append it to the channel.
 *
 * See the `iterating-over-matches` example for the different ways of looping
 * over a set of matches.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.rssboard.org/rss-specification
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/* The stub for the feed itself. */
$rss_stub = '<?xml version="1.0"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
	<channel>
		<title></title>
		<link></link>
		<description></description>
		<language>en</language>
		<generator>QueryPath</generator>
	</channel>
</rss>';

/* The stub for a single entry in the feed. */
$rss_item_stub = '<?xml version="1.0"?>
<item>
	<title></title>
	<link></link>
	<description></description>
	<comments></comments>
	<category></category>
	<pubDate></pubDate>
	<guid isPermaLink="false"></guid>
</item>';

/*
 * The entries to render. A nested array keeps the example self-contained; in a
 * real application this would be a database result, an API response, and so on.
 */
$items = [
	[
		'title' => 'Item 1',
		'link' => 'https://example.com/item1',
		'description' => '<strong>This has embedded <em>HTML</em></strong>',
		'comments' => 'https://example.com/item1/comments',
		'category' => 'Some Term',
		'pubDate' => date('r'),
		'guid' => '123456-789',
	],
	[
		'title' => 'Item 2',
		'link' => 'https://example.com/item2',
		'description' => '<strong>This has embedded <em>HTML</em></strong>',
		'comments' => 'https://example.com/item2/comments',
		'category' => 'Some Other Term',
		'pubDate' => date('r'),
		'guid' => '123456-790',
	],
];

try {
	/*
	 * Load the feed stub with <title> selected, fill in the channel metadata,
	 * then step back up to <channel> so items can be appended to it.
	 */
	$qp = qp($rss_stub, 'channel > title')
		->text('A QueryPath RSS Feed')
		// next() moves to the following sibling, so the fields are filled in
		// document order without re-querying each time.
		->next('link')->text('https://example.com')
		->next('description')->text('QueryPath: Find your way.')
		->parent();

	foreach ($items as $item) {
		/*
		 * Each entry gets its own QueryPath built from the item stub. The keys of
		 * $item are in the same order as the elements in the stub, so the fields
		 * can be walked with next() as above.
		 *
		 * text() encodes its argument, so the HTML in 'description' is written
		 * out as &lt;strong&gt;... - exactly what an RSS reader expects.
		 */
		$qpi = qp($rss_item_stub, 'title')->text($item['title']);

		foreach (['link', 'description', 'comments', 'category', 'pubDate', 'guid'] as $field) {
			$qpi = $qpi->next()->text($item[$field]);
		}

		/* top() returns to <item> - the whole fragment - before appending it. */
		$qp->append($qpi->top());
	}

	/*
	 * When serving this over HTTP the content type needs to be set before any
	 * output is written:
	 *
	 *     header('Content-Type: application/rss+xml');
	 */
	$qp->top()->writeXML();
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}
