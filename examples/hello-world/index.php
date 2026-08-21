<?php
/**
 * Hello World
 *
 * The smallest useful QueryPath program, and a good place to start.
 *
 * Three functions do most of the work in QueryPath, and all three return a
 * `\QueryPath\DOMQuery`:
 *
 *   qp()       parses XML and XHTML with libxml
 *   htmlqp()   parses legacy (pre-HTML5) HTML with libxml
 *   html5qp()  parses HTML5 with masterminds/html5 - the recommended choice for HTML
 *
 * From there the API mirrors jQuery: `find()` selects, methods like `text()` and
 * `attr()` read or change the selection, and every call returns a QueryPath so
 * calls can be chained.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://github.com/GravityPDF/querypath
 */

require_once __DIR__ . '/../../vendor/autoload.php';

try {
	/*
	 * QueryPath ships with an empty HTML5 document to build on:
	 * \QueryPath\QueryPath::HTML5_STUB. Parse it, select the <body>, set some
	 * text, and print the result.
	 *
	 * writeHTML5() prints the whole document. Use html5() instead if you want
	 * the markup returned as a string.
	 */
	html5qp(\QueryPath\QueryPath::HTML5_STUB)
		->find('body')
		->text('Hello World')
		->writeHTML5();

	/*
	 * Reading is just as short. Pass a markup string (or a file path, or a URL)
	 * to the factory, select what you want, and pull the value out.
	 */
	$html = '<ul id="cast">
		<li class="name">John</li>
		<li class="name">Paul</li>
		<li class="name">George</li>
		<li class="name">Ringo</li>
	</ul>';

	echo PHP_EOL;

	/* text() on a single match returns that element's text. */
	echo 'First name:  ' . html5qp($html)->find('#cast .name')->eq(0)->text() . PHP_EOL;

	/* count() reports how many elements matched the selector. */
	echo 'How many:    ' . html5qp($html)->find('#cast .name')->count() . PHP_EOL;

	/* textImplode() joins the text of every match with a separator. */
	echo 'Everyone:    ' . html5qp($html)->find('#cast .name')->textImplode(', ') . PHP_EOL;

	/*
	 * Changing a document is the same idea. Here every name gets a class, and
	 * the modified list is returned as a string rather than printed.
	 */
	echo PHP_EOL . html5qp($html)
		->find('#cast .name')
		->addClass('beatle')
		->parents('#cast')
		->html() . PHP_EOL;
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}
