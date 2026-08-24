# CLAUDE.md

> **Cheat sheet only** — commands and layout. Keep it terse. Decisions, specs, research, findings, gotchas → `.claude/memory/` (see `MEMORY.md`). Plans → `.claude/plans/`.

@.claude/memory/MEMORY.md

## Overview

`gravitypdf/querypath` is a fork of the QueryPath library: a jQuery-like fluent API for querying and
manipulating XML/HTML(5) documents in PHP. It is a library published on Packagist and
`replace`s `querypath/querypath` and `arthurkushman/query-path`. Much of the code is legacy (2009–2012 era) with Doxygen-style DocBlocks.

## Commands

```bash
composer install
vendor/bin/phpunit                                    # full suite (PHPUnit 9 via yoast/phpunit-polyfills)
vendor/bin/phpunit --filter testAppend                # single test method
vendor/bin/phpunit tests/QueryPath/DOMQueryTest.php   # single file
vendor/bin/phpunit --coverage-clover=./coverage/coverage1.xml

composer run lint          # phpcs against phpcs.xml (src, tests, examples)
composer run lint:fix      # phpcbf
composer run lint:min-php  # PHPCompatibility check against phpcompat.xml
```

The `Makefile` targets are stale (they point at a `test/Tests` directory that no longer exists) — ignore them.

CI (`.github/workflows`) runs PHPUnit on PHP 7.1–8.5 for pull requests, and phpcs + PHPCompatibility on every push.

## Constraints that shape every change

- **PHP 7.1 through 8.5 must all pass.** `phpcompat.xml` pins `testVersion` to `7.1-`. No typed properties, no arrow
  functions, no constructor promotion, no `match`, no union types. `??` and `?:` are fine.
- **Tabs, not spaces.** `phpcs.xml` is PSR-2 with `Generic.WhiteSpace.DisallowSpaceIndent` and tab indentation
  (tab-width 4).
- **Tests are extended, not replaced.** Test classes extend `QueryPathTests\TestCase`, which extends
  `Yoast\PHPUnitPolyfills\TestCases\TestCase` so the same tests run on the PHPUnit versions supported across PHP 7.1–8.5.
  Use the polyfill lifecycle names (`set_up`, `tear_down`, `assertMatchesRegularExpression`, etc.), not the
  version-specific PHPUnit ones.
- `phpunit.xml` sets `beStrictAboutOutputDuringTests`, and many QueryPath methods print (`writeHTML()`,
  `writeHTML5()`, `writeXML()`) — wrap those in output buffering in tests.
- PRs are expected to add an entry under `# Unreleased changes` in `CHANGELOG.md` and a test for the fixed behaviour
  (see `.github/CONTRIBUTING.md`).

## Architecture

### Entry points

`src/qp_functions.php` (autoloaded by Composer via `files`) defines the three global factories, each guarded by
`function_exists()` because the replaced packages defined the same names:

- `qp()` → `QueryPath::with()` — XML/XHTML via libxml
- `htmlqp()` → `QueryPath::withHTML()` — legacy HTML via libxml
- `html5qp()` → `QueryPath::withHTML5()` — HTML5 via `masterminds/html5` (recommended path)

All three return a `QueryPath\DOMQuery`.

### The query object

`QueryPath\DOM` (abstract) holds the `DOMDocument` and the `SplObjectStorage` of matched nodes, and its constructor is
the polymorphic loader — it accepts a file path, an XML/HTML string, `DOMDocument`, `DOMNode`, `SplObjectStorage`,
`SimpleXMLElement`, `Masterminds\HTML5`, another `DOM`, or an array of nodes. Option precedence is
`$options` passed in → `QueryPath\Options::get()` (global defaults) → the class defaults in `DOM::$options`.

`QueryPath\DOMQuery extends DOM implements Query` is the public surface. It is deliberately split so the file stays
navigable — most jQuery-equivalent methods live in traits under `src/Helpers/` and are composed into `DOMQuery`:

- `QueryFilters` — traversal/filtering (`filter`, `map`, `each`, `eq`, `not`, `closest`, `parent(s)`, `children`,
  `next/prev(All|Until)`, `siblings`, …)
- `QueryMutators` — mutation (`append`, `prepend`, `before`, `after`, `wrap*`, `replaceWith`, `attr`, `css`,
  `addClass`, `remove`, …)
- `QueryChecks` — predicates (`is`, `has`, `hasClass`, `hasAttr`, `removeAttr`)

When adding or fixing a method, edit the trait, not `DOMQuery`.

**Chaining semantics matter and are easy to break.** `DOMQuery::inst()` clones the object and swaps the match set —
that clone is what supports `end()` and `branch()`. `find()` returns a new instance via `inst()`; `findInPlace()`
mutates `$this`. Preserve whichever variant a method already uses.

`src/Query.php` is the (small, partial) interface `DOMQuery` implements; the bulk of the API is not declared there.

### CSS selector engine (`src/CSS/`)

Selector strings are parsed by an event-driven, SAX-like pipeline:

`InputStream` → `Scanner` (produces `Token`s) → `Parser` → calls into an `EventHandler` implementation.

There are two `EventHandler` implementations, and both are live:

- `Selector` — accumulates parsed selectors into `SimpleSelector` objects. This is what the **current** engine,
  `CSS\DOMTraverser` (implements `CSS\Traverser`), consumes. `DOMTraverser` does an initial match (by ID, class,
  element, or namespace) then walks combinators (`>`, `+`, `~`, descendant) and filters attributes/pseudo-classes.
  Pseudo-class evaluation lives in `CSS\DOMTraverser\PseudoClass`; `an+b` parsing and other shared helpers in
  `CSS\DOMTraverser\Util`. **This is the engine `find()` uses.**
- `QueryPathEventHandler` — the **legacy** engine, still used by `QueryMutators::remove()` and
  `QueryMutators::replaceAll()`. A selector bug can therefore reproduce in one engine and not the other; check which
  path the failing method takes before fixing.

Supported selectors are CSS 3 plus parts of CSS 4 and most jQuery pseudo-classes (`:eq`, `:lt`, `:gt`, `:first`,
`:odd`, …). UA-dependent pseudo-classes (`:hover`, `:visited`, …) parse but never match.

### Extensions (`src/Extension.php`, `src/ExtensionRegistry.php`)

An extension is a class implementing `QueryPath\Extension` (constructor takes a `Query`), registered with
`QueryPath::enable(...)`. `DOMQuery::__call()` lazily instantiates registered extensions on the first unknown method
call and dispatches via reflection — extensions are not loaded during construction, by design, so `qp()` stays cheap.
Bundled extensions live in `src/Extension/`: `QPXML`, `QPXSL`, `Format`.

### Other pieces

- `src/Entities.php` / `EntitiesContract.php` — HTML entity → numeric entity replacement, used when the
  `replace_entities` option is on.
- `src/QueryPathIterator.php` — makes `foreach ($qp->find('li') as $li)` yield `DOMQuery` objects, not raw nodes.
- `src/Exception.php`, `ParseException.php`, `IOException.php`, `CSS/ParseException.php`,
  `CSS/NotImplementedException.php` — all throwables descend from `QueryPath\Exception`, so catching it is the
  documented user-facing pattern.
- `src/documentation.php` and `config.doxy` exist only to feed Doxygen; they contain no runtime code.
- `tests/*.xml` and `tests/data.html` are fixtures referenced by `TestCase::DATA_FILE_XML` / `DATA_FILE_HTML`
  (paths are relative to the repo root, so PHPUnit must be run from there).
- `examples/` are runnable scripts demonstrating the API; they are linted but not tested.
