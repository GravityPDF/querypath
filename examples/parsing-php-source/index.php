<?php
/**
 * Parsing a PHP template with QueryPath
 *
 * Any well-formed XML or HTML document can be parsed by QueryPath - and that
 * includes a PHP template. As far as an XML parser is concerned a PHP block is a
 * processing instruction, so a template made up of markup with PHP blocks inside
 * it parses cleanly into a DOM.
 *
 * That means QueryPath can read, traverse, and rewrite PHP templates the same
 * way it handles any other document. This example parses `template.php` and
 * reports on both its markup and its PHP blocks.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.php.net/manual/en/language.basic-syntax.phpmode.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

echo '<h1>Parsing a PHP template</h1>';

try {
	/*
	 * Parse the template. htmlqp() is used rather than qp() because the file is
	 * HTML rather than well-formed XML.
	 *
	 * Note that the file is read, not executed - QueryPath sees the PHP source
	 * exactly as it sits on disk.
	 */
	$template = htmlqp(__DIR__ . '/template.php');

	echo '<h2>Reading the markup</h2>';

	printf(
		'<p>The template is titled <strong>%s</strong> and has <strong>%d</strong> list item(s).</p>',
		htmlspecialchars($template->find('title')->text()),
		$template->top()->find('#menu li')->count()
	);

	/*
	 * PHP blocks survive parsing as processing instruction nodes, so they can be
	 * located with XPath and inspected like any other node.
	 *
	 * The `data` property of the node holds the PHP source. QueryPath normalises
	 * it on load, so the closing "?" of the "?>" is never part of the data no
	 * matter which parser read the document.
	 */
	echo '<h2>The PHP blocks in the template</h2>';

	$blocks = $template->top()->xpath('//processing-instruction()');

	echo '<ol>';

	foreach ($blocks as $block) {
		$code = trim($block->get(0)->data);

		echo '<li><code>' . htmlspecialchars($code) . '</code></li>';
	}

	echo '</ol>';

	/*
	 * Because it is a normal DOM, the template can be rewritten too. Here a new
	 * menu item is added and the heading is retitled.
	 *
	 * Every serializer restores the closing "?>" of a PHP block, so a template
	 * can be written back out in whichever format suits: writeHTML(), writeXML(),
	 * and writeHTML5() print it, and html(), xml(), and html5() return it as a
	 * string. Capturing writeHTML() with an output buffer, as below, makes it
	 * easy to send the result somewhere other than standard output, such as back
	 * to disk with file_put_contents().
	 */
	echo '<h2>Rewriting the template</h2>';

	ob_start();

	$template->top()
		->find('#menu')
		->append('<li><a href="/contact">Contact</a></li>')
		->top()
		->find('h1')
		->text('A rewritten template')
		->top()
		->writeHTML();

	$rewritten = ob_get_clean();

	echo '<pre><code>' . htmlspecialchars(trim($rewritten)) . '</code></pre>';
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}
