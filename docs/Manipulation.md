# Manipulation

Methods that **change the document**: inserting, moving, removing, wrapping, and editing attributes.

Most methods here return `$this`, so they chain. The exceptions — noted per method — are
`replaceWith()`, `detach()` and `remove()`, which return a new `DOMQuery` wrapping the nodes that
were taken out.

## Contents

- [Inserting content](#inserting-content) — `append()`, `prepend()`, `before()`, `after()`
- [Inserting into another object](#inserting-into-another-object) — `appendTo()`, `prependTo()`, `insertBefore()`, `insertAfter()`
- [Removing](#removing) — `remove()`, `detach()`, `attach()`, `removeChildren()`, `emptyElement()`
- [Replacing](#replacing) — `replaceWith()`, `replaceAll()`
- [Wrapping](#wrapping) — `wrap()`, `wrapAll()`, `wrapInner()`, `unwrap()`
- [Attributes](#attributes) — `attr()`, `removeAttr()`, `hasAttr()`, `val()`
- [Classes](#classes) — `addClass()`, `removeClass()`, `hasClass()`
- [Inline styles](#inline-styles) — `css()`
- [Changing the match set](#changing-the-match-set) — `add()`, `cloneAll()`

## What counts as content

Everywhere a method below takes `$data` or `$markup`, you may pass:

- a markup string (`'<p>hi</p>'`) — the usual case
- a `DOMNode` or `DOMDocumentFragment`
- a `SimpleXMLElement`
- another `DOMQuery`

**Inserted nodes are always cloned.** A DOM node can only live at one place in a document, so
QueryPath copies it on the way in. Once inserted, the node you passed and the node in the document
are two separate objects — modifying the original will not change the document. Re-select the
inserted node if you need to work on it further.

## Inserting content

### `append()`

Insert as the **last child** of each selected element.

```php
$qp->find('ul')->append('<li>Last</li>');
```

**Returns** `$this`.

If the document is empty and nothing is selected, `append()` treats the content as the new document
element.

### `prepend()`

Insert as the **first child** of each selected element.

**Returns** `$this`.

### `before()`

Insert as a **preceding sibling** of each selected element.

**Returns** `$this`. The match set is unchanged — you still have the original elements selected, not
the inserted ones.

### `after()`

Insert as a **following sibling** of each selected element.

**Returns** `$this`. Passing empty content is a no-op.

## Inserting into another object

These are the reverse-direction forms: instead of "put this content into my elements", they mean
"put my elements into that object".

All four **return `$this`** — the *original* object, unaltered. Only `$dest` is modified.

### `appendTo()`

Append the selected elements as the last children of `$dest`'s elements.

### `prependTo()`

Insert the selected elements as the first children of `$dest`'s elements.

### `insertBefore()`

Insert the selected elements as preceding siblings of `$dest`'s elements.

### `insertAfter()`

Insert the selected elements as following siblings of `$dest`'s elements.

```php
$src  = qp($xmlA, 'item');
$dest = qp($xmlB, 'list');

$src->appendTo($dest);   // $dest now contains copies of the items
```

`appendTo()` and `attach()` require a `DOMQuery`; `prependTo()`, `insertBefore()` and
`insertAfter()` accept any `QueryPath\Query`.

## Removing

### `remove()`

Remove elements from the document.

```php
$qp->find('.advert')->remove();     // remove the selected elements
$qp->remove('.advert');             // find, then remove, in one call
```

**Returns** a new `DOMQuery` wrapping the removed nodes. They are detached but not destroyed, so
they can be re-inserted elsewhere.

> **`remove($selector)` uses the legacy selector engine**, not the one `find()` uses. The two do not
> always agree — `li:lt(2)` matches two elements through `find()` but removes only one, and
> selectors such as `:any-link` and `:scope` throw `ParseException` here while working fine in
> `find()`. When the selector is anything beyond simple CSS, prefer `find($selector)->remove()`,
> which routes through the current engine. See
> [Two selector engines](CSS-Selector-Reference.md#two-selector-engines).

### `detach()`

Remove elements and remember them, so `attach()` can put them back.

```php
$removed = $qp->find('li')->detach();
```

**Returns** a new `DOMQuery` wrapping the removed nodes.

> **Known issue.** The `$selector` argument has no effect: `detach($selector)` runs the query but
> discards the result, then detaches whatever was already selected. Call
> `find($selector)->detach()` instead.

### `attach()`

Append the nodes remembered by the last `detach()` (or `add()`) into the destination object.

```php
$qp->find('li')->detach();
$qp->attach($otherQuery);
```

**Returns** `$this`. This reads from an internal "last match set" buffer, so it is only meaningful
directly after a `detach()` on the same object.

### `removeChildren()`

Remove all child nodes of each selected element, leaving the elements themselves in place.

**Returns** `$this`. This is jQuery's `empty()`, renamed because `empty` is a reserved word in PHP.

### `emptyElement()`

A deprecated alias for `removeChildren()`.

**Returns** `$this`.

## Replacing

### `replaceWith()`

Replace each selected element with new content.

```php
$old = $qp->find('h1')->replaceWith('<h2>Title</h2>');
```

**Returns** a new `DOMQuery` wrapping **the elements that were removed**, matching jQuery's
behaviour. The replacement content is not selected.

### `replaceAll()`

Replace everything matching `$selector` in `$document` with the first element of the current set.

```php
$qp->find('template')->replaceAll('.placeholder', $otherDocument);
```

**Returns** a new `DOMQuery` wrapping `$document`.

> Deprecated, and it uses the legacy selector engine (see [`remove()`](#remove)). `replaceWith()`
> does the same job more predictably.

## Wrapping

All three wrapping methods accept the same content types as `append()`. If the markup nests, the
selected elements are placed inside its **deepest** node, so `wrap('<div><span/></div>')` puts the
element inside the `<span>`.

### `wrap()`

Wrap **each** selected element individually.

```php
// <wrap><a>1</a><b>2</b></wrap>  →  <wrap><em><a>1</a></em><b>2</b></wrap>
qp($xml, 'a')->wrap('<em/>');
```

**Returns** `$this`. Empty markup is a no-op.

### `wrapAll()`

Wrap **all** selected elements together in a single wrapper, inserted at the position of the first
one.

```php
// <wrap><a>1</a><b>2</b></wrap>  →  <wrap><em><a>1</a><b>2</b></em></wrap>
qp($xml, 'a, b')->wrapAll('<em/>');
```

**Returns** `$this`.

### `wrapInner()`

Wrap the **children** of each selected element.

```php
// <wrap><a>1</a><b>2</b></wrap>  →  <wrap><em><a>1</a><b>2</b></em></wrap>
qp($xml, 'wrap')->wrapInner('<em/>');
```

**Returns** `$this`.

### `unwrap()`

Remove each selected element's parent, promoting the element in its place. The inverse of `wrap()`.

```php
// <root><wrapper><content/></wrapper></root>  →  <root><content/></root>
qp($xml, 'content')->unwrap();
```

**Returns** `$this`, with the same elements selected.

Throws `QueryPath\Exception: Cannot unwrap the root element.` if any selected element is the
document element. Unwrapping a direct child of the root replaces the root — so do it to only one
element, or the document ends up with multiple root elements.

## Attributes

### `attr()`

Get or set attributes.

```php
$qp->attr();                            // all attributes of the first element, as an array
$qp->attr('href');                      // the first element's href
$qp->attr('href', '/new');              // set href on every selected element
$qp->attr(['id' => 'x', 'lang' => 'en']); // set several at once
```

**Returns** `$this` when setting; when getting, a `string`, an `array`, or `null`.

Getter details worth knowing:

- Only the **first** selected element is read.
- An element that exists but lacks the attribute gives `''`, not `null`.
- An **empty match set** gives `null`.
- `attr('nodeType')` is special-cased and returns the first element's DOM node type as an integer,
  not an attribute value.

### `removeAttr()`

Remove an attribute from every selected element.

**Returns** `$this`.

### `hasAttr()`

Whether **every** selected element has the attribute.

**Returns** `bool`.

> On an empty match set this returns `true`, since there is no element that fails the test. Check
> `count()` first if that matters.

### `val()`

Shorthand for the `value` attribute: `val()` reads it from the first element, `val($v)` sets it on
all of them.

**Returns** `$this` when setting, otherwise the attribute value or `null`.

> Deprecated — `attr('value')` does the same thing. It exists for jQuery familiarity and has little
> use server-side.

## Classes

### `addClass()`

Append a class to the `class` attribute of every selected element, creating the attribute if
needed.

**Returns** `$this`.

> No de-duplication is performed: calling `addClass('p')` twice produces `class="p p"`.

### `removeClass()`

Remove one class, or with no argument the whole `class` attribute.

```php
// <element class="first second"/>
$qp->removeClass('first');   // class="second"
$qp->removeClass();          // the class attribute is gone
```

If removing the named class would leave the attribute empty, the attribute is removed entirely.

**Returns** `$this`.

### `hasClass()`

Whether **any** selected element carries the class.

**Returns** `bool`.

## Inline styles

### `css()`

Get or set declarations in the `style` attribute.

```php
$qp->css('background-color', 'red');
$qp->css(['color' => 'blue', 'margin' => '0']);
$qp->css();     // the raw style attribute of the first element
```

**Returns** `$this` when setting; when getting, the raw `style` attribute string.

Since QueryPath 2.1 new declarations are merged into the existing `style` attribute rather than
replacing it. Output is written as `name: value;` pairs, including a trailing semicolon.

> **Styles are pooled across the whole match set.** `css()` reads the `style` attribute of *every*
> selected element into one map, merges your declarations into it, and writes the combined result
> back to *all* of them. If two selected elements start with different styles, both end up with the
> union of the two:
>
> ```php
> $qp = html5qp('<div><p style="color:red"/><p style="font-size:2px"/></div>', 'p');
> $qp->css('margin', '0');
> // BOTH paragraphs are now style="color: red;font-size: 2px;margin: 0;"
> ```
>
> Apply `css()` to one element at a time when the elements do not already share a style.

## Changing the match set

These two change what is selected rather than what is in the document, but both mutate the object
in place, which is why they live here rather than in
[Traversal and Filtering](Traversal-and-Filtering.md).

### `add()`

Run a fresh query from the top of the document and merge the results into the current match set.

```php
$qp->find('p')->add('div');   // paragraphs and divs
```

**Returns** `$this`, mutated. The previous match set is saved, so `end()` undoes it.

### `cloneAll()`

Deep-clone every selected node and select the clones instead. The clones are detached from the
document, so subsequent edits do not affect the original.

**Returns** `$this`, mutated. This is jQuery's `clone()`. Contrast with
[`branch()`](Traversal-and-Filtering.md#branch), which copies the *query object* and keeps pointing
at the same nodes.

## See also

- [Traversal and Filtering](Traversal-and-Filtering.md) — selecting what to change
- [Markup and Text](Markup-and-Text.md) — `html()`, `xml()`, `text()` also act as setters
- [CSS Selector Reference](CSS-Selector-Reference.md)
