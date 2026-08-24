# QueryPath

A jQuery-like library for working with XML and HTML(5) documents in PHP.

```bash
composer require gravitypdf/querypath
```

## Documentation

### Guides

| Page | What's in it |
|---|---|
| [Getting Started](Getting-Started.md) | The three factories, chaining, and how object identity works |
| [CSS Selector Reference](CSS-Selector-Reference.md) | Every supported selector, with the jQuery differences called out |
| [Parser Options](Parser-Options.md) | Every option `qp()`, `htmlqp()` and `html5qp()` accept |
| [Writing Extensions](Writing-Extensions.md) | Adding your own methods to the fluent API |

### API reference

| Page | What's in it |
|---|---|
| [API Reference](API-Reference.md) | Every method, alphabetically, with a known-issues summary |
| [Traversal and Filtering](Traversal-and-Filtering.md) | Choosing which elements are selected |
| [Manipulation](Manipulation.md) | Changing the document |
| [Markup and Text](Markup-and-Text.md) | Reading and writing content |
| [Document and Utility](Document-and-Utility.md) | The match set, the DOM, options, and errors |

### Community guides

- [How to parse HTML in PHP using querypath](How-to-parse-HTML-in-PHP-using-querypath-library.md) — a
  web-scraping oriented walkthrough

## Quick example

```php
require_once __DIR__ . '/vendor/autoload.php';

$html = '<ul><li>Foo</li><li>Bar</li><li>FooBar</li></ul>';

foreach (html5qp($html)->find('li') as $li) {
    echo $li->text(), "\n";
}
```

## Editing these pages

**This wiki is generated.** The pages live in [`docs/`](https://github.com/GravityPDF/querypath/tree/main/docs)
in the main repository and are pushed here automatically when `main` changes.

Edit the files in `docs/` and open a pull request — **edits made directly in the wiki UI will be
overwritten** on the next sync.

## Elsewhere

- [Repository](https://github.com/GravityPDF/querypath)
- [Issues](https://github.com/GravityPDF/querypath/issues)
- [Discussions](https://github.com/GravityPDF/querypath/discussions)
- [Packagist](https://packagist.org/packages/gravitypdf/querypath)
- `examples/` in the repository — runnable scripts covering each part of the API

> The legacy manual at [querypath.org](http://querypath.org/) documents QueryPath 2.x and is not
> maintained by Gravity PDF. Prefer the pages above.
