<?php
/**
 * Do an XML lookup from musicbrainz.org and parse/filter the results.
 *
 * This example shows how to make a simple REST-style request against a remote
 * server.
 *
 * This does two HTTP requests - one to get information about a band, and another
 * to get a list of albums put out by that band.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://musicbrainz.org
 */
require_once __DIR__ . '/../../vendor/autoload.php';

$artist_name = 'U2';
$artist_url = 'https://musicbrainz.org/ws/2/artist/?query=' . rawurlencode($artist_name);
$album_url = 'https://musicbrainz.org/ws/2/release-group?&artist=';

try {
	/* Make a remote cURL request to get the XML */
	$artist_xml = get($artist_url);

	/* Load the XML into QueryPath and select the first artist in the list */
	$artist = qp($artist_xml, 'artist:first');

	/* Check if nothing in the XML matched the selector */
	if (count($artist) === 0) {
		echo '<h1>No results found</h1>';
		exit;
	}

	/* Get direct children of "artist", filter by the "name" tag, and output the text */
	echo sprintf('<h1>Albums by <em>%s</em></h1>', $artist->children('name')->text());

	/* Get the unique albums listed for this artist */
	$id = $artist->attr('id');
	$album_url .= rawurlencode($id);
	$albums_xml = get($album_url);

	/* Load the XML into QueryPath */
	$albums = qp($albums_xml, 'release-group');

	/* Loop over the results */
	echo '<ol>';
	foreach ($albums as $album) {
		echo sprintf(
			'<li>%1$s (%2$s)</li>',
			$album->find('title')->text(),
			$album->find('first-release-date')->text()
		);
	}
	echo '</ol>';

	/* The XML retrieved via cURL and fed into QueryPath */
	echo '<h2>XML</h2>';

	echo '<h3>Artists</h3>';
	echo sprintf('<code>%s</code>', $artist_url);
	echo '<pre><code>';
	echo htmlspecialchars($artist->top()->xml());
	echo '</code></pre>';

	echo '<h3>Albums</h3>';
	echo sprintf('<code>%s</code>', $album_url);
	echo '<pre><code>';
	echo htmlspecialchars($albums->top()->xml());
	echo '</code></pre>';

} catch (Exception $e) {
	echo $e->getMessage();
}

/**
 * Make a GET request using cURL and return the results
 *
 * @param string $url
 * @return string
 */
function get($url)
{
	$defaults = array(
		CURLOPT_URL => $url,
		CURLOPT_HEADER => 0,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_USERAGENT => 'QueryPath/3.0'
	);

	$ch = curl_init();

	curl_setopt_array($ch, $defaults);

	if (!$result = curl_exec($ch)) {
		throw new RuntimeException(curl_error($ch));
	}

	curl_close($ch);

	return $result;
}