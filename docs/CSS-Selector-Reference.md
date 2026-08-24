# CSS Selector Reference

QueryPath implements CSS 3 selectors, plus parts of the CSS 4 selector draft and most of the jQuery
pseudo-class extensions. Selectors can be passed to `qp()`, `htmlqp()`, `html5qp()`, and to
`find()`, `top()`, `children()`, `filter()`, `not()`, `has()` and others.

```php
$qp = html5qp($html, 'body');   // find the body
$another = $qp->branch('p');    // a second object searching body for p tags
$qp->find('strong > a');        // a elements directly inside strong elements
$qp->top('head');               // start over at the document root, find head
```

XPath is available too, via `xpath()`:

```php
qp($xml)->xpath('//foo');
```

> Everything on this page was verified against the current release. Where QueryPath differs from
> jQuery or from the CSS spec, the difference is called out — several of them are surprising.

## Contents

- [Basic selectors](#basic-selectors)
- [Combinators](#combinators)
- [Attribute selectors](#attribute-selectors)
- [Pseudo-classes](#pseudo-classes)
  - [Position and counting](#position-and-counting) — **1-indexed, unlike jQuery**
  - [Structural](#structural)
  - [Content](#content)
  - [Links](#links)
  - [Form](#form)
  - [Scope](#scope)
  - [Always false](#always-false-user-agent-dependent)
  - [Special cases](#special-cases)
- [Pseudo-elements](#pseudo-elements)
- [XML namespaces](#xml-namespaces)
- [Two selector engines](#two-selector-engines)

## Basic selectors

| Selector | Matches |
|---|---|
| `p` | All `p` elements |
| `*` | Any element |
| `#my-id` | The element with `id="my-id"` |
| `div.content` | `div` elements with `content` in their class list |
| `.a.b` | Elements carrying both classes |
| `h1, h2` | All `h1` **and** all `h2` (selector group) |

## Combinators

| Selector | Matches |
|---|---|
| `strong a` | `a` anywhere beneath a `strong` (descendant) |
| `strong > a` | `a` directly beneath a `strong` (child) |
| `h1 + p` | The `p` immediately following an `h1` (adjacent sibling) |
| `h1 ~ p` | Any `p` following an `h1` at the same level (general sibling) |
| `:root > head` | `head` directly beneath the document root |

> **The child combinator crashes when its right-hand side matches the document element.** In
> practice this means `X > *` always raises a `TypeError` from the selector engine, whatever `X` is:
>
> ```php
> qp($xml, 'wrap > *');           // TypeError
> qp($xml, 'wrap > a');           // fine
> qp($xml, 'wrap *');             // fine — descendant combinator
> qp($xml, 'wrap')->children();   // fine — the intended replacement
> ```
>
> `combineDirectDescendant()` hands the parent node straight to a method typed `DOMElement`, and the
> document element's parent is the `DOMDocument`. Use
> [`children()`](Traversal-and-Filtering.md#children) until this is fixed.

## Attribute selectors

| Selector | Matches |
|---|---|
| `[href]` | Has an `href` attribute |
| `[href="x"]` | `href` is exactly `x` |
| `[href~="x"]` | `href` is a space-separated list containing `x` |
| `[href\|="x"]` | `href` is `x`, or begins `x-` |
| `[href^="x"]` | `href` begins with `x` |
| `[href$="x"]` | `href` ends with `x` |
| `[href*="x"]` | `href` contains `x` |

## Pseudo-classes

### Position and counting

> **These are 1-indexed. jQuery's equivalents are 0-indexed.** Porting a jQuery selector across
> without adjusting the number will select the wrong element — or nothing at all.

Against `<ul><li>a</li><li>b</li><li>c</li><li>d</li><li>e</li></ul>`:

| Selector | QueryPath matches | jQuery would match |
|---|---|---|
| `li:eq(0)` | *nothing* | `a` |
| `li:eq(1)` | `a` | `b` |
| `li:eq(2)` | `b` | `c` |
| `li:nth(1)` | `a` | — |
| `li:first` | `a` | `a` |
| `li:last` | `e` | `e` |
| `li:lt(2)` | `a`, `b` | `a`, `b` |
| `li:gt(2)` | `c`, `d`, `e` | `d`, `e` |
| `li:even` | `b`, `d` | `a`, `c`, `e` |
| `li:odd` | `a`, `c`, `e` | `b`, `d` |

`:even` and `:odd` count from 1, so **the first element is odd**.

`:lt()` and `:gt()` are asymmetric: `:lt(n)` is "position ≤ n" (inclusive), `:gt(n)` is
"position > n" (exclusive). `li:lt(2)` and `li:gt(2)` therefore both include position 2 and
exclude it respectively — they are not complements.

`:nth-child()` and friends follow the CSS spec and take `an+b`, `odd`, or `even`:

| Selector | Matches |
|---|---|
| `li:nth-child(1)` | `a` |
| `li:nth-child(2n)` | `b`, `d` |
| `li:nth-child(odd)` | `a`, `c`, `e` |
| `:nth-last-child(n)` | As above, counting from the end |
| `:nth-of-type(n)` | Every nth element of that tag name |
| `:nth-last-of-type(n)` | As above, counting from the end |

### Structural

| Selector | Matches |
|---|---|
| `:root` | The document root element |
| `:first-child` / `:last-child` | First / last child of its parent |
| `:only-child` | Only if it has no siblings |
| `:first-of-type` / `:last-of-type` | First / last of its tag name |
| `:only-of-type` | Only element of its tag name among siblings |
| `:empty` | Has no child nodes |
| `:parent` | Has child nodes (the inverse of `:empty`) |

### Content

| Selector | Matches |
|---|---|
| `p:contains(Hello)` | Text content contains `Hello` (substring match) |
| `p:contains-exactly(Hello)` | Text content is exactly `Hello` (**not** a substring match) |
| `:has(strong > a)` | Has a descendant matching the given selector |
| `:matches(sel)` | Alias of `:has()` |
| `:not(.nav)` | Negation. Takes a full selector. Throws `ParseException` with no value |

### Links

| Selector | Matches |
|---|---|
| `:link` | Has an `href` attribute |
| `:any-link` | Has an `href`, `src`, or `link` attribute (CSS 4) |
| `:local-link` | A link pointing within the current document (CSS 4) |

### Form

`:enabled`, `:disabled` and `:checked` match on the presence of the attribute of that name.

`:text`, `:radio`, `:checkbox`, `:file`, `:password`, `:submit`, `:image`, `:reset` and `:button`
match `input` elements by their `type` attribute:

```php
html5qp($html)->find('form input:text');   // all text inputs in a form
```

`:header` matches `h1` through `h6`.

### Scope

`:scope` (CSS 4) matches the element that was passed into the QueryPath constructor, rather than
the document root. `:x-root` and `:x-reset` are QueryPath's older names for the same thing and
remain supported.

### Always false (user-agent dependent)

These parse without error and match nothing, because a server-side library has no user agent,
no viewport, no history, and no location:

`:current`, `:past`, `:future`, `:visited`, `:hover`, `:active`, `:focus`, `:animated`,
`:visible`, `:hidden`, `:target`

These also always return false, because QueryPath does not validate documents or resolve
text direction:

`:valid`, `:invalid`, `:required`, `:optional`, `:read-only`, `:read-write`, `:dir()`,
`:nth-column()`, `:nth-last-column()`

### Special cases

**`:indeterminate` returns a random result.** It is implemented as a coin flip per element, so the
same selector against the same document returns a different match set each time it runs. Do not
use it.

**`:lang()` is implemented**, but requires a value — `:lang()` with no argument throws
`NotImplementedException`. Note that it does not implement the full spec.

**An unrecognised pseudo-class throws `ParseException`** (`Unknown Pseudo-Class: …`) rather than
matching nothing.

## Pseudo-elements

Pseudo-elements use the double-colon syntax and are only partially meaningful server-side:

| Selector | Behaviour |
|---|---|
| `::first-line` | Matches the element if it has any text content |
| `::first-letter` | Matches the element if it has any text content |
| `::before` | Matches the element if it has any text content |
| `::after` | Matches the element if it has any text content |
| `::selection` | Throws `NotImplementedException` |

> `::first-line` and `::first-letter` **do not extract a line or a letter**. All four of the
> supported pseudo-elements resolve to the same test — "does this element have text?" — and return
> the whole element. `qp($xml, 'p::first-letter')->text()` returns the entire paragraph text, not
> its first character.

## XML namespaces

CSS namespace syntax uses a vertical bar, **not** the colon used in the XML tag itself. To select
`<atom:entry>`, the selector is `atom|entry`:

```php
qp($xml, 'atom|entry');                      // all <atom:entry> elements
qp($xml, 'atom|entry > xmedia|video');       // <xmedia:video> directly inside <atom:entry>
qp($xml, '*|entry');                         // any namespace, tag name "entry"
```

QueryPath resolves namespaces to short names where it can, but a malformed namespace declaration
can prevent namespace queries from resolving.

## Two selector engines

QueryPath contains two independent CSS engines. `find()` and most traversal methods use the
current one; **`remove()` and `replaceAll()` still use the legacy engine**, which does not support
the full selector set and does not always agree with `find()`.

Confirmed differences against `<ul><li>a</li>…<li>e</li></ul>`:

| Selector | `find()` | `remove()` |
|---|---|---|
| `li:lt(2)` | matches 2 elements | removes 1 element |
| `li:any-link` | matches 0 | throws `ParseException` |
| `li:scope` | matches 0 | throws `ParseException` |

If a selector behaves differently than this page describes, check first whether you are calling it
through `remove()` or `replaceAll()`. Consolidating onto a single engine is tracked work.

## See also

- [Parser Options](Parser-Options.md) — every option accepted by `qp()`, `htmlqp()` and `html5qp()`
- [Getting Started](Getting-Started.md)
- The `examples/` directory in the repository
- [API Reference](API-Reference.md) — every method, with a known-issues summary
