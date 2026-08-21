<?php
/**
 * Open Document Text (ODT) Parser
 *
 * The ODT format is the standard way of representing word processing documents
 * produced by LibreOffice, OpenOffice, and friends. An .odt file is a ZIP archive,
 * and the document text lives inside it as an XML file called `content.xml`.
 * Styles and other metadata are stored in sibling XML files in the same archive.
 *
 * Because PHP ships with a `zip://` stream wrapper, QueryPath can be pointed
 * straight at a file inside the archive - there is no need to extract it first.
 *
 * ODT is heavily namespaced (`text:h`, `text:list`, `text:p`, ...). In a CSS
 * selector the namespace separator is a pipe rather than a colon, so `text:h`
 * is selected with `text|h`.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.php.net/manual/en/wrappers.compression.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

echo '<h1>Parsing an Open Document Text file</h1>';

echo '<p>This example reads <code>example.odt</code> and rebuilds its outline, bullet list, and ordered list.</p>';

try {
	/*
	 * Point QueryPath at content.xml inside the ZIP archive.
	 *
	 * The zip:// wrapper takes the form zip://<path to archive>#<path inside archive>
	 */
	$doc = qp('zip://' . __DIR__ . '/example.odt#content.xml');

	/*
	 * Build the document outline.
	 *
	 * Every heading is a <text:h> element, and its depth is recorded in the
	 * text:outline-level attribute. Namespaced attributes use the same pipe
	 * syntax as elements.
	 */
	echo '<h2>Outline</h2>';
	echo '<pre>';

	foreach ($doc->find('text|h') as $header) {
		$level = (int) $header->attr('text:outline-level');

		echo str_repeat('    ', max($level - 1, 0)) . '- ' . htmlspecialchars($header->text()) . PHP_EOL;
	}

	echo '</pre>';

	/*
	 * ODT does not mark up bullet and numbered lists differently - both are a
	 * <text:list>. What separates them is the list style applied to it, so we
	 * match on the text:style-name attribute.
	 *
	 * Each item is a <text:list-item> wrapping a <text:p>.
	 */
	echo '<h2>Bullet list</h2>';
	echo '<ul>';

	foreach ($doc->top()->find('text|list[text|style-name="L1"] text|list-item text|p') as $item) {
		echo '<li>' . htmlspecialchars($item->text()) . '</li>';
	}

	echo '</ul>';

	echo '<h2>Ordered list</h2>';
	echo '<ol>';

	foreach ($doc->top()->find('text|list[text|style-name="L2"] text|list-item text|p') as $item) {
		echo '<li>' . htmlspecialchars($item->text()) . '</li>';
	}

	echo '</ol>';

	/*
	 * Body copy is stored in <text:p> elements. Skipping the empty ones keeps
	 * the blank "spacer" paragraphs a word processor leaves behind out of the way.
	 */
	echo '<h2>Body copy</h2>';

	foreach ($doc->top()->find('office|text > text|p') as $paragraph) {
		$text = trim($paragraph->text());

		if ($text === '') {
			continue;
		}

		echo '<p>' . htmlspecialchars($text) . '</p>';
	}
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}
