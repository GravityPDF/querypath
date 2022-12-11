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

echo '<h1>Basic HTML Usage</h1>';
echo 'The following HTML chunk will get parsed, traverse, filtered, and manipulated:';
echo '<pre><code>' . htmlspecialchars($html) . '</code></pre>';

echo '<h2>Example 1</h2>';
echo 'Add the attribute <code>class="cell"</code> to all <code>&lt;td&gt;</code> elements:';

echo '<pre><code>';

echo htmlspecialchars(
	html5qp($html, 'td')
		->attr('class', 'cell')
		->top() // return to <html> tag
		->innerHTML5() // get mark-up without <html>. Use ->html5() to return a valid HTML document (Doctype and all)
);

echo '</code></pre>';

echo '<h2>Example 2</h2>';
echo 'Use <code>html5qp($html)->find(\'#row2 > td:nth-child(2)\')->text();</code> to display the contents of the second <code>&lt;td&gt;</code> in the second <code>&lt;tr&gt;</code>: <br><strong>';

echo html5qp($html)
	->find('#row2 > td:nth-child(2)')
	->text();

echo '</strong>';

echo '<h2>Example 3</h2>';
echo 'Append another row to the HTML and output the results:';
echo '<code><pre>';

echo htmlspecialchars(
	html5qp($html, 'tr:last')
		->after("\n\n\t<tr>\n\t\t<td>seven</td>\n\t\t<td>eight</td>\n\t\t<td>nine</td>\n\t</tr>")
		->top() // return to <html> tag
		->innerHTML5() // get mark-up without <html>. Use ->html5() to return a valid HTML document (Doctype and all)
);

echo '</pre></code>';


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

echo '<h1>Basic XML Usage</h1>';
echo 'The following XML will get parsed, traverse, filtered, and manipulated:';
echo '<pre><code>' . htmlspecialchars($xml) . '</code></pre>';

echo '<h2>Example 1</h2>';
echo 'Add the attribute <code>class="item"</code> to all <code>&lt;desc&gt;</code> elements:';

echo '<pre><code>';

echo htmlspecialchars(
	qp($xml, 'desc')
		->attr('class', 'item')
		->top() // return to the <categories> tag
		->xml() // output a valid XML document. Use ->innerXML() to get the contents of <categories /> instead.
);

echo '</code></pre>';

echo '<h2>Example 2</h2>';
echo 'Use <code>qp($xml)->find(\'categories > category:nth-child(3) desc\')->text();</code> to display the contents of the third <code>&lt;desc&gt;</code>: <br><strong>';

echo qp($xml)
	->find('categories > category:nth-child(3) desc')
	->text();

echo '</strong>';

echo '<h2>Example 3</h2>';
echo 'Append another category to the XML and output the results:';
echo '<code><pre>';

echo htmlspecialchars(
	qp($xml, 'category:last')
		->after("\n\n\t<category nam=\"Appended\">\n\t\t<desc>The appended node...</desc>\n\t</category>")
		->top()
		->xml()
);

echo '</pre></code>';
