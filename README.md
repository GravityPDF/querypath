# QueryPath: Find the better way

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/arthurkushman/querypath/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/arthurkushman/querypath/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/arthurkushman/querypath/badges/build.png?b=master)](https://scrutinizer-ci.com/g/arthurkushman/querypath/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/arthurkushman/querypath/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)
[![codecov](https://codecov.io/gh/arthurkushman/querypath/branch/master/graph/badge.svg)](https://codecov.io/gh/arthurkushman/querypath)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

## At A Glance

QueryPath is a jQuery-like library for working with XML and HTML
documents in PHP. It now contains support for HTML5 via the
[HTML5-PHP project](https://github.com/Masterminds/html5-php).

### Why this lib was forked and recoded

- Legacy code (repo was left for > 3 years) didn't allow to support new features of PHP>=7.1
- A lot of DeaDBeaF code like: unused params, unused local variables etc
- A lot of needless flow structures 
- DRY/KISS/SOLID rules were thrown away when it was developed
- Minor bugs and fragile functionality 

### Installation
``` 
composer require arthurkushman/query-path 
```

### Gettings Started

Assuming you have successfully installed QueryPath via Composer, you can
parse documents like this:

```
// HTML5 (new)
$qp = html5qp("path/to/file.html");

// Legacy HTML via libxml
$qp = htmlqp("path/to/file.html");

// XML or XHTML
$qp = qp("path/to/file.html");

// All of the above can take string markup instead of a file name:
$qp = qp("<?xml version='1.0'?><hello><world/></hello>")

```

But the real power comes from chaining. Check out the example below.

### Example Usage

Say we have a document like this:
```xml
<?xml version="1.0"?>
<table>
  <tr id="row1">
    <td>one</td><td>two</td><td>three</td>
  </tr>
  <tr id="row2">
    <td>four</td><td>five</td><td>six</td>
  </tr>
</table>
```

And say that the above is stored in the variable `$xml`. Now
we can use QueryPath like this:

```php
<?php
// Add the attribute "foo=bar" to every "td" element.
qp($xml, 'td')->attr('foo', 'bar');

// Print the contents of the third TD in the second row:
echo qp($xml, '#row2>td:nth(3)')->text();

// Append another row to the XML and then write the
// result to standard output:
qp($xml, 'tr:last')->after('<tr><td/><td/><td/></tr>')->writeXML();

?>
```

(This example is in `examples/at-a-glance.php`.)

With over 60 functions and robust support for chaining, you can
accomplish sophisticated XML and HTML processing using QueryPath.

From there, the main functions you will want to use are `qp()`
(alias of `QueryPath::with()`) and `htmlqp()` (alias of
`QueryPath::withHTML()`). 

## QueryPath Format Extension

### format()

```php
\QueryPath\DOMQuery format(callable $callback [, mixed $args [, $... ]])
```

A quick example:

```php
<?php
QueryPath::enable(Format::class);
$qp = qp('<?xml version="1.0"?><root><div>_apple_</div><div>_orange_</div></root>');

$qp->find('div')
        ->format('strtoupper')
        ->format('trim', '_')
        ->format(function ($text) {
            return '*' . $text . '*';
        });

$qp->writeXML();
```

OUTPUT:

```xml
<?xml version="1.0"?>
<root>
  <div>*APPLE*</div>
  <div>*ORANGE*</div>
</root>
```


### formatAttr()

```php
\QueryPath\DOMQuery formatAttr(string $name, callable $callback [, mixed $args [, $... ]])
```

A quick example:

```php
<?php
QueryPath::enable(Format::class);
$qp = qp('<?xml version="1.0"?><root>' .
        '<item label="_apple_" total="12,345,678" />' .
        '<item label="_orange_" total="987,654,321" />' .
        '</root>');

$qp->find('item')
        ->formatAttr('label', 'trim', '_')
        ->formatAttr('total', 'str_replace[2]', ',', '');

$qp->find('item')->formatAttr('label', function ($value) {
    return ucfirst(strtolower($value));
});

$qp->writeXML();
```

OUTPUT:

```xml
<?xml version="1.0"?>
<root>
  <item label="Apple" total="12345678"/>
  <item label="Orange" total="987654321"/>
</root>
```

