QueryPath Changelog
===========================

# Unreleased changes

- On PHP 8.0 and later, `filterLambda()` and `eachLambda()` now throw a `QueryPath\Exception` that names their replacements, `filterCallback()` and `each()`, instead of an `Error` about the undefined `create_function()` (issue #81). They still work on PHP 7.1–7.4 and will be removed in 5.0
- **Behaviour change.** Fix the jQuery positional pseudo-classes (`:eq()`, `:first`, `:last`, `:lt()`, `:gt()`, `:odd`, `:even`, and the `:nth()` alias of `:eq()`), which indexed an element's siblings instead of the matched set (issue #66). They are now applied, zero-indexed and in document order, to the set the selector matched, as in jQuery. Most visibly, `:eq(0)` now matches the first result instead of never matching. `:lt()`, `:gt()`, `:eq()` and `:nth()` are zero-indexed where they were previously one-indexed, `:odd`/`:even` now index the matched set rather than sibling position, and a negative index counts back from the end of the set (`:eq(-1)` is the last match). The CSS structural pseudo-classes (`:nth-child()`, `:nth-of-type()`, `:first-child`, `:last-child`, ...) are unchanged and still count siblings, one-indexed
- Make `QueryPath\CSS\QueryPathEventHandler`, the legacy engine still used by `remove()` and `replaceAll()`, agree with `QueryPath\CSS\DOMTraverser` on all of the positional pseudo-classes. `remove('li:first')` and `find('li:first')` now select the same element
- Support positional pseudo-classes on any simple selector in a chain, not just the last one: `ul:first li` selects the list items of the first `ul`, and each comma-separated group is filtered independently before the union is returned in document order
- Support positional pseudo-classes as the argument to `:not()`, `:has()` and `:matches()`, so the common `li:not(:first)` idiom now excludes the first match rather than every match
- `QueryPath\CSS\DOMTraverser\PseudoClass::elementMatches()` now throws `QueryPath\CSS\NotImplementedException` for the positional pseudo-classes. They cannot be answered for a node in isolation; use a `Traverser`, which applies them after traversal
- **Breaking behaviour change:** `DOMQuery::is()` now behaves like jQuery's `.is()`. It tests the elements held in the current match set and returns `true` when at least one of them matches the selector. Previously it ran a descendant search, so `html5qp('<p><span>foo</span></p>', 'p')->is('span')` returned `true`. Use `has()` if you need the old "does the set contain something matching this selector" behaviour (#51)
- `DOMQuery::is()` no longer raises a fatal error when the match set contains non-element nodes (text nodes, comments, processing instructions). Those nodes cannot match a CSS selector, so they are skipped (#51)
- Fix `parents($selector)` so the selector filters the ancestors themselves, instead of matching any ancestor that merely *contains* an element matching the selector. `qp($xml, 'Demographics > Age > Name')->parents('Demographics')` now returns only `<Demographics>`, matching jQuery (#62)
- Apply the same fix to the other selector-filtered traversal methods in `QueryPath\Helpers\QueryFilters`: `parent()`, `parents()`, `parentsUntil()`, `closest()`, `next()`, `nextAll()`, `nextUntil()`, `prev()`, `prevAll()`, `prevUntil()`, `siblings()`, and `not()` (#62)
- **Behaviour change:** `parents()` and `parentsUntil()` now return their results in reverse document order with duplicates removed, as jQuery does. Previously a set built from more than one starting element was grouped by starting element (#62)
- **Breaking behaviour change:** `DOMQuery::filter()` now narrows the match set to the members that match the selector, as jQuery does, instead of keeping any element that merely *contains* a match. `qp($file, 'inner')->filter('li')` returned both `<inner>` elements and now returns none. Use `has()` for the old behaviour
- **Breaking behaviour change:** `find()` now searches descendants only, as jQuery's `.find()` does. A node already in the match set is no longer a candidate for its own selector, so `qp($xml, '#a1')->find('a')`, `find('#a1')` and `find('.c')` no longer return `#a1` itself. The document element remains matchable, because `qp()` seeds the match set with it rather than with the document. Use `filter()` to match the nodes in hand. `detach($selector)`, which uses `find()`, follows suit. `remove($selector)` and `replaceAll($selector)` still use the legacy engine and still match the nodes in hand, so `qp($xml, 'p')->remove('p')` removes the `<p>` elements while `detach('p')` detaches nothing (#126)
- Fix `children($selector)`, which resolved `:scope` against each child rather than the document element, so `children(':scope')` matched every child
- `filter()` and `children($selector)` now evaluate the selector against the whole candidate set in a single pass, rather than one node at a time. A per-node pass cannot evaluate a selector describing a position within the set, since each node is the only member of its own one-element set
- `nextAll()` and `prevAll()` stop walking at the first sibling already collected from an earlier starting element, rather than re-walking to the end of the list. On a 4,000-item list `nextAll()` fell from about 430 ms to under 10 ms
- Reorganise, modernise, and repair the `examples/` directory. Each example now lives in its own subdirectory with an `index.php`, and the full set is indexed in `examples/quickstart-guide.md`
- Convert the remaining legacy examples: `simple_example.php`, `techniques.php`, `svg.php`, `rss.php`, `odt.php`, `parse_php.php`, and `sparql.php`
- Fix examples that no longer ran: send a `User-Agent` where remote hosts now require one, resolve paths relative to the example rather than the working directory, and stop relying on the removed `qp.php` autoloader and the PHP 8 incompatible `eachLambda()`
- Rewrite `examples/quickstart-guide.md`, which documented an autoloader and a set of Composer caveats that no longer apply
- Remove unused example fixtures (`The_Beatles.rdf`, `testGrid.html`, `out.svg`) and generated output
- Add `ext-dom` and `ext-simplexml` to the `composer.json` requirements, and `ext-zip` to the dev requirements
- Add `QueryPathTests\ExamplesTest`, which runs every offline example on each supported PHP version and fails if one stops working. The examples that call third-party services are run by the new `Examples` workflow, weekly and whenever an example changes
- Rewrite the cURL example against the PubMed E-utilities API. MusicBrainz throttles by IP address, which made the example unusable from any shared address
- Add `composer run test:examples` (and `test:examples:network`) to run the examples locally
- Fix processing instructions gaining an extra `?` each time an HTML-parsed document was serialized, so `<?php echo $title; ?>` came back out of `html()`, `innerHTML()`, `innerXML()`, `innerXHTML()`, `xml()`, `html5()`, `innerHTML5()`, and `writeXML()` as `<?php echo $title; ??>`. libxml's HTML parser keeps the closing `?` as part of the node's data, unlike its XML parser and the Masterminds HTML5 parser, so QueryPath now strips it on load. `DOMProcessingInstruction::$data` consequently no longer has a stray `?` on the end for documents read with `htmlqp()` or `qp()` on an `.html` file. Note that this normalisation applies only to documents QueryPath parses itself, and that taking the underlying `DOMDocument` out of QueryPath and calling libxml's own `saveHTML()` on it will emit `<?php ... >` without the terminator, since libxml's HTML serializer never adds one
- With libxml2 2.14+, `htmlqp()` parses `<?php ... ?>` as a comment (`<!--?php ... ?-->`) and no longer throws `QueryPath\ParseException` on malformed HTML. Use `html5qp()` to keep processing instructions
- Add `QueryPath\Document`, a `DOMDocument` subclass QueryPath parses into. The type is how QueryPath records that a document's processing instruction data does not carry the closing `?`, which cannot be determined by inspecting the document afterwards. It travels with the document, so every route to a second `DOMQuery` over one document -- iteration, `add()`, `remove()`, `replaceAll()`, `branch()`, `QueryPath::with()`, and the bundled extensions -- serializes it correctly. A `DOMDocument` supplied by the caller is a plain `DOMDocument`, makes no such promise, and is still serialized exactly as it was handed over
- Fix `QPXML::pi($prefix)` always returning `NULL` with an "Undefined property" warning instead of the matching processing instruction's text (issue #79)
- Fix fatal error when running a CSS selector against a match set that contains non-element nodes (text, comment, CDATA or processing instruction), as `contents()` returns. Those nodes now simply do not match, instead of calling element-only DOM methods on them
- Fix fatal error in `attr()`, `addClass()`, `removeClass()`, `css()`, `hasClass()`, `hasAttr()` and `removeAttr()` when the match set contains non-element nodes (text, comment, CDATA or processing instruction), as `contents()` returns (issue #98). As in jQuery, those nodes are skipped, and the `attr()` and `css()` getters return `NULL` when the first match is not an element
- Fix `:nth-child()`, `:nth-last-child()`, `:nth-of-type()` and `:nth-last-of-type()` taking quadratic time on wide sibling lists (issue #84). Each sibling list is now indexed once per query, shared with nested `:not()` and `:has()` queries and across the per-element queries `filter()` and `children()` run, instead of being walked for every element. `tr:nth-child(2n)` on a 4000-row table drops from about 320 ms to 4 ms
- Fix `find('a > b')` throwing a `TypeError` when the left-hand side matched the root element, whose parent is the document rather than an element
- Fix `QPXML::createElement()` emitting an "Only variables should be passed by reference" notice for a namespace-prefixed element name without an explicit namespace URI (issue #82)
- Fix the `:text` pseudo-class so it matches jQuery: it selects `input` elements whose `type` attribute is absent or is `text` (case-insensitively). It never indicated, and still does not indicate, whether a node is a text node
- `find('*')` now matches the nodes in the match set as well as their descendants, so that a selector can be tested against an element already in hand
- Fix `QPXML::createElement()` throwing "Undefined namespace" for a prefixed element name whose prefix is declared on the matched element or an ancestor below the document element. The prefix is now resolved against the namespaces in scope on the first matched element (issue #94)
- **Behaviour change.** `firstChild()` now returns the first child element of every matched element, as `lastChild()` already did, instead of only that of the first matched element (issue #78). Matches with no child elements contribute nothing. Use `firstChild()->first()` for the previous result
- Fix `detach($selector)` ignoring its selector and detaching the current match set instead (issue #76). It now detaches only the matches of `$selector`, as `remove($selector)` does, and leaves the current match set in the document
- `writeHTML5()` now returns the `DOMQuery` like the other `write*()` methods, and throws `QueryPath\IOException` instead of a `TypeError` when the file cannot be written (issue #80)
- Fix `QueryPath\ParseException` and `QueryPath\IOException` losing the PHP warning they were raised from (issue #91). The message was the error number, as in `2 (/path/to/src/DOMQuery.php: 1343)`, and the code was always `0`. The message now carries the warning text and the code is the PHP error level (for example `E_WARNING`), so code that matches on the old message text will need updating
- Fix `tag()` emitting an "Undefined property" warning and returning `NULL` when the first match is not an element, such as a text, comment or processing instruction node returned by `contents()`. It now returns an empty string, as it does for an empty match set (issue #93)
- Fix QueryPath's error handler staying installed for the rest of the request when an exception other than a `QueryPath\Exception` (a `TypeError`, or one thrown by a stream wrapper, for example) escaped from parsing a document, inserting markup, or `writeXML()`/`writeHTML()` (issue #95)
- Fix `writeHTML()` throwing `QueryPath\ParseException` instead of `QueryPath\IOException` when the file cannot be written, as `writeXML()` and `writeHTML5()` do (issue #92). `IOException` extends `ParseException`, so existing `catch (ParseException $e)` blocks still catch it
- Fix `html()` and `writeHTML()` dropping the closing `?` of a processing instruction in an `html5qp()` document, so `<?php echo $title; ?>` came out as `<?php echo $title; >` (issue #86). `html5qp()` documents are now `QueryPath\Document` instances, like those from `qp()` and `htmlqp()`
- Fix `css()` merging the inline styles of every matched element and writing the combined result back to all of them. The new declarations are now merged into each element's own `style` attribute (issue #77)
- Fix `not()` keeping only the given nodes, instead of removing them, when passed an `SplObjectStorage` such as `get(null, true)` returns (issue #75)
- Fix selectors with two or more levels of nested parentheses, such as `li:not(:not(:nth-child(1)))` or `ul:has(li:not(:nth-child(1)))`, throwing "Recursion error detected" (issue #120). The scanner skipped the character after every nested argument, so the outer argument never saw its closing paren. This also stops `li:not(:nth-child(1)) b` from folding ` b` into the `:not()` argument, and lets quoted arguments contain parentheses, as in `:contains("a)b")`
- Fix `QueryPath::VERSION` and `QueryPath::VERSION_MAJOR` still reporting `3.2.2` and `3`. They now report `4.2.0` and `4`, and both are deprecated in favour of `\Composer\InstalledVersions::getPrettyVersion('gravitypdf/querypath')`, which cannot drift from the installed version (issue #83)

# 4.2.0

- Add PHP 8.5 support

# 4.1.0

- Update composer.json to mark library as PHP 8.4 compatible
- Use `\QueryPath\CSS\DOMTraverser\Util::parseAnB()` in `\QueryPath\CSS\QueryPathEventHandler` class to parse the `:nth-child(an+b)` syntax
- Deprecate protected method `\QueryPath\CSS\QueryPathEventHandler::parseAnB()` in favor of public static method `\QueryPath\CSS\DOMTraverser\Util::parseAnB()`

# 4.0.1

- Only define global functions qp(), htmlqp(), and html5qp() if they haven't been defined already.
- Fix for :nth-child(n+B) to select B-th and all following elements
- Fix for :nth-child(-n+B) to select first B elements
- Update PHPUnit Test Suite to use @dataProvider in testPseudoClassNthChild() to reduce code repetition
- Fix error when getting parents() for HTML elements

# 4.0.0

- Reverse logic in DomQuery::html5() so that DomQuery::html5() returns the content of the current match, and DomQuery::html5('') replaces the content of the current matches. This matches the existing logic used in DomQuery::html().
- Return DOMQuery object if QueryMutators::wrapAll() has no matches (instead of null). This aligns the method with the Docblock return type.

# 3.2.3

- Add PHP 8.3 Support
- Fix type mismatch for classes that implement the "Query" interface

# 3.2.2

- Add PHP 8.2 Support

# 3.2.1

- Fix QueryPath\QueryPathIterator::current() deprecation notice in PHP8.1

# 3.2.0

- Fixes a number of type-related errors on PHP 8.1
- Update PHPUnit Test Suite to pass from PHP7.1 to 8.1
- Add GitHub Actions to run PHPUnit for all PRs
- Removes composer.lock file to ensure the correct PHPUnit is installed in GitHub Actions. Since this is a library this is generally the accepted practice.
- Removed the Faker dependency (it didn't appear to be used in the codebase and has been sunset).
- Apply PSR-2 Linter to Codebase, using tabs instead of spaces
- Rename QueryPath fork and prepare for publishing on Packagist

# 3.1.4

- Added return types to getIterator() and count() for compatibility with PHP 8.1

# 3.1.3

- Add DOMQuery traits - dividing mutators, filters, checks

# 3.1.2

- Add format extension + test coverage + readme

# 3.1.1

- Fix juggling equations, return types in Utils

# 3.1.0

Transform library for PHP>=7.1 support + essential bug-fixes

- Add strict types passed for parameters
- Add return types scalar/boxed
- Refactor code to OOP (DRY/KISS/SOLID/YAGNI) style - minor to be continued...
- Remove redundant code
- Remove redundant ops
- Fix bugs with equations
- Fix juggling equations
- Fix priority ops in flow structs
- Remove unused vars from stack

# 3.0.4
- Addition of namespace fetching method ns().
- Various fixes
- Basic support for HTML5 via Masterminds\HTML5
- Fixed #164

# 3.0.3
- Bug #141: Fixed :gt and :lt pseudoclasses (thanks to lsv)
- Bug #124: Fixed inheritance issue with late static binding (via noisan)
- Bug #126: text(string) was not updating all matched elements (via noisan)
- Bug #127: wrapInner() was mangling the HTML if matched element set was greater than 1 (via noisan)
- Bug #128: Improved jQuery compatibility with wrap*() *pend(), before(), after(), etc. (via noisan)

# 3.0.2
- Bug #112: children() was not correctly restricting filters.
- Bug #108: QueryPath\Query interface was too restrictive. (via mkalkbrenner)
- Feature #106: :contains() is now case-insensitive. (via katzwebservices)

# 3.0.1
- Issue #100: qp.php divided into qp.php and qp_functions.php. Composer now includes qp(). (via hakre)
- Issue #102: Added QueryPath.VERSION_MAJOR, changed QueryPath.VERSION
- Issue #97: Rewrite children() and filter() to be a little more efficient.

# 3.0.0
- **REALLY IMPORTANT CHANGE:** To match jQuery, and to correctly implement
  a bottom-up parser, the following will no longer work:
  $qp($html, 'li')->find(':root ul');
  You can no longer use find() to ever, ever move UP the document true
  (like back to the root from somewhere in the tree). You MUST use
  top() to do this now. This is how jQuery works, and making this
  minor change makes things much faster.

- **REALLY IMPORTANT CHANGE:** Many "destructive" operations now return a
  new QueryPath object. This mirrors jQuery's behavior.

  THAT MEANS: find() now works like jQuery find(), branch() is 
  deprecated. findInPlace() does what find() used to do.

- removeAll() now works as it does in recent jQuery. Thanks to GDMac
  (issues #77 #78) for the fix.
- Refactored to use namespaces.
- Refactored to be SPR-0 compliant.
- Now uses Composer.
- The traversal mechanism is now bottom-up, which means Querypath is
  faster.
- Issue #83: Fixed broken forloop in techniques.php. Thanks to BillOrtell for
  the fix.
- ID-based searches no longer guarantee that only one element will be returned.
  This accomodates XML documents that may use 'id' in a way different than
  HTML.
- The base CSS Traverser is now optimized for selectors that use ID or
  class, but no element.
- Pseudo-element behavior has been rewritten to better conform to the
  standard. Using a pseudo-element no longer changes the match. Rather,
  it checks to see if the condition is met by the present element, and
  returns TRUE if it does. This means we do not need special case logic
  to deal with text fragments.
- :x-root, :x-reset, and :scope are now implemented using the
  same algorithm (they are, in other words, aliases). Since
  :scope is part of CSS 4, you should use that.
- Support for the following CSS 4 Selectors featues has been added:
  - :matches() pseudoclass, which behaves the way :has() behaves.
  - :any-link
  - :local-link (with some restrictions, as it does not know what the real
    URL of the document is).
  - :scope (see above)
- Traversing UP the DOM tree with find() is no longer allowed. Use top().
- :first is an alias of :nth(1) instead of :first-of-type. This follows
  jQuery now.
- eachLambda() is now deprecated. It WILL be removed in the future.
- **Extensions:** QPList, QPTPL, and QPDB have all been moved to the new 
  project QueryPath-Ext.

# 2.1.3

- QueryPath Templates have gotten an overhaul from TomorrowToday (Issue #59).
   Templates now support attributes.

# 2.1.2:

- Fixed the case where remove() caused an error when no items were found
   to remove (issue #63). Thanks marktheunissen for the bug report and fix.
- New XML extensions to deal with adding namespaced elements (#64). Thanks to
   theshadow for contributing an entire extension, and to farinspace for
   detailed experiments with QP and XML namespaces.
- The adjacent CSS selector has been modified to ignore text elements. This
   seems to be inline with the spec, but I am not 100% sure. Thanks to
   fiveminuteargument for the patch.

# 2.1.1:

- The xhtml() and writeXHTML() methods now correctly escape JS/CSS and also correctly
   fold some tags into unaries will keeping other empty tags. See issues #10, #47. 
   Thanks to Alex Lawrence for his input.
- The method document() has been added. Thanks to Alex Lawrence for suggesting this 
   addition.
- The fetch_rss.php example created broken HREFs in some cases. Thanks to yaph for
   the patch.
- The xpath() method now supports setting default namespaces. Thanks to Xavier Prud'homme
   for a patch.
- The remove() method was fixed (issue #55) to now correctly return a QueryPath with 
   just the removed nodes, while not altering the base QueryPath object. Thanks to MarcusX
   for finding and reporting the problem.
- Added childrenText() convenience method. (Safe -- no changes to existing functions.)
   Thanks to Xavatar for suggestion and proofing initial code.
- Fixed bad character stripping in htmlqp() (Issue #58, #52) so that meaningful whitespace
   is no longer stripped prior to parsing. Thanks to NortherRaven for detailed report
   and help testing and debugging.
- Fixed broken :nth-of-type pseudo-class (Issue #57). Thanks to NorthernRaven for the 
   detailed report and help debugging.
- Fixed broken an+b rule handling in the special case '-n+b'. Thanks to NorthernRaven for
   reporting and helping out.
- Xinclude support has been added via the xinclude() method on QueryPath. Thanks to Crell
   for the suggestion and to sdboyer for help (Issue #50).
- QueryPath now implements Countable, which means you can do `count(qp($xml, 'div'))`. The
   size() function has been marked deprecated.
- is() now takes both DOMNodes and Traversables (including QueryPath) as an argument. See
   issue #53. 
- The dirty_html.php example (contributed by Emily Brand, thanks!) is now fixed. Thanks to
   MartyIX for tracking down the issue (#59).
- NEW METHOD: document() returns the DOMDocument
- BUG FIX: Issue #10 has been re-fixed to correctly collapse certain empty tags.
- BUG FIX: Issue #10 has been re-fixed to correctly escape JavaScript for browsers.
- BUG FIX: Issue #47 has been fixed to only remove XML declaration, but leave DOCTYPE.
- NEW ARGUMENT: xpath() now supports $options, which includes the ability to set a namespace.

# 2.1.0:
Big Changes:

- There is now an `htmlqp()` function that parses crufty HTML in a far
more reliable way than `qp()`. Use this instead of any variant of 
the older `@qp()` setup.
- The API has been brought into alignment with jQuery 1.4. See 
API-2.1.0 for details.
- This release was driven substantially by eabrand's GSOC 2010 
contributions. Thanks, Emily!
- There are now Phar and PEAR packages available. Got to 
http://pear.querypath.org for PEAR packages.
- The minimal QP distribution is no longer minified, as it reportedly
causes XDebug to crash.
- Data URs are now supported. QueryPath can now embed images directly
into HTML and XML this way.
- Documentation is now in Doxygen instead of PhpDocumentor. Thanks
to Matt Farina and Kevin O'Brien for their input.

## New Functions
- The `htmlqp()` method has been added for parsing icky HTML. Use
  this for web scraping.

## Modified Functions
- The qp() function now supports the following new options:
    - convert_to_encoding
    - convert_from_encoding
    - strip_low_ascii
    - use_parser

## New Methods
- attach()/detach()
- has()
- emptyElement()
- even()/odd()
- first()/last()
- firstChild()/lastChild()
- nextUntil()/prevUntil()
- parentsUntil()
- encodeDataURL()
- dataURL()
- filterPreg()
- textBefore()/textAfter()

## Modified Methods
- css() has been changed to allow subsequent calls
  to modify the style attribute (issue #28)
- attr() has been changed. If it is called with no
  arguments, it now returns all attributes.

## New CSS Selectors Behavior

- :contains-exactly() performs as :contains() used to perform.

## Modified CSS Selectors Behavior

- The star operator (*) is now non-greedy, per spec. Before, the star would match
  any descendants. Now it will only match children.
- :contains() now does substring matching instead of exact matching. This conforms
  to jQuery's behavior.
- Quotes are now checked carefully before being stripped from pseudo-class values.
- Issue #40 identified a potential infinite looping problem on poorly formed selectors.
  This was fixed.
