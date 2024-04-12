<?php
/** @file
 * Using QueryPath.
 *
 * This file contains an example of how QueryPath can be used
 * to generate web pages. Part of the design of this example is to exhibit many
 * different QueryPath functions in one long chain. All of the methods shown
 * here are fully documented in {@link \QueryPath\QueryPath}.
 *
 * The method used in this example is a typical example of how QueryPath can
 * gradually build up content.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

echo '<h1>Building a HTML Document with QueryPath</h1>';

echo 'You can use QueryPath to build complex HTML documents using a simple jQuery-like API:';

echo '<pre><code>&lt;?php 

// Begin with an HTML5 stub document and navigate to the title.
html5qp(\QueryPath\QueryPath::HTML5_STUB, "title")
	// Add text to the title
	-&gt;text("Example of QueryPath.")
	// Traverse to the root of the document, then locate the body tag
	-&gt;top("body")
	// Inside the body, add a heading and paragraph.
	-&gt;append("&lt;h1&gt;This is a test page&lt;/h1&gt;&lt;p&gt;Test text&lt;/p&gt;")
	// Select the paragraph we just created inside the body
	-&gt;children("p")
	// Add a class attribute to the paragraph
	-&gt;attr("class", "some-class")
	// And an inline style to the paragraph
	-&gt;css("background-color", "#eee")
	// Traverse back up the DOM to the body
	-&gt;parent()
	// Add an empty table to the body, before the heading
	-&gt;prepend("&lt;table id=\'my-table\'&gt;&lt;/table&gt;")
	// Now go to the table...
	-&gt;find("#my-table")
	// Add a couple of empty rows
	-&gt;append("&lt;tr&gt;&lt;/tr&gt;&lt;tr&gt;&lt;/tr&gt;")
	// select the rows (both at once)
	-&gt;children()
	// Add a CSS class to both rows
	-&gt;addClass("table-row")
	// Get the first row (at position 0)
	-&gt;eq(0)
	// Add a table header in the first row
	-&gt;append("&lt;th&gt;This is the header&lt;/th&gt;")
	// Now go to the next row
	-&gt;next()
	// Add some data to this row
	-&gt;append("&lt;td&gt;This is the data&lt;/td&gt;")
	// Traverse to the root of the document
	-&gt;top()
	// Write it all out as HTML
	-&gt;html();
';

echo '</code></pre>';

echo '<h2>Results</h2>';

try {
	echo '<pre><code>';

	echo htmlspecialchars(
	// Begin with an HTML5 stub document and navigate to the title.
		html5qp(\QueryPath\QueryPath::HTML5_STUB, 'title')
			// Add text to the title
			->text('Example of QueryPath.')
			// Traverse to the root of the document, then locate the body tag
			->top('body')
			// Inside the body, add a heading and paragraph.
			->append('<h1>This is a test page</h1><p>Test text</p>')
			// Select the paragraph we just created inside the body
			->children('p')
			// Add a class attribute to the paragraph
			->attr('class', 'some-class')
			// And an inline style to the paragraph
			->css('background-color', '#eee')
			// Traverse back up the DOM to the body
			->parent()
			// Add an empty table to the body, before the heading
			->prepend('<table id="my-table"></table>')
			// Now let's go to the table...
			->find('#my-table')
			// Add a couple of empty rows
			->append('<tr></tr><tr></tr>')
			// select the rows (both at once)
			->children()
			// Add a CSS class to both rows
			->addClass('table-row')
			// Get the first row (at position 0)
			->eq(0)
			// Add a table header in the first row
			->append('<th>This is the header</th>')
			// Now go to the next row
			->next()
			// Add some data to this row
			->append('<td>This is the data</td>')
			// Traverse to the root of the document
			->top()
			// Write it all out as HTML
			->html()
	);

	echo '</code></pre>';
} catch (\QueryPath\Exception $e) {
	die($e->getMessage());
}
