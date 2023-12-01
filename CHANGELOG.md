QueryPath Changelog
===========================

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
