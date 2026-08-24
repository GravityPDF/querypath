# QueryPath modernization plan

Status: proposed, 2026-08-21. Constraint: **no breaking changes** — this plan stays inside a 4.x minor.

## What "no breaking changes" rules out

Everything below obeys these, so they're stated once:

- **No native param/return types added to public or protected methods.** `DOMQuery` is subclassable, `Query` is
  implementable, and extensions are duck-typed through `__call()`. Adding `string $selector` to a public method
  breaks every subclass that overrides it. PHPDoc types are free; native types are not.
- **No public method or class removals**, including the ones that are already broken.
- **No PHP floor bump.** `^7.1` stays. That rules out typed properties, `??=`, arrow functions, `str_contains`,
  constructor promotion, and `match`.
- **No behaviour changes to selector matching**, unless covered by a test that proves the old behaviour was a bug.

---

## Audit findings

Measured on PHP 8.3.16, 2026-08-21. Baseline: **319 tests, 1073 assertions, green**, 2 skipped. `composer run lint`
passes clean. Line coverage 88.7%, method coverage 67.6%.

### 1. There is no static analysis, and it would find real bugs

PHPStan has never been run here. A throwaway run against `src/` finds:

| Level | Errors |
|-------|--------|
| 0 | 8 |
| 1 | 21 |
| 2 | 69 |
| 3 | 78 |
| 5 | 146 |

That is a small, tractable number for an 11.6k-line legacy library, and level 0 is almost entirely genuine defects:

- `src/CSS/DOMTraverser.php:386` — `combineAnyDescendant()` documents `@return boolean` but falls off the end of the
  `while` loop returning `null`. Callers compare loosely so it hasn't bitten yet.
- `src/CSS/Parser.php:363` — `pseudoClassValue()` same shape: documented `string`, returns `null` when the token
  isn't `LPAREN`.
- `src/DOMQuery.php:405` — `dataURL()` has three exits, one of which (`return;`) yields `null` against a documented
  `DOMQuery|string`.
- `src/DOM.php:247` and `:272` — the abstract `DOM` writes `$this->last` and `$this->length`, but both properties are
  declared on the `DOMQuery` **subclass** (`src/DOMQuery.php:57`, `:66`). Anything that extends `DOM` directly creates
  dynamic properties — deprecated in PHP 8.2, fatal in PHP 9.
- `src/Helpers/QueryFilters.php:98` and `:370` — `filterLambda()` and `eachLambda()` call `create_function()`, removed
  in PHP 8.0. These are **fatal on every supported PHP 8**. Both tests for them are `markTestSkipped`'d
  (`tests/QueryPath/DOMQueryTest.php:585`, `:727`), so the suite is green while the methods are dead.
- `src/Helpers/QueryFilters.php:246` — `@return Iterable` resolves as a class name, not the `iterable` keyword.

Level 2 adds a large cluster of docblock types that don't resolve: `callback` instead of `callable` (5 sites in
`QueryFilters`/`DOMQuery`), `DOMNode` written unqualified inside `QueryPath\CSS` so it resolves to
`QueryPath\CSS\DOMNode` (6 sites in `DOMTraverser`), `char` as a return type (`InputStream::peek()`), `Traverser`
unqualified in `src/CSS/Traverser.php:27`. Plus ~25 `@throws mixed` / `@throws QueryPath\Exception|QueryPath\QueryPath`
tags that name non-throwables, and 4 `@property` tags that are outright parse errors
(`@property Traversable|array|SplObjectStorage matches` — missing the `$`).

One genuine signature bug: `QueryMutators::removeClass($class = false): Query` documents `@param string $class` but
defaults to `false`. The body relies on `empty($class)` for the "remove the whole attribute" path, so the default
can't simply change to `''`… except `empty('')` is also true, so it can. Needs a test either way.

### 2. Two CSS engines are live, and the legacy one is 1466 lines

`CSS\DOMTraverser` (899 lines) is what `find()` uses. `CSS\QueryPathEventHandler` (1466 lines) is the 2009-era engine,
still reached by exactly two methods: `QueryMutators::remove()` and `QueryMutators::replaceAll()`. That means a
selector fix can land in one engine and silently not apply to the other, and every selector bug report needs a
"which path does this take?" triage step first. It is also the least-covered large file (66.7% of methods) and carries
its own defects (`src/CSS/QueryPathEventHandler.php:672`, `$item` used outside the `foreach` that defines it;
`:1039`, `count()` called as a method on an array).

### 3. Docblocks are Doxygen, and Doxygen for PHP is abandoned

`@code`/`@endcode`, `@ingroup`, `@file`, `@mainpage`, raw `<b>` HTML — roughly 100 occurrences across 11 files, most
densely in `DOMQuery.php` (32) and `QueryMutators.php` (20). `config.doxy` and `src/documentation.php` (261 lines of
pure `@mainpage` prose, no runtime code) exist only to feed it. Nothing in CI generates docs, and the `Makefile`
`docs` target points at a `doxygen` binary nobody runs. IDEs and PHPStan both ignore this markup, so the library's
best documentation is invisible to every modern tool.

### 4. Repo root is carrying PEAR-era dead weight

Tracked in git, none of it referenced by anything current:

- `package.xml`, `package_compatible.xml` — PEAR package descriptors
- `build.xml` — Phing
- `Makefile` — targets point at `test/Tests`, a directory that hasn't existed for years
- `INSTALL` — describes extracting `QueryPath-2.0-minimal.tgz`
- `clover.xml` — a coverage report generated **July 2018**, committed to the repo
- `config.doxy`, `src/documentation.php` — see above

There is also no `.gitattributes`, so `composer require gravitypdf/querypath` ships `tests/`, `examples/`, all of the
above, and the fixtures to every consumer.

### 5. Tooling is pinned to the past

- `phpunit.xml` uses the pre-9.3 schema. PHPUnit warns `Your XML configuration validates against a deprecated schema`
  on every single run.
- The **coding-standard workflow runs on PHP 7.1**. That's the language floor, not a sensible host for the linter, and
  it forces old phpcs/phpcompat versions to stay resolvable.
- `dealerdirect/phpcodesniffer-composer-installer: ^0.7.0` (current is `^1.0`), `phpcompatibility/php-compatibility: *`
  (unpinned — a transitive break lands silently).
- `phpcs.xml` targets **PSR-2**, which PHP-FIG marked deprecated in favour of PSR-12 in 2019.
- **CI bug:** `.github/workflows/unit-testing.yml` declares `matrix.php-versions` but its `include:` block sets
  `php: '8.3'`. Since the key doesn't match, that `include` appends an 11th job with `php-versions` **unset** rather
  than tagging the existing 8.3 job. The coverage upload runs on that phantom job.

### 6. Coverage is uneven in a way that blocks refactoring

Line coverage looks healthy at 88.7%, but method coverage tells the real story for the pieces this plan wants to touch:

| Class | Methods covered |
|---|---|
| `CSS\SimpleSelector` | 20% (2% of lines) |
| `Extension\QPXML` | 22% |
| `QueryPath` | 29% |
| `CSS\Parser` | 38% |
| `CSS\DOMTraverser` | 59% |
| `CSS\QueryPathEventHandler` | 67% |

`DOMTraverser` and `QueryPathEventHandler` are precisely the classes phase 6 wants to consolidate. That work is not
safe at 59%/67%.

---

## The plan

Seven phases, each a self-contained PR with its own `CHANGELOG.md` entry. Phases 1–5 are ordered by dependency;
6 depends on 5.

### Phase 1 — Safety net (no `src/` changes)

Get the instruments in place before touching anything.

1. `vendor/bin/phpunit --migrate-configuration` — silences the schema warning, stays on PHPUnit 9.
2. Add `phpstan/phpstan` to `require-dev`, `phpstan.neon` at **level 5** with a generated baseline
   (146 entries), `composer run analyse`, and a CI job on PHP 8.3. Level 5 with a baseline means *new* code is held to
   level 5 from day one while the debt is paid down deliberately in phase 3.
3. Fix the `unit-testing.yml` matrix `include` key (`php` → `php-versions`) so coverage uploads from a real job.
4. Move the coding-standard workflow to PHP 8.3.

**Verify:** suite green, phpcs green, `analyse` green against its own baseline, CI matrix shows 10 jobs not 11.

### Phase 2 — Repo hygiene (no `src/` changes)

1. Delete `package.xml`, `package_compatible.xml`, `build.xml`, `Makefile`, `INSTALL`, `clover.xml`.
   Keep `CREDITS` — it's attribution for a fork of a fork.
2. Add `.gitattributes` with `export-ignore` for `tests/`, `examples/`, `.github/`, `.claude/`, and the dotfile
   configs, so installs stop shipping them.
3. **Decision needed:** `config.doxy` + `src/documentation.php`. Either delete both and fold the prose into `README.md`
   (recommended — nothing generates Doxygen today), or keep them and add a docs job. Don't leave them in limbo.

**Verify:** `composer install` in a scratch project pulls a visibly smaller package; suite green.

### Phase 3 — Pay down the static-analysis baseline

Each fix ships with a regression test. This is where the level-0/1/2 findings from the audit get resolved.

1. Declare `protected $last = [];` and `public $length = 0;` on `DOM`, remove the duplicates from `DOMQuery`. Kills
   the PHP 9 dynamic-property hazard.
2. Add the missing `return false;` / `return '';` / `return null;` to `combineAnyDescendant()`,
   `pseudoClassValue()`, and `dataURL()`, matching what callers already assume. Test each.
3. Make `filterLambda()` and `eachLambda()` throw `\QueryPath\Exception` with a message naming the replacement
   (`filterCallback()` / `each()`) instead of fataling with `Call to undefined function create_function()`. Un-skip
   both tests and assert the exception. **This cannot be a removal in 4.x** — it's a documented public method.
4. Fix the ~40 unresolvable docblock types: `Iterable`→`iterable`, `callback`→`callable`, `DOMNode`→`\DOMNode`,
   `char`→`string`, `Traverser`→`\QueryPath\CSS\Traverser`, and the 4 malformed `@property` tags.
5. Replace the ~25 `@throws mixed` and `@throws QueryPath\Exception|QueryPath\QueryPath` tags with what the methods
   actually throw.
6. Settle `removeClass($class = false)` — change the default to `''` with a test proving `removeClass()` still strips
   the whole attribute.

Shrink the baseline as each lands. Target: baseline file empty at level 5.

**Verify:** suite green on 7.1 and 8.5 in CI; baseline strictly smaller each commit.

### Phase 4 — Docblocks that tools can read

Mechanical, file-by-file, no logic changes.

1. Doxygen → phpDocumentor + Markdown: `@code`/`@endcode` → fenced blocks, `@ingroup`/`@file` dropped, `<b>` → `**`.
2. Fill in real `@param`/`@return`/`@var` types on every method that has none, including array shapes
   (`array<string, mixed>` for the options array, which is currently untyped everywhere it's passed).
3. `Query` interface (`src/Query.php`) is 9 methods against a ~200-method public surface, with two `@method` tags
   papering over the gap. Document what it is and isn't; **don't widen it** — adding methods to a published interface
   breaks every implementor.

This is what lets PHPStan go past level 5 later without touching a single signature.

**Verify:** raise the PHPStan level one notch at a time and confirm the baseline doesn't need to grow.

### Phase 5 — Close the coverage gaps that block phase 6

1. Characterization tests for `CSS\QueryPathEventHandler` driven through `remove()` and `replaceAll()` — the two
   methods that actually reach it. Capture current behaviour, including the wrong bits.
2. Run the same selector corpus through `DOMTraverser` and record every divergence. That diff is the actual scope of
   phase 6, and right now nobody knows how big it is.
3. Lift `CSS\SimpleSelector` (2% of lines), `Extension\QPXML` (22% of methods), and `QueryPath` (29%) to something
   defensible.
4. Bump `dealerdirect/phpcodesniffer-composer-installer` to `^1.0` and pin `phpcompatibility/php-compatibility`
   to `^9.3`. Leave `yoast/phpunit-polyfills` at `^1.0` — `^2.0` adds PHPUnit 10/11 but drops PHP < 7.4, so it's
   blocked on the floor bump.

**Verify:** method coverage above 80% overall; the divergence list exists and is reviewed.

### Phase 6 — Retire the second selector engine (internal only)

Only after phase 5 quantifies the risk.

1. Point `QueryMutators::remove()` and `QueryMutators::replaceAll()` at `CSS\DOMTraverser`.
2. Mark `CSS\QueryPathEventHandler` `@deprecated` — **keep the class**, it's public API and something out there may
   construct it directly. Removal is a 5.0 item.
3. Fix any divergence the phase-5 corpus exposed, one test per fix.

Result: one engine on every code path, 1466 lines out of the maintenance surface, and selector bug triage collapses to
a single question.

### Phase 7 — Structural cleanup (behaviour-preserving)

1. `DOM::__construct()` is a 95-line polymorphic loader with an 8-branch `if`/`elseif` chain over
   `SplObjectStorage`/`DOM`/`DOMDocument`/`DOMNode`/`HTML5`/`SimpleXMLElement`/array/string/filename. Extract each
   branch to a named private method. One public constructor, same semantics, readable.
2. The `set_error_handler`/`restore_error_handler` + "emulate finally" try/catch appears at **8 sites** across
   `DOM.php` and `DOMQuery.php` — with hand-rolled `finally` emulation that predates PHP 5.5. Extract one private
   helper using a real `finally`. Watch the `$errTypes` argument, which differs between sites.
3. PSR-2 → PSR-12 in `phpcs.xml`, keeping the tab-indent and line-length overrides. Run `phpcbf`, then diff carefully
   — PSR-12 adds rules PSR-2 doesn't have, and `beStrictAboutOutputDuringTests` will catch anything that moves.
4. Add explicit visibility to `Options`' `static $options` and its four `static function`s (currently implicit
   public — adding the keyword is BC).

---

## Explicitly deferred to 5.0

Tracked here so they don't get re-litigated every time someone opens `QueryFilters.php`:

- **PHP floor.** 7.1 reached EOL in December 2019; 7.4 in November 2022. A 5.0 on `^8.0` unlocks native types
  throughout, typed properties, `str_contains`, and `match` — and would let phases 3 and 4 finish the job properly
  instead of half in docblocks. This is the single highest-leverage change available and it is a hard BC break.
- Deleting `filterLambda()`, `eachLambda()`, and the other 11 `@deprecated` methods.
- Deleting `CSS\QueryPathEventHandler`.
- Widening `Query` to reflect the real public surface.
- `yoast/phpunit-polyfills: ^2.0` for PHPUnit 10/11.

---

## Suggested order

Phases 1 and 2 are independent of everything and can land this week. 3 needs 1. 4 is parallelisable with 3 by file.
6 hard-blocks on 5. 7 can slot in anywhere after 3.

The one thing worth doing before any of it: agree the 5.0 floor-bump question, because if `^8.0` is coming within a
release or two, phase 4 should write docblocks knowing they'll become native types rather than optimising to live
forever.
