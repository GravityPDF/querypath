# Traversal and Filtering

Methods that change **which elements are selected**, without changing the document.

Every method on this page except `each()` and `findInPlace()` returns a **new** `DOMQuery` and leaves
the object you called it on untouched. See [Getting Started](Getting-Started.md#objects-are-not-mutated-in-place).

## Contents

- [Finding](#finding) — `find()`, `findInPlace()`, `xpath()`, `top()`, `branch()`
- [Filtering a set](#filtering-a-set) — `filter()`, `not()`, `is()`, `has()`, `filterPreg()`, `filterCallback()`, `map()`
- [Picking by position](#picking-by-position) — `eq()`, `first()`, `last()`, `slice()`, `odd()`, `even()`
- [Moving up](#moving-up) — `parent()`, `parents()`, `parentsUntil()`, `closest()`
- [Moving down](#moving-down) — `children()`, `contents()`, `firstChild()`, `lastChild()`, `deepest()`
- [Moving sideways](#moving-sideways) — `next()`, `prev()`, `nextAll()`, `prevAll()`, `nextUntil()`, `prevUntil()`, `siblings()`
- [Iterating](#iterating) — `each()`, `foreach`
- [Going back](#going-back) — `end()`, `andSelf()`
- [Deprecated](#deprecated)

## Finding

### `find()`

Search the descendants of every currently selected element.

```php
$qp->find('div.content p');
```

**Returns** a new `DOMQuery`. Selector syntax is documented in the
[CSS Selector Reference](CSS-Selector-Reference.md).

If `find()` matches nothing, a further `find()` on the result also matches nothing — there are no
elements left to search from. Use [`top()`](#top) to get back to the document.

### `findInPlace()`

Identical to `find()`, but replaces the match set **on the current object** instead of returning a
new one.

**Returns** `$this`. Use this when you are working with one long-lived object and do not need
`end()` to step back.

### `xpath()`

Run an XPath 1.0 query relative to each selected element.

```php
$qp->xpath('//atom:entry', [
    'namespace_prefix' => 'atom',
    'namespace_uri'    => 'http://www.w3.org/2005/Atom',
]);
```

`namespace_prefix` and `namespace_uri` register a single namespace for the query. They are options
to *this method*, not constructor options.

**Returns** a new `DOMQuery`. XPath can select attributes, text nodes and comments as well as
elements; the non-element results are reachable through [`get()`](Document-and-Utility.md#get) but
most other methods expect elements.

### `top()`

Select the document's root element, optionally running a query from there.

```php
$qp->find('p')->top();          // back to the document element
$qp->find('p')->top('h1');      // start a fresh query at the root
```

**Returns** a new `DOMQuery`. This is the reliable way to recover after a query that matched
nothing — unlike `find(':root')`, it does not need an existing element to work from.

### `branch()`

Copy the `DOMQuery` object while keeping it pointed at the same nodes in the same document.

```php
$body  = html5qp($html, 'body');
$paras = $body->branch('p');    // a second cursor into the same document

$paras->addClass('para');       // $body sees the change; its match set is unaffected
```

**Returns** a new `DOMQuery` sharing the underlying `DOMDocument`. Contrast with
[`cloneAll()`](Manipulation.md#cloneall), which copies the *nodes*.

## Filtering a set

### `filter()`

Keep only the currently selected elements that match the selector. Unlike `find()`, this does not
descend — it tests the elements you already have.

**Returns** a new `DOMQuery`.

### `not()`

The inverse of `filter()`: drop the elements that match.

```php
$qp->find('li')->not('.hidden');
```

Accepts a CSS selector, a `DOMElement`, or an array of `DOMNode`s.

**Returns** a new `DOMQuery`.

> **Known issue.** Passing an `SplObjectStorage` inverts the test: instead of removing those nodes,
> `not()` keeps *only* those nodes. Pass an array (`$other->get()`) rather than
> `$other->get(null, true)` until this is fixed.

### `is()`

Test whether any selected element matches.

```php
if ($qp->find('input')->is('[required]')) { … }
```

Also accepts a `DOMNode` (true when the set is exactly that one node) or a `Traversable` of nodes
(true when the sets contain the same nodes).

**Returns** `bool`.

### `has()`

Keep only the elements that contain something matching — the selector is tested against
*descendants*.

```php
$qp->find('li')->has('a');      // only list items containing a link
```

Accepts a selector or a `DOMNode`. Unlike jQuery, any node type counts, not just elements.

**Returns** a new `DOMQuery`. (The docblock in older releases claimed this modified the object in
place. It does not.)

### `filterPreg()`

Keep elements whose **text content** matches a PCRE pattern.

```php
$qp->find('div')->filterPreg('/World/');
```

The pattern is matched against the element's full text content, including the text of its
descendants — so a `<div>` containing `<i>World</i>` matches. This is the same content the
`:contains()` pseudo-class inspects.

**Returns** a new `DOMQuery`.

### `filterCallback()`

Keep elements for which the callback does not return `false`.

```php
$qp->find('li')->filterCallback(function ($index, DOMNode $item) {
    return strlen($item->textContent) > 3;
});
```

The callback receives `($index, $item)`. Only a strict `false` removes the element; `null` and other
falsey values keep it.

**Returns** a new `DOMQuery`. Throws `QueryPath\Exception` if the callback is not callable.

### `map()`

Replace the match set with whatever the callback returns.

```php
$qp->find('li')->map(fn($i, $item) => $item->parentNode);
```

Per return value:

| Callback returns | Effect |
|---|---|
| `null` | The item is dropped |
| An array or iterable | Every member is added to the set |
| A `DOMNode` | Added to the set |
| A scalar | Wrapped in a `stdClass` with a `textContent` property, then added |

**Returns** a new `DOMQuery`. Because the set may now hold non-DOM values, most other methods will
not work on the result — use it as a final step, or follow it with
[`get()`](Document-and-Utility.md#get). Throws `QueryPath\Exception` if the callback is not callable.

## Picking by position

> These methods are **0-indexed**, while the equivalent CSS pseudo-classes (`:eq()`, `:lt()`,
> `:gt()`, `:nth-child()`) are **1-indexed**. `->eq(0)` and `:eq(1)` select the same element. See
> [Position and counting](CSS-Selector-Reference.md#position-and-counting).

### `eq()`

Reduce the set to the single element at `$index` (0-based). Out of range gives an empty set.

**Returns** a new `DOMQuery`.

### `first()`

Reduce the set to its first element.

**Returns** a new `DOMQuery`.

### `last()`

Reduce the set to its last element.

**Returns** a new `DOMQuery`.

### `slice()`

Take a contiguous run of the match set, like `array_slice()`. `$length` of `0` means "everything
from `$start` onward".

```php
$qp->find('li')->slice(1, 2);   // the 2nd and 3rd items
```

**Returns** a new `DOMQuery`. Negative offsets are not supported.

### `odd()`

Select the elements at even indexes — the 1st, 3rd, 5th and so on.

```php
// For a set of five elements holding 1,2,3,4,5:
$qp->odd()->textImplode(',');   // '1,3,5'  — positions 0, 2, 4
$qp->even()->textImplode(',');  // '2,4'    — positions 1, 3
```

**Returns** a new `DOMQuery`.

> The names refer to the 1-based *ordinal* ("the first, third, fifth"), not the 0-based index — so
> `odd()` returns the elements at even indexes. This is the opposite of what most callers expect;
> reach for `:nth-child(odd)` in a selector if you want the CSS meaning.

### `even()`

Select the elements at odd indexes — the 2nd, 4th, 6th and so on. See the note under
[`odd()`](#odd) about the naming.

**Returns** a new `DOMQuery`.

## Moving up

### `parent()`

With no argument, the immediate parent of each selected element.

With a selector, the **nearest ancestor that matches** — not the immediate parent, and not an empty
set when the immediate parent doesn't match.

```php
qp($xml, 'o')->parent()->tag();      // 'n' — the immediate parent
qp($xml, 'o')->parent('r')->tag();   // 'r' — the nearest matching ancestor
```

**Returns** a new `DOMQuery`.

### `parents()`

Every ancestor of every selected element, up to but excluding the document node. With a selector,
only the matching ancestors.

**Returns** a new `DOMQuery`.

### `parentsUntil()`

Ancestors, stopping *before* the first one that matches.

**Returns** a new `DOMQuery`.

### `closest()`

The nearest match in the ancestry chain, testing the element itself first, then each ancestor.

```php
$qp->find('a')->closest('.card');
```

**Returns** a new `DOMQuery`. Provided for jQuery compatibility; `parent($selector)` differs in that
it never returns the element itself.

## Moving down

### `children()`

The immediate child **elements** of each selected element, optionally filtered.

**Returns** a new `DOMQuery`.

> Prefer this over the selector `'parent > *'` — see [the note below](#child-combinator-with-the-universal-selector).

### `contents()`

All immediate child **nodes**, including text nodes, comments and CDATA sections.

```php
qp('<r>t<a/>u</r>', 'r')->contents()->count();   // 3
qp('<r>t<a/>u</r>', 'r')->children()->count();   // 1
```

**Returns** a new `DOMQuery`. Does not descend into iframes.

### `firstChild()`

The first child element of each selected element.

**Returns** a new `DOMQuery`.

> **Known issue.** `firstChild()` stops after the first selected element, so it returns at most one
> node no matter how many elements are selected. For consistent behaviour use
> `children()->first()`.

### `lastChild()`

The last child element of each selected element — one per element, unlike `firstChild()`.

**Returns** a new `DOMQuery`.

### `deepest()`

Reduce the set to the node or nodes furthest from their matched ancestor. Depth is measured from
each selected element, not from the document root, and ties are all kept.

**Returns** a new `DOMQuery`.

## Moving sideways

All seven **return a new `DOMQuery`**, and all seven take an optional selector.

### `next()`

The next sibling element. With a selector, the first following sibling that matches.

### `prev()`

The previous sibling element. With a selector, the first preceding sibling that matches.

### `nextAll()`

All following siblings, optionally filtered.

### `prevAll()`

All preceding siblings, optionally filtered.

### `nextUntil()`

Following siblings, stopping *before* the first one that matches.

### `prevUntil()`

Preceding siblings, stopping *before* the first one that matches.

### `siblings()`

All siblings of each selected element, excluding the element itself.

If two selected elements are siblings of each other, both appear in the result, because each is a
sibling of the other.

### Ordering

`prevAll()` and `prevUntil()` walk outward from the starting element, so the result is in reverse
document order:

```php
// document order is a, b, c
qp($xml, 'c')->prevAll()->textImplode(',');   // '2,1' — b then a
```

## Iterating

### `each()`

Run a callback over each selected node.

```php
$qp->find('li')->each(function ($index, DOMNode $item) {
    echo $index, ': ', $item->textContent, "\n";
});
```

The callback receives `($index, $item)` — a raw `DOMNode`, not a `DOMQuery`. Returning `false`
stops the loop, like `break`; any other return value continues.

**Returns** `$this` — one of the two methods on this page that does not create a new object.

### Iterating with `foreach`

`DOMQuery` is `Traversable`, and iterating it yields `DOMQuery` objects, so the full API is
available on each item:

```php
foreach ($qp->find('li') as $li) {
    echo $li->text(), "\n";       // $li is a DOMQuery
}
```

## Going back

### `end()`

Restore the match set from before the last destructive operation.

```php
$qp->find('p')->addClass('para')->end();   // back to the set before find('p')
```

Only **one** level of history is kept. Calling `end()` on a freshly constructed object returns the
document element, not an empty set.

**Returns** `$this`, with its match set rewound. Marked `@deprecated` in the source; it survives
because `find()` returning a new object usually makes it unnecessary.

### `andSelf()`

Merge the previous match set into the current one.

```php
$qp->find('p')->find('span')->andSelf();   // the spans and the paragraphs
```

**Returns** `$this`, mutated.

## Deprecated

### `filterLambda()`

### `eachLambda()`

String-body pseudo-lambdas from before PHP 5.3. `filterLambda($fn)` is `filterCallback()` and
`eachLambda($lambda)` is `each()`, with the callback body supplied as a string.

> **These methods are non-functional on PHP 8.0 and later.** They call `create_function()`, which
> was removed in PHP 8.0, so any call raises
> `Error: Call to undefined function QueryPath\Helpers\create_function()`. Use
> [`filterCallback()`](#filtercallback) and [`each()`](#each) with a closure.

## Child combinator with the universal selector

A selector whose right-hand side of a child combinator also matches the document element raises a
`TypeError` from the selector engine. In practice this means **`X > *` always fails**:

```php
qp($xml, 'wrap > *');    // TypeError
qp($xml, 'wrap > a');    // fine
qp($xml, 'wrap *');      // fine — descendant combinator
qp($xml, 'wrap')->children();   // fine — the intended replacement
```

Use `children()` instead. This is a library bug, not a limitation of the selector syntax.

## See also

- [Manipulation](Manipulation.md) — changing the document
- [Markup and Text](Markup-and-Text.md) — reading and writing content
- [CSS Selector Reference](CSS-Selector-Reference.md)
