<?php
/**
 * Generating a Scalable Vector Graphic (SVG)
 *
 * SVG is a W3C standard XML format for describing two-dimensional graphics.
 * Because it is just XML, QueryPath can build one the same way it builds any
 * other document - start from a stub and use the jQuery-like API to append,
 * traverse, and set attributes.
 *
 * Run this from the command line and redirect the output to a file to get a
 * viewable image:
 *
 *     php index.php > shapes.svg
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.w3.org/TR/SVG11/
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/*
 * A minimal SVG document to build on top of.
 *
 * Like every XML document handled by qp(), it begins with the XML declaration.
 */
$svg_stub = '<?xml version="1.0"?>
<svg
	xmlns="http://www.w3.org/2000/svg"
	xmlns:svg="http://www.w3.org/2000/svg"
	xmlns:xlink="http://www.w3.org/1999/xlink"
	version="1.0"
	width="800"
	height="600"
	id="example">
	<desc>Created by QueryPath.</desc>
</svg>';

try {
	qp($svg_stub)
		// The root <svg> element is selected by default, so attr() applies to it.
		// Passing an array sets several attributes in one call.
		->attr(['width' => 200, 'height' => 120])
		// Add two rectangles to the canvas.
		->append('<rect id="first"/><rect id="second"/>')
		// Select the second rectangle and position it.
		->find('#second')
		->attr(['x' => 60, 'y' => 20, 'width' => 100, 'height' => 80, 'fill' => 'red'])
		// prev() steps back to the preceding sibling - the first rectangle.
		->prev()
		->attr(['x' => 20, 'y' => 20, 'width' => 100, 'height' => 80, 'fill' => 'navy'])
		// Add a caption. Note that text() escapes its input for you.
		->top()
		->append('<text id="caption"/>')
		->find('#caption')
		->attr(['x' => 20, 'y' => 115, 'font-family' => 'sans-serif', 'font-size' => 12])
		->text('Drawn with QueryPath')
		// writeXML() prints the whole document, starting from the root.
		->top()
		->writeXML();
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	echo $e->getMessage();
	exit(1);
}
