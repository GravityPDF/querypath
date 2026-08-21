# QueryPath QuickStart

A short guide to getting started with QueryPath.

## Installing

QueryPath is installed with [Composer](https://getcomposer.org):

```bash
composer require gravitypdf/querypath
```

Include Composer's autoloader and you are ready to go:

```php
<?php
require 'vendor/autoload.php';

echo html5qp('https://example.com', 'title')->text();
```

The three global functions – `qp()`, `htmlqp()`, and `html5qp()` – come from
`src/qp_functions.php`, which Composer loads for you through its `files`
autoloader. There is nothing else to include.

Each function is a thin wrapper around a static factory, so the object-oriented
form is always available if you prefer it, or if a function of the same name is
already defined elsewhere in your application:

| Function                | Equivalent                     | Parser                                          |
|-------------------------|--------------------------------|-------------------------------------------------|
| `qp($source)`           | `QueryPath::with($source)`     | Guesses XML or HTML (libxml)                    |
| `htmlqp($source)`       | `QueryPath::withHTML($source)` | Legacy, pre-HTML5 HTML (libxml)                 |
| `html5qp($source)`      | `QueryPath::withHTML5($source)`| HTML5 via [masterminds/html5][html5]            |
| –                       | `QueryPath::withXML($source)`  | XML, unconditionally                            |

All of them return a `\QueryPath\DOMQuery`.

## A simple example

```php
<?php
require 'vendor/autoload.php';

echo html5qp('https://example.com', 'title')->text();
```

That one line does three things:

1. **Loads and parses a document.** QueryPath reads local files, remote URLs,
   strings of HTML or XML, `DOMDocument` and `DOMNode` objects, `SimpleXMLElement`
   objects, and other `DOMQuery` objects.
2. **Runs a selector.** The second argument is a CSS selector, the same query
   language jQuery uses. `title` is about as simple as they get – something like
   `#bar-one table > tr:odd td > a:first-of-type` works just as well. If you would
   rather use XPath, there is an `xpath()` method.
3. **Reads a value.** `text()` returns the text content of the matches, or an
   empty string when nothing matched.

From there, the API mirrors jQuery. There are methods for traversing (`find()`,
`top()`, `children()`, `next()`, `prev()`, `parents()`), for filtering (`filter()`,
`filterCallback()`, `map()`, `eq()`, `not()`), and for modifying a document
(`append()`, `prepend()`, `before()`, `after()`, `attr()`, `css()`, `addClass()`,
`text()`, `remove()`). Almost all of them return a `DOMQuery`, so calls chain.

## HTML vs XML

QueryPath originally made no distinction between HTML and XML. In practice, HTML
cannot be parsed or serialized as though it were XML, so the two now have
separate entry points:

* **`html5qp()` / `QueryPath::withHTML5()`** – parses with masterminds/html5.
  This is the recommended choice for anything that is HTML.
* **`htmlqp()` / `QueryPath::withHTML()`** – forces libxml's HTML parser and makes
  a number of adjustments to accommodate common HTML breakages. Use it for
  pre-HTML5 documents.
* **`QueryPath::withXML()`** – forces XML parsing.
* **`qp()` / `QueryPath::with()`** – inspects the document and guesses, favouring
  XML slightly. It decides from the file extension, the XML declaration, and any
  options passed in. Because it guesses, an XML *string* handed to `qp()` should
  begin with `<?xml version="1.0"?>`.

Output follows the same split: `writeHTML()`, `writeHTML5()`, and `writeXML()`
print a document, while `html()`, `html5()`, and `xml()` return it as a string.

Empty documents to build on are available as constants:
`QueryPath::HTML5_STUB`, `QueryPath::HTML_STUB`, and `QueryPath::XHTML_STUB`.

## Character encoding

XML expects UTF-8. Plenty of HTML is encoded as something else – often
ISO-8859-1 – and web servers regularly report one character set while serving
another. QueryPath tries to convert documents automatically using PHP's character
detection, but it does sometimes guess wrong. When it does, pass the encoding
explicitly in the `$options` array:

```php
<?php
htmlqp($source, 'body', ['encoding' => 'ISO-8859-1']);
```

## Remote documents

`qp()` and `htmlqp()` fetch URLs through PHP's stream wrappers, so a stream
context passed as the `context` option controls the request – headers, method,
timeouts, proxies, and so on:

```php
<?php
$context = stream_context_create([
	'http' => [
		'header' => 'User-Agent: My Application',
	],
]);

qp('https://example.com/feed.xml', 'item', ['context' => $context]);
```

`html5qp()` fetches URLs through masterminds/html5, which does not take a stream
context. To control that request, fetch the page yourself and pass the markup to
`html5qp()` as a string.

## The examples

Every example in this directory is runnable on its own:

```bash
composer install
php examples/hello-world/index.php
```

Most of them print HTML, so they also work under a web server:

```bash
php -S localhost:8000 -t examples
```

### The basics

| Example | What it covers |
|---------|----------------|
| [hello-world](hello-world/index.php) | The smallest useful program: parse, select, read, and modify. |
| [basic-manipulation-filter-and-retrieval](basic-manipulation-filter-and-retrieval/index.php) | Selecting, filtering, and manipulating HTML and XML side by side. |
| [iterating-over-matches](iterating-over-matches/index.php) | Five ways to loop over a set of matches, and how changes made in a loop stick. |
| [filtering-by-text-content](filtering-by-text-content/index.php) | `:contains()` and `filterCallback()` for matching on content rather than structure. |

### Building documents

| Example | What it covers |
|---------|----------------|
| [create-html-document](create-html-document/index.php) | Building a full HTML document in a single chain. |
| [create-xml-document](create-xml-document/index.php) | Building XML with `wrap()`, `before()`, `after()`, and friends. |
| [create-svg-document](create-svg-document/index.php) | Generating an SVG image from an XML stub. |
| [generating-rss-feed](generating-rss-feed/index.php) | Merging data into stub documents to render a feed. |

### Working with remote data

| Example | What it covers |
|---------|----------------|
| [parsing-rss-feed](parsing-rss-feed/index.php) | Fetching and parsing a remote RSS feed, with a stream context. |
| [remote-filter-and-retrieval](remote-filter-and-retrieval/index.php) | Scraping an HTML page and pulling values out of it. |
| [curl-xml-filter-and-retrieval](curl-xml-filter-and-retrieval/index.php) | Two chained REST requests with cURL, and attribute selectors over the XML. |
| [parsing-xml-from-url-and-dynamically-generating-html](parsing-xml-from-url-and-dynamically-generating-html/index.php) | Reading a remote XML API and rendering it into an HTML template. |
| [http-stream-xml-namespaces-and-linked-data](http-stream-xml-namespaces-and-linked-data/index.php) | Stream contexts, XML namespaces, and Linked Data from DBpedia. |
| [sparql-endpoint-query](sparql-endpoint-query/index.php) | Querying a SPARQL endpoint and rendering the results as a table. |

### Parsing other formats

| Example | What it covers |
|---------|----------------|
| [basic-docx-parser](basic-docx-parser/index.php) | Reading a Word `.docx` file out of its ZIP archive. |
| [basic-odt-parser](basic-odt-parser/index.php) | Reading an OpenDocument `.odt` file via the `zip://` stream wrapper. |
| [parsing-php-source](parsing-php-source/index.php) | Traversing and rewriting a PHP template, PHP blocks and all. |

Several of these call live third-party services, which change their markup,
rate-limit, and occasionally go down. An example that reaches across the network
may need its selectors adjusted from time to time; the ones that read local
fixtures will always work.

## Where to go from here

* The source is documented inline; `src/Helpers/` holds most of the jQuery-style
  methods.
* Issues and discussions live on [GitHub][repo].

[html5]: https://github.com/Masterminds/html5-php
[repo]: https://github.com/GravityPDF/querypath
