# Parser Options

Every QueryPath factory takes an optional `array $options` as its third argument:

```php
qp($document, $selector, $options);
htmlqp($document, $selector, $options);
html5qp($document, $selector, $options);
QueryPath::with($document, $selector, $options);
```

Options are resolved in this order, highest priority first:

1. The `$options` array passed to the factory
2. Defaults set globally via `\QueryPath\Options::set()` / `::merge()`
3. QueryPath's own built-in defaults

```php
// Set defaults for every QueryPath object created afterwards
\QueryPath\Options::set(['format_output' => false]);
\QueryPath\Options::merge(['replace_entities' => true]);
```

## Contents

- [Parsing](#parsing)
- [Character encoding](#character-encoding)
- [Error handling](#error-handling)
- [Output](#output)
- [Advanced](#advanced)
- [What each factory sets for you](#what-each-factory-sets-for-you)
- [html5qp() is different](#html5qp-is-different)

## Parsing

### `use_parser`

`'xml'` parses as XML, `'html'` parses as HTML. If unset, QueryPath autodetects: a document
beginning `<?xml` is parsed as XML, anything else as HTML. The XML parser is strict; the HTML
parser is lenient but still enforces parts of the DTD.

### `parser_flags`

An OR-combined set of libxml parser flags, passed through to `DOMDocument::loadXML()`. All flags
supported by `DOMDocument` are supported here. Default `null`.

### `context`

A stream context resource, used when the document is a path or URL. Created with
`stream_context_create()`. This is how you set HTTP headers, timeouts, or a proxy when QueryPath
fetches a remote document.

```php
$context = stream_context_create(['http' => ['header' => "User-Agent: MyApp/1.0\r\n"]]);
qp('https://example.com/feed.xml', null, ['context' => $context]);
```

### `strip_low_ascii`

Boolean. Strips all ASCII characters below 32 before parsing. Useful when the source has illegal
control characters in it. Default `false`.

## Character encoding

### `encoding`

A character encoding such as `'utf-8'` or `'ISO-8859-1'`, passed to the `DOMDocument` constructor.

> This applies **only when creating a new empty document**. It has no effect when parsing existing
> content — use `convert_to_encoding` for that.

### `convert_to_encoding`

Converts the document to the named encoding before parsing, using `mb_convert_encoding()`. For old
HTML, `ISO-8859-1` generally gives the best results. If unset, no conversion happens.

> Requires the `mbstring` extension. If it is not loaded, the conversion is silently skipped.

### `convert_from_encoding`

The source encoding to convert *from*, used with `convert_to_encoding`. Defaults to `'auto'`,
which lets mbstring guess.

## Error handling

### `ignore_parser_warnings`

Boolean, default `false`. When `false`, parser warnings are converted into exceptions. Set it to
`true` to parse badly mangled HTML — or to let a missing file fail quietly instead of throwing.

### `exception_level`

An error-level bitmask controlling which PHP errors QueryPath converts into exceptions. Defaults to
`771` (`E_ERROR | E_WARNING | E_USER_ERROR | E_USER_WARNING`).

Setting `ignore_parser_warnings` to `true` overrides this with `257`
(`E_ERROR | E_USER_ERROR`) — that is, warnings stop being fatal.

## Output

### `format_output`

Boolean, default `true`. When `true`, output is indented for readability. Set to `false` to
minimise whitespace and keep the document small.

### `omit_xml_declaration`

Boolean, default `false`. When `true`, output methods such as `xml()` and `writeXML()` leave off
the `<?xml … ?>` declaration.

### `replace_entities`

Boolean, default `false`. When `true`, the insertion methods (`append()`, `before()`, and friends)
replace named HTML entities with their numeric equivalents and escape bare ampersands.

### `escape_xhtml_js_css_sections`

Controls how CDATA sections in `script` and `style` bodies are rendered by `xhtml()` and
`writeXHTML()`. XHTML requires these sections to be escaped, but older readers do not handle CDATA,
and comments have their own problems — so by default QueryPath comments out the CDATA delimiters,
keeping the markup valid for both XML and HTML parsers.

The value is a `preg_replace()` replacement applied to each CDATA **delimiter**
(`<![CDATA[` and `]]>`), where `\1` is the delimiter itself. Use one of the `QueryPath\DOM`
constants, or supply your own pattern:

| Constant | Value | Each delimiter becomes |
|---|---|---|
| `JS_CSS_ESCAPE_CDATA_CCOMMENT` *(default)* | `/* \1 */` | `/* <![CDATA[ */` … `/* ]]> */` |
| `JS_CSS_ESCAPE_CDATA` | `\1` | Left as-is — a bare CDATA section |
| `JS_CSS_ESCAPE_CDATA_DOUBLESLASH` | `// \1` | `// <![CDATA[` … `// ]]>` |
| `JS_CSS_ESCAPE_NONE` | *(empty)* | Removed entirely |

> `JS_CSS_ESCAPE_NONE` strips the delimiters rather than leaving them unescaped.

## Advanced

### `QueryPath_class`

The class that the factory instantiates. It must be `QueryPath\DOMQuery` or a subclass. Use this to
have QueryPath return your own subclass throughout a chain.

```php
qp($xml, 'foo', ['QueryPath_class' => MyQuery::class]);
```

### `namespace_prefix` and `namespace_uri`

These are **not** constructor options — they are read by `xpath()` only, and must be passed to that
method. Together they register a default namespace for the XPath query.

```php
$qp->xpath('//atom:entry', [
    'namespace_prefix' => 'atom',
    'namespace_uri'    => 'http://www.w3.org/2005/Atom',
]);
```

## What each factory sets for you

Each factory applies its own defaults, which your `$options` array can override.

| Factory | Applies |
|---|---|
| `qp()` / `QueryPath::with()` | Nothing — built-in defaults only |
| `QueryPath::withXML()` | `use_parser: 'xml'` |
| `htmlqp()` / `QueryPath::withHTML()` | `ignore_parser_warnings: true`, `convert_to_encoding: 'ISO-8859-1'`, `convert_from_encoding: 'auto'`, `use_parser: 'html'` |
| `html5qp()` / `QueryPath::withHTML5()` | Parses with `masterminds/html5` — see below |

`htmlqp()` additionally calls the constructor with the error-suppression operator, so parser
warnings are not surfaced to the application at all.

## html5qp() is different

`html5qp()` parses with `masterminds/html5` rather than libxml. When the source is a string, it is
handed to the HTML5 parser first and a fully built `DOMDocument` is passed on to QueryPath.

That means the **parsing** options above are never consulted for `html5qp()` — `use_parser`,
`parser_flags`, `convert_to_encoding`, `convert_from_encoding`, `strip_low_ascii` and
`replace_entities` have no effect on how the document is read. Any option supported by
`masterminds/html5` may be passed instead, and is forwarded to it.

The **output** options (`format_output`, `omit_xml_declaration`, `escape_xhtml_js_css_sections`)
and `QueryPath_class` still apply as normal.

## See also

- [CSS Selector Reference](CSS-Selector-Reference.md)
- [Getting Started](Getting-Started.md)
