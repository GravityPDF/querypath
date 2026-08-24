# QueryPath: Find the better way

QueryPath is a jQuery-like library for working with XML and HTML(5) documents in PHP. It is stable software, [with the original library garnering 4M+ downloads](https://packagist.org/packages/querypath/querypath) since first published in 2009.

**This is a fork of a fork. The [original library](https://github.com/technosophos/querypath), and [subsequent fork](https://github.com/arthurkushman/querypath), are no longer being maintained. The aim of `gravitypdf/querypath` is to ensure the library remains compatible with the latest version of PHP, and bug free. 🧑‍💻There is still a lot of legacy code to clean up + modernize, and any assistance given is appreciated.** 

> If you are viewing this file on QueryPath GitHub repository homepage or on Packagist, please note that the default repository branch is `main` which can differ from the last stable release.

[![Latest Stable Version](http://poser.pugx.org/gravitypdf/querypath/v)](https://packagist.org/packages/gravitypdf/querypath) [![License](http://poser.pugx.org/gravitypdf/querypath/license)](https://packagist.org/packages/gravitypdf/querypath) [![codecov](https://codecov.io/gh/GravityPDF/querypath/branch/main/graph/badge.svg?token=Dsul7f36K4)](https://codecov.io/gh/GravityPDF/querypath) [![PHP Version Require](http://poser.pugx.org/gravitypdf/querypath/require/php)](https://packagist.org/packages/gravitypdf/querypath)

## Installation
``` 
composer require gravitypdf/querypath 
```

## Basic Usage

To parse HTML or XML:

```php
<?php
// Assuming you installed from Composer:
require_once __DIR__.'/vendor/autoload.php';

try {
	// Recommended: uses the masterminds/html5 library to process the HTML
	$qp = html5qp(__DIR__.'/path/to/file.html'); // load a file from disk
	$qp = html5qp('<div>You can pass a string of HTML directly to the function</div>'); // load a string
} catch (\QueryPath\Exception $e) {
	// Handle error
}

try {
	// Legacy: uses libxml to parse HTML
	$qp = htmlqp(__DIR__.'/path/to/file.html'); // load a file from disk
	$qp = htmlqp('<div>You can pass a string of HTML directly to the function</div>'); // load a string
} catch (\QueryPath\Exception $e) {
	// Handle error
}

try {
	// XML or XHTML
	$qp = qp(__DIR__.'/path/to/file.html'); // load a file from disk
	$qp = qp("<?xml version='1.0'?><hello><world/></hello>"); // load a string
} catch (\QueryPath\Exception $e) {
	// Handle error
}
```

The real power of QueryPath comes from chaining methods together. This example will generate a valid HTML5 document and output to the browser:

```php
try {
	html5qp(\QueryPath\QueryPath::HTML5_STUB, 'title')
		// Add some text to the title
		->text('Example of QueryPath.')
		// Now look for the <body> element
		->top('body')
		// Inside the body, add a title and paragraph.
		->append('<h1>This is a test page</h1><p>Test text</p>')
		// Now we select the paragraph we just created inside the body
		->children('p')
		// Add a 'class="some-class"' attribute to the paragraph
		->attr('class', 'some-class')
		// And add a style attribute, too, setting the background color.
		->css('background-color', '#eee')
		// Now go back to the paragraph again
		->parent()
		// Before the paragraph and the title, add an empty table.
		->prepend('<table id="my-table"></table>')
		// Now let's go to the table...
		->top('#my-table')
		// Add a couple of empty rows
		->append('<tr></tr><tr></tr>')
		// select the rows (both at once)
		->children()
		// Add a CSS class to both rows
		->addClass('table-row')
		// Now just get the first row (at position 0)
		->eq(0)
		// Add a table header in the first row
		->append('<th>This is the header</th>')
		// Now go to the next row
		->next()
		// Add some data to this row
		->append('<td>This is the data</td>')
		// Write it all out as HTML
		->writeHTML5();
} catch (\QueryPath\Exception $e) {
	// Handle error
}
```

You can find specific nodes, loop over the matches, and extract information about each element:

```php
try {
	$html = '
    <ul>
        <li>Foo</li>
        <li>Bar</li>
        <li>FooBar</li>
    </ul>';

	$qp = html5qp($html);
	foreach ($qp->find('li') as $li) {
		echo $li->text() .'<br>';
	}
} catch (\QueryPath\Exception $e) {
	// Handle error
}
```

See the [examples directory files](https://github.com/GravityPDF/querypath/tree/main/examples) for more usages.

## Documentation

| Page | What's in it |
|---|---|
| [Getting Started](https://github.com/GravityPDF/querypath/wiki/Getting-Started) | The three factories, chaining, and how object identity works |
| [CSS Selector Reference](https://github.com/GravityPDF/querypath/wiki/CSS-Selector-Reference) | Every supported selector, with the jQuery differences called out |
| [Parser Options](https://github.com/GravityPDF/querypath/wiki/Parser-Options) | Every option `qp()`, `htmlqp()` and `html5qp()` accept |
| [Writing Extensions](https://github.com/GravityPDF/querypath/wiki/Writing-Extensions) | Adding your own methods to the fluent API |

The full API reference:

| Page | What's in it |
|---|---|
| [API Reference](https://github.com/GravityPDF/querypath/wiki/API-Reference) | Every method, alphabetically, with a known-issues summary |
| [Traversal and Filtering](https://github.com/GravityPDF/querypath/wiki/Traversal-and-Filtering) | Choosing which elements are selected |
| [Manipulation](https://github.com/GravityPDF/querypath/wiki/Manipulation) | Changing the document |
| [Markup and Text](https://github.com/GravityPDF/querypath/wiki/Markup-and-Text) | Reading and writing content |
| [Document and Utility](https://github.com/GravityPDF/querypath/wiki/Document-and-Utility) | The match set, the DOM, options, and errors |

These pages are the source of truth and live in [`docs/`](docs) in this repository — the wiki is
generated from them, so **edit `docs/` and open a pull request** rather than editing the wiki
directly. Contributions are very welcome.

> ⚠️ The legacy manual at [querypath.org](http://querypath.org/) was generated from QueryPath 2.x
> DocBlocks by phpDocumentor. It is not built or maintained by Gravity PDF, we have no access to
> change it, and parts of it are now wrong — prefer the pages above.

## General Troubleshooting

For general questions or troubleshooting please use [Discussions](https://github.com/gravitypdf/querypath/discussions).

You can also use the [querypath tag](https://stackoverflow.com/questions/tagged/querypath) at Stack Overflow, as the StackOverflow user base is more likely to answer you in a timely manner.

## Contributing

Before submitting issues and pull requests please read [CONTRIBUTING.md](https://github.com/gravitypdf/querypath/blob/main/.github/CONTRIBUTING.md).

If opening a Pull Request ensure the linter and PHPUnit tests pass (and write a new test for the bug you are fixing):

* Lint: `composer run lint`
* PHPUnit: `vendor/bin/phpunit`
