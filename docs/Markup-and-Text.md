# Markup and Text

Reading and writing the content of selected elements, and serialising the document.

Every method on this page that takes an optional argument is both a **getter** (called with no
argument) and a **setter** (called with one). As a setter each returns `$this` and chains; as a
getter each returns a string, or `null` when nothing is selected (with the exception of `text()`,
which returns `''`).

## Contents

- [Which method should I use?](#which-method-should-i-use)
- [Whole-element markup](#whole-element-markup) — `html()`, `html5()`, `xml()`, `xhtml()`
- [Inner markup](#inner-markup) — `innerHTML()`, `innerHTML5()`, `innerXML()`, `innerXHTML()`
- [Text](#text) — `text()`, `textImplode()`, `childrenText()`, `textBefore()`, `textAfter()`
- [Writing to output or a file](#writing-to-output-or-a-file) — `writeHTML()`, `writeHTML5()`, `writeXML()`, `writeXHTML()`
- [Data URLs](#data-urls) — `dataURL()`

## Which method should I use?

| You want | Method |
|---|---|
| The element **and** its children, as HTML5 | `html5()` |
| Only the children, as HTML5 | `innerHTML5()` |
| The element and its children, as XML | `xml()` |
| Only the children, as XML | `innerXML()` |
| Just the text, markup stripped | `text()` |
| To print the whole document | `writeHTML5()` / `writeXML()` |

> **`html()` is not jQuery's `html()`.** QueryPath's `html()` includes the element itself;
> jQuery's returns only the children. `innerHTML()` is the one that matches jQuery.

## Whole-element markup

All four getters read only the **first** selected element.

### `html()`

Legacy HTML 4.01, via libxml.

```php
$qp->find('#d')->html();     // '<div id="d">test <p>foo</p> tail</div>'
$qp->find('#d')->html('<p>new</p>');   // replaces all children
```

As a setter the markup **must be well formed** — it is parsed as an XML document fragment, so
unclosed tags fail. If the `replace_entities` option is on, named entities are converted first.

When the first selected element is the document element, the whole document is serialised
(including the doctype).

### `html5()`

The same, parsed and serialised by `masterminds/html5`. **This is the one to use for HTML.** As a
setter it accepts real-world HTML fragments, not just well-formed XML.

### `xml()`

XML. As a setter, the markup is parsed as a document fragment and replaces the children of each
selected element — an XML declaration is not needed.

Passing `true` instead of markup is a getter that omits the XML declaration:

```php
$qp->xml(true);     // serialise without <?xml … ?>
```

The same effect is available document-wide through the
[`omit_xml_declaration`](Parser-Options.md#omit_xml_declaration) option.

### `xhtml()`

Like `xml()`, but always writes closing tags (`<script></script>`, never `<script/>`) and collapses
the HTML unary elements — `br`, `img`, `input`, `meta` and friends — to `<br />` form. CDATA
delimiters inside `script` and `style` are rewritten according to the
[`escape_xhtml_js_css_sections`](Parser-Options.md#escape_xhtml_js_css_sections) option.

As a **setter** `xhtml($markup)` simply delegates to `xml($markup)`. Like `xml()`, it accepts `true`
as a getter that omits the XML declaration.

No schema validation is performed.

## Inner markup

These four are getters only, and read only the **first** selected element. Each returns `''` for an
element with no children, and `null` when nothing is selected.

### `innerXML()`

libxml, XML serialisation rules.

### `innerHTML()`

An alias of `innerXML()` — despite the name, the output is XML-serialised.

### `innerXHTML()`

libxml with `LIBXML_NOEMPTYTAG`, so every element gets a closing tag.

### `innerHTML5()`

Serialised by `masterminds/html5`. The right choice for HTML documents.

`innerXHTML()` writes a closing tag for **every** element, including the HTML void elements, so a
`<br>` comes back as `<br></br>` rather than `<br />`. Unlike `xhtml()`, there is no post-processing
step to collapse them. Use `innerHTML5()` for HTML output.

```php
// <div>test <p>foo</p> tail</div>
$qp->find('div')->innerHTML();   // 'test <p>foo</p> tail'
```

> `innerHTML()` is the alias, not the implementation — it produces XML-serialised output. For HTML5
> documents use `innerHTML5()`.

## Text

### `text()`

Get or set text content, with markup escaped.

```php
$qp->find('p')->text();          // the text of every match, concatenated
$qp->find('p')->text('hello');   // replaces all children with a text node
```

**Returns** `$this` when setting, otherwise a string.

> As a getter, `text()` concatenates **all** selected elements with no separator — five elements
> holding `1`…`5` give `'12345'`. Use `textImplode()` when you want a separator, or `eq(0)->text()`
> for just the first.

### `textImplode()`

Concatenate the text of each selected element with a separator.

```php
$qp->find('li')->textImplode(', ');        // 'one, two, three'
$qp->find('li')->textImplode(' | ', false); // keep empty items
```

`$filterEmpties` (default `true`) drops elements whose text is empty or whitespace-only.

**Returns** `string`.

### `childrenText()`

Concatenate the text of the descendants of the selected elements.

**Returns** `string`. Implemented as `xpath('descendant::text()')`, so despite the name it collects
text from the **whole subtree**, not only the immediate children. It is non-destructive — the match
set is untouched.

### `textBefore()`

### `textAfter()`

Get or set the text nodes immediately adjacent to each selected element — `textBefore()` looks
backwards, `textAfter()` forwards.

```php
$xml = '<?xml version="1.0"?><root>Foo<a>Bar</a><b/></root>';

qp($xml, 'a')->textBefore();        // 'Foo'
qp($xml, 'b')->textBefore('Baz');   // inserts 'Baz' before <b/>
```

As getters they walk backwards (or forwards) over **consecutive text-node siblings only** and stop
at the first element, so they read the adjacent run of text and nothing further.

**Returns** `$this` when setting, otherwise a string (possibly empty).

## Writing to output or a file

Each of these serialises the **entire document**, not just the selected elements. With no argument
they print to STDOUT; with a path they write a file.

### `writeHTML5()`

HTML5, UTF-8, via `masterminds/html5`.

### `writeHTML()`

HTML 4.01, via libxml.

### `writeXML()`

XML. The second argument takes libxml constants.

### `writeXHTML()`

`writeXML()` with `LIBXML_NOEMPTYTAG`, so every element gets a closing tag.

```php
html5qp($html)->writeHTML5();              // to the browser
html5qp($html)->writeHTML5('/tmp/out.html'); // to a file
```

If the file cannot be written, `writeXML()` and `writeXHTML()` throw `QueryPath\IOException` and
`writeHTML()` throws `QueryPath\ParseException` — both descend from `QueryPath\Exception`, so a
single catch covers them. `writeHTML5()` is the exception: a bad path surfaces as a raw `TypeError`
from the underlying HTML5 library, so check the directory yourself before calling it.

> `writeHTML()`, `writeXML()` and `writeXHTML()` return `$this` and chain. **`writeHTML5()` returns
> `null`**, so it must be the last call in a chain.

> Under PHPUnit these methods trip `beStrictAboutOutputDuringTests`. Wrap them in output buffering
> when testing.

## Data URLs

### `dataURL()`

Read or write an attribute as an [RFC 2397 data URL](https://en.wikipedia.org/wiki/Data_URI_scheme).

```php
// Setter — inject a PNG into every selected element's src attribute
$qp->find('img')->dataURL('src', file_get_contents('my.png'), 'image/png');

// Getter
$qp->find('img')->dataURL('src');
// ['mime' => 'image/png', 'data' => "\x89PNG…"]
```

`$data` may be a string, a stream or file handle, or a full URL (in which case the content is
fetched, optionally through the `$context` stream context).

**Returns** `$this` when setting. As a getter it returns an **array** with `mime` and `data` keys —
not a string — or `null` if the attribute is missing or is not a base64 data URL. Only base64
encoding is supported.

`QueryPath::encodeDataURL($data, $mime, $context)` is the underlying static helper, usable without a
document.

## See also

- [Parser Options](Parser-Options.md) — `format_output`, `omit_xml_declaration`, entity handling
- [Manipulation](Manipulation.md) — structural changes
- [Document and Utility](Document-and-Utility.md) — `document()`, `toArray()`, `getOptions()`
