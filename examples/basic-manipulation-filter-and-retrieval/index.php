<?php

require_once __DIR__ . '/../../vendor/autoload.php';

/*
 * HTML Example
 */
$html = <<<EOF
<table>
	<tr id="row1">
		<td>one</td>
		<td>two</td>
		<td>three</td>
	</tr>
	
	<tr id="row2">
		<td>four</td>
		<td>five</td>
		<td>six</td>
	</tr>
</table>
EOF;

/*
 * XML Example
 */
$xml = <<<EOF
<?xml version="1.0"?>
<categories>
	<category name="DOM">
		<desc>This is the DOM description...</desc>
	</category>
	
	<category name="Traversing">
		<desc>This is the Traversing description...</desc>
	</category>
	
	<category name="Filtering">
		<desc>This is the Filtering description...</desc>
	</category>
	
	<category name="Selectors">
		<desc>This is the Selectors description...</desc>
	</category>
</categories>
EOF;

try {
	echo '<h1>Basic HTML Usage</h1>';
	echo 'The following HTML chunk will get parsed, traverse, filtered, and manipulated:';
	echo '<pre><code>' . htmlspecialchars($html) . '</code></pre>';

	echo '<h2>Example 1</h2>';
	echo 'Add the attribute <code>class="cell"</code> to all <code>&lt;td&gt;</code> elements:';

	echo '<pre><code>
&lt;?php 

echo html5qp($html, "td")
-&gt;attr("class", "cell")
-&gt;top("table")
-&gt;html()  
</code></pre>';

	echo 'This will output the following HTML:';

	echo '<pre><code>';

	echo htmlspecialchars(
		html5qp($html, 'td')
			->attr('class', 'cell')
			->top('table') // jump back up the DOM to the table
			->html() // get get HTML of the table
	);

	echo '</code></pre>';

	echo 'If you want to output a valid HTML document, use <code>top()</code> without an argument:';

	echo '<pre><code>';

	echo htmlspecialchars(
		html5qp($html, 'td')
			->attr('class', 'cell')
			->top()
			->html()
	);

	echo '</code></pre>';

	echo '<h2>Example 2</h2>';
	echo 'Find and output the text of the second cell in the second row of the table:';

	$text = html5qp($html)
		->find('#row2 > td:nth-child(2)')
		->text();

	echo '<pre><code>
&lt;?php 

echo html5qp($html)
-&gt;find("#row2 > td:nth-child(2)")
-&gt;text();

// Result: '. $text. '
</code></pre>';

	echo '<h2>Example 3</h2>';
	echo 'Append an additional row at the end of the table:';
	echo '<pre><code>
&lt;?php 

echo html5qp($html, "td")
-&gt;after("&lt;tr&gt;&lt;td&gt;seven&lt;/td&gt;&lt;td&gt;eight&lt;/td&gt;&lt;td&gt;nine&lt;/td&gt;&lt;/tr&gt;")
-&gt;top("table")
-&gt;html()
</code></pre>';

	echo 'This will output the following HTML:';

	echo '<code><pre>';

	echo htmlspecialchars(
		html5qp($html, 'tr:last')
			->after("\n\n\t<tr>\n\t\t<td>seven</td>\n\t\t<td>eight</td>\n\t\t<td>nine</td>\n\t</tr>")
			->top("table")
			->html()
	);

	echo '</pre></code>';

	echo '<h1>Basic XML Usage</h1>';
	echo 'The following XML will get parsed, traverse, filtered, and manipulated:';
	echo '<pre><code>' . htmlspecialchars($xml) . '</code></pre>';

	echo '<h2>Example 1</h2>';
	echo 'Add the attribute <code>class="item"</code> to all <code>&lt;desc&gt;</code> elements:';

	echo '<pre><code>
&lt;?php 

echo qp($xml, "desc")
-&gt;attr("class", "item)
-&gt;top() // return to the &lt;categories&gt; tag
-&gt;xml(); // output a valid XML document.
</code></pre>';

	echo 'This will output the following XML:';

	echo '<pre><code>';

	echo htmlspecialchars(
		qp($xml, 'desc')
			->attr('class', 'item')
			->top() // return to the <categories> tag
			->xml() // output a valid XML document
	);

	echo '</code></pre>';

	echo 'You can omit the XML declaration by setting the first argument to true: <code>-&gt;xml(true)</code>.';

	echo '<h2>Example 2</h2>';
	echo 'Find and output the text of the third <code>&lt;desc&gt;</code> tag:';

	$text = qp($xml)
		->find('categories > category:nth-child(3) desc')
		->text();

	echo '<pre><code>
&lt;?php 

echo qp($xml)
-&gt;find("categories > category:nth-child(3) desc")
-&gt;text();
 
 // Result: '.$text.'
</code></pre>';

	echo '<h2>Example 3</h2>';
	echo 'Append a category at the end of the group:';
	echo '<pre><code>
&lt;?php 

echo qp($xml, "category:last")
-&gt;after("&lt;category name=\'Appended\'&gt;&lt;desc&gt;The appended node...&lt;/desc&gt;&lt;/category&gt;")
-&gt;top()
-&gt;xml()
</code></pre>';

	echo 'This will output the following HTML:';

	echo '<code><pre>';

	echo htmlspecialchars(
		qp($xml, 'category:last')
			->after("\n\n\t<category name=\"Appended\">\n\t\t<desc>The appended node...</desc>\n\t</category>")
			->top()
			->xml()
	);

	echo '</pre></code>';
} catch (\QueryPath\Exception $e) {
	// Handle QueryPath exceptions
	die($e->getMessage());
}
