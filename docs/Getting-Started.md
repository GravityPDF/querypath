# Getting Started

QueryPath is a PHP library for working with XML and HTML documents, modelled on jQuery's traversal
and manipulation API.

```bash
composer require gravitypdf/querypath
```

## The three factories

QueryPath has three global functions, each backed by a static method. Which one you want depends on
what you are parsing.

| Function | Equivalent to | Parser | Use for |
|---|---|---|---|
| `html5qp()` | `QueryPath::withHTML5()` | `masterminds/html5` | **HTML — recommended** |
| `htmlqp()` | `QueryPath::withHTML()` | libxml | Legacy HTML, when you need libxml's behaviour |
| `qp()` | `QueryPath::with()` | libxml | XML and XHTML |

All three return a `QueryPath\DOMQuery`, and all three accept a file path, a URL, a markup string,
or an existing DOM object.

```php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $qp = html5qp(__DIR__ . '/page.html');                  // a file
    $qp = html5qp('https://example.com/page.html');         // a URL
    $qp = html5qp('<div>markup passed directly</div>');     // a string
} catch (\QueryPath\Exception $e) {
    // every QueryPath throwable descends from this
}
```

`qp()` additionally accepts a `DOMDocument`, a `DOMNode`, a `SimpleXMLElement`, an array of
`DOMNode`s, or another `DOMQuery`.

> Catch `\QueryPath\Exception`. Every exception the library throws — parse errors, IO errors,
> selector errors — descends from it.

## A first query

```php
$html = '<ul><li>Foo</li><li>Bar</li><li>FooBar</li></ul>';

foreach (html5qp($html)->find('li') as $li) {
    echo $li->text(), "\n";
}
```

Iterating a `DOMQuery` yields `DOMQuery` objects, not raw `DOMNode`s, so the full API is available
on each item.

## Chaining

Most methods return a `DOMQuery`, so calls chain:

```php
echo html5qp(QueryPath::HTML5_STUB, 'body')
    ->append('<h1>Title</h1>')
    ->addClass('body-class')
    ->top()
    ->html5();
```

## Objects are not mutated in place

This is the most important thing to understand, and it is where older QueryPath documentation is
wrong.

**`find()` returns a new object. The object you called it on is left alone.**

```php
$qp    = html5qp('<div><p>one</p><p>two</p></div>');
$found = $qp->find('p');

$found->count();   // 2 — the paragraphs
$qp->count();      // 1 — still the original match set, unchanged
$found === $qp;    // false
```

If you want the mutating behaviour, use `findInPlace()`:

```php
$qp = html5qp('<div><p>one</p><p>two</p></div>');
$qp->findInPlace('p');

$qp->count();      // 2 — $qp itself now holds the paragraphs
```

> Documentation predating QueryPath 3 states that "QueryPath does not return a new object for each
> call… the same object is mutated from call to call." That has not been true for a long time.
> `find()` is the non-mutating variant; `findInPlace()` is the mutating one.

### Stepping back with `end()`

Because each call produces a new object, `end()` can return you to the previous match set:

```php
$qp->find('p')->addClass('para')->end();   // back to the match set before find('p')
```

### Working with two sets at once via `branch()`

`branch()` clones the object so you can hold on to two positions in the same document:

```php
$body    = html5qp($html, 'body');
$paras   = $body->branch('p');   // a second object, searching body for p tags
```

## Where QueryPath differs from jQuery

- **The jQuery-style positional pseudo-classes are 1-indexed**, where jQuery's are 0-indexed.
  `:eq(1)` is the first element, and `:eq(0)` matches nothing. See the
  [CSS Selector Reference](CSS-Selector-Reference.md#position-and-counting).
- QueryPath adds methods jQuery has no need for — `top()`, `dataURL()`, `writeXML()`,
  `filterPreg()`, `xpath()` — and omits everything to do with events, effects, and Ajax.
- Some CSS pseudo-classes cannot mean anything without a browser (`:hover`, `:visited`,
  `:target`, …). They parse, and match nothing.

## Next steps

- [CSS Selector Reference](CSS-Selector-Reference.md) — every supported selector, with the gotchas
- [Parser Options](Parser-Options.md) — every option the factories accept
- [Writing Extensions](Writing-Extensions.md) — adding your own methods to the fluent API
- The `examples/` directory in the repository holds runnable scripts for each of the above
