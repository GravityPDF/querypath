# Document and Utility

Inspecting the match set, reaching the underlying DOM, and the static entry points.

## Contents

- [Counting and indexing](#counting-and-indexing) — `count()`, `size()`, `length`, `index()`, `tag()`
- [Getting nodes out](#getting-nodes-out) — `get()`, `toArray()`, `getIterator()`
- [Reaching the DOM](#reaching-the-dom) — `document()`, `ns()`, `xinclude()`, `setMatches()`
- [Sorting](#sorting) — `sort()`
- [Options](#options) — `getOptions()`, `QueryPath\Options`
- [Static entry points](#static-entry-points) — `QueryPath::with()` and friends
- [Constants](#constants) — the document stubs
- [Errors](#errors) — the exception hierarchy

## Counting and indexing

### `count()`

The number of selected nodes. `DOMQuery` implements `Countable`, so `count($qp)` works too.

**Returns** `int`.

### `size()`

A deprecated alias of `count()`.

### `length`

A public property holding the same number, refreshed whenever the match set changes.

```php
$qp->find('li')->length;    // same as ->count()
```

> The docblock on `size()` claims there is no `length` property. There is, and it is kept current.

### `index()`

The 0-based position of a given node in the match set.

```php
$i = $qp->index($someDomElement);
if ($i !== false) { … }
```

**Returns** `int`, or `false` when the node is not in the set. Because `0` is a valid answer, always
compare with `!==`.

### `tag()`

The tag name of the first selected element.

**Returns** `string` — `''` when nothing is selected.

## Getting nodes out

### `get()`

Reach the raw DOM nodes.

```php
$qp->get();          // array of DOMNode
$qp->get(2);         // the third node, or null if out of range
$qp->get(null, true); // the internal SplObjectStorage
```

**Returns** an `array`, a single `DOMNode`, `null`, or an `SplObjectStorage` when `$asObject` is
`true`. Non-destructive. The `SplObjectStorage` form is the one extensions should use.

### `toArray()`

Identical to `get()` with no arguments. Provided for jQuery 1.4 familiarity.

**Returns** `array`.

### `getIterator()`

Called for you by `foreach`. Iterating a `DOMQuery` yields **`DOMQuery` objects**, one per node, not
raw `DOMNode`s — so the whole API is available on each item.

```php
foreach ($qp->find('li') as $li) {
    echo $li->attr('id'), ': ', $li->text(), "\n";
}
```

Use `get()` when you want the `DOMNode`s themselves.

## Reaching the DOM

### `document()`

The underlying `DOMDocument`. It is shared, not copied — changes made through the DOM API are
visible to QueryPath and vice versa.

**Returns** `DOMDocument`.

### `ns()`

The namespace URI of the first selected element.

**Returns** `string`, or `null` for an element in no namespace. Throws if nothing is selected.

### `xinclude()`

Process XInclude directives in the document, by calling `DOMDocument::xinclude()`.

**Returns** `$this`.

### `setMatches()`

Replace the match set directly with an `SplObjectStorage`, array, or single node.

**Returns** `void`. This is an expert-level hook used internally and by extensions; it bypasses
every selector and consistency check. It also updates the `end()`/`andSelf()` history and the
`length` property.

## Sorting

### `sort()`

Reorder the match set with a comparator, optionally reordering the DOM to match.

```php
$comparator = function (DOMNode $a, DOMNode $b) {
    return strcmp($a->textContent, $b->textContent);
};

$sorted = $qp->find('li')->sort($comparator);        // sorts the match set only
$qp->find('li')->sort($comparator, true);            // also reorders the document
```

**Returns** a new `DOMQuery` — the object you called it on keeps its original order, despite the
docblock saying "This object".

With `$modifyDOM = true`, the sorted nodes are reinserted at the position the **first** node of the
original set occupied. If the selected elements did not all share a parent, they all end up under
that first node's parent.

## Options

### `getOptions()`

The effective options for this object, after merging the three sources.

**Returns** `array`. See [Parser Options](Parser-Options.md) for every key and the precedence rules.

### `QueryPath\Options`

Global defaults, applied to every object created afterwards.

| Method | Purpose |
|---|---|
| `Options::set(array $array)` | Replace the global defaults wholesale |
| `Options::merge(array $array)` | Merge into the existing defaults |
| `Options::get()` | The current global defaults |
| `Options::has(string $key)` | Whether a default is set for this key |

```php
\QueryPath\Options::merge(['format_output' => false]);
```

Remember the leading backslash — inside a namespace, `QueryPath\Options` resolves relative to the
current namespace.

## Static entry points

`QueryPath\QueryPath` holds the static factories. The global functions in
[Getting Started](Getting-Started.md#the-three-factories) are thin wrappers over the first three.

| Method | Notes |
|---|---|
| `QueryPath::with($document, $selector, $options)` | The general entry point; `qp()` calls this |
| `QueryPath::withXML($source, $selector, $options)` | Forces `use_parser: 'xml'` |
| `QueryPath::withHTML($source, $selector, $options)` | Legacy libxml HTML; `htmlqp()` calls this |
| `QueryPath::withHTML5($source, $selector, $options)` | `masterminds/html5`; `html5qp()` calls this |
| `QueryPath::enable($extensionNames)` | Register one extension class, or an array of them |
| `QueryPath::enabledExtensions()` | The registered class names |
| `QueryPath::encodeDataURL($data, $mime, $context)` | Build a data URL without a document |

Extension registration is covered in [Writing Extensions](Writing-Extensions.md).

## Constants

`QueryPath::HTML_STUB`, `QueryPath::HTML5_STUB` and `QueryPath::XHTML_STUB` are minimal, valid
documents to build from:

```php
html5qp(QueryPath::HTML5_STUB, 'body')
    ->append('<h1>Title</h1>')
    ->top()
    ->writeHTML5();
```

`QueryPath::VERSION` and `QueryPath::VERSION_MAJOR` also exist, but **do not reflect the installed
release** — they still read `3.2.2` / `3`. Read the version from Composer
(`composer show gravitypdf/querypath`) rather than from these constants.

The CDATA-escaping constants (`DOM::JS_CSS_ESCAPE_*`) are documented under
[`escape_xhtml_js_css_sections`](Parser-Options.md#escape_xhtml_js_css_sections).

## Errors

Every throwable in the library descends from `QueryPath\Exception`, so one catch covers the library:

```php
try {
    $qp = html5qp($source);
} catch (\QueryPath\Exception $e) {
    // parse errors, IO errors, selector errors
}
```

| Class | Raised when |
|---|---|
| `QueryPath\Exception` | The base; also thrown directly for unsupported input and bad callbacks |
| `QueryPath\ParseException` | A document fails to parse; also from `writeHTML()` on an unwritable path |
| `QueryPath\IOException` | `writeXML()` / `writeXHTML()` cannot write the file |
| `QueryPath\CSS\ParseException` | A selector cannot be parsed |
| `QueryPath\CSS\NotImplementedException` | A selector parses but the engine cannot evaluate it |

Note that `QueryPath\ParseException` and `QueryPath\CSS\ParseException` are different classes.

Two failures escape this hierarchy and surface as raw PHP errors:

- `X > *` and other child-combinator selectors that match the document element raise a `TypeError`
  (see [Traversal and Filtering](Traversal-and-Filtering.md#child-combinator-with-the-universal-selector))
- `filterLambda()` and `eachLambda()` raise an `Error` on PHP 8, having been built on the removed
  `create_function()`

## See also

- [Parser Options](Parser-Options.md)
- [Writing Extensions](Writing-Extensions.md)
- [API Reference](API-Reference.md) — every method, alphabetically
