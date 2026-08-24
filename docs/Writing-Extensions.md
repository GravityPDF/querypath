# Writing Extensions

QueryPath can be extended with your own methods, which then behave exactly like built-in ones —
including participating in the fluent chain.

## The interface

An extension is any class implementing `QueryPath\Extension`. The interface has a single
requirement: a constructor taking a `QueryPath\Query`.

```php
interface Extension
{
    public function __construct(Query $qp);
}
```

## A complete example

```php
use QueryPath\QueryPath;

class ShoutExtension implements \QueryPath\Extension
{
    private $qp;

    public function __construct(\QueryPath\Query $qp)
    {
        $this->qp = $qp;
    }

    public function shout()
    {
        foreach ($this->qp->get() as $node) {
            $node->textContent = strtoupper($node->textContent);
        }

        return $this->qp;   // return the Query object to stay chainable
    }
}

QueryPath::enable(ShoutExtension::class);

echo html5qp('<p>hi</p>')->find('p')->shout()->html5();
// <p>HI</p>
```

Two things make this work:

1. **Store the `Query` object in the constructor.** You need it to do anything useful, and to
   return it.
2. **Return the `Query` object** from any method that has no more specific value to return. That is
   what keeps the fluent interface intact.

## Enabling extensions

```php
QueryPath::enable(ShoutExtension::class);                  // one
QueryPath::enable([ShoutExtension::class, Other::class]);  // several
```

Enable an extension before creating the QueryPath objects that use it.

To see what is currently enabled:

```php
print_r(QueryPath::enabledExtensions());
// Array ( [0] => ShoutExtension )
```

## How the dispatch works

Extensions are **not** instantiated when a QueryPath object is constructed — that would make `qp()`
expensive for the majority of callers who use no extensions at all.

Instead, `DOMQuery::__call()` catches the unknown method, lazily instantiates the registered
extension that provides it, and dispatches to it by reflection. The cost is paid on first use, per
object.

A consequence worth knowing: **an extension method that collides with an existing `DOMQuery` method
will never be called**, because `__call()` only fires for methods that do not already exist.

## Managing the registry directly

`QueryPath\ExtensionRegistry` backs `QueryPath::enable()` and can be used directly for finer
control:

| Method | Purpose |
|---|---|
| `ExtensionRegistry::extend($class)` | Register an extension class |
| `ExtensionRegistry::extensionNames()` | List registered class names |
| `ExtensionRegistry::hasExtension($name)` | Is this extension registered? |
| `ExtensionRegistry::hasMethod($name)` | Does any extension provide this method? |
| `ExtensionRegistry::getMethodClass($name)` | Which extension provides this method? |
| `ExtensionRegistry::getExtensions($qp)` | Instantiate all extensions against a Query |

Extension support can be turned off wholesale:

```php
\QueryPath\ExtensionRegistry::$useRegistry = false;
\QueryPath\ExtensionRegistry::autoloadExtensions(false);   // identical — a setter for the same flag
```

With extensions disabled, calling an extension method throws
`QueryPath\Exception: No method named … found (Extensions disabled).` rather than being silently
ignored.

Calling a method that no registered extension provides throws
`QueryPath\Exception: No method named … found. Possibly missing an extension.`

## The bundled extensions

Three ship with the library, and they are the best short reference for writing your own.

### `QueryPath\Extension\QPXML`

XML-specific node handling.

`schema()`, `cdata()`, `comment()`, `pi()`, `toXml()`, `createNilElement()`, `createElement()`,
`appendElement()`

```php
QueryPath::enable(\QueryPath\Extension\QPXML::class);

echo qp('<?xml version="1.0"?><r/>')->find('r')->comment('hi')->top()->xml();
// <?xml version="1.0"?>
// <r>
//   <!--hi-->
// </r>
```

### `QueryPath\Extension\QPXSL`

XSL transformation: `xslt()`.

### `QueryPath\Extension\Format`

Applies a formatting callback to text or attribute values: `format()`, `formatAttr()`.

## See also

- [Getting Started](Getting-Started.md)
- [CSS Selector Reference](CSS-Selector-Reference.md)
- `src/Extension/` in the repository for the bundled implementations
