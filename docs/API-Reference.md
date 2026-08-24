# API Reference

Every public method on `QueryPath\DOMQuery`, alphabetically. Follow a link for the full entry.

The detail pages group the same methods by task:

| Page | Covers |
|---|---|
| [Traversal and Filtering](Traversal-and-Filtering.md) | Choosing which elements are selected |
| [Manipulation](Manipulation.md) | Changing the document |
| [Markup and Text](Markup-and-Text.md) | Reading and writing content |
| [Document and Utility](Document-and-Utility.md) | The match set, the DOM, options, errors |

## Reading the "Returns" column

| Value | Meaning |
|---|---|
| **new** | Returns a **new** `DOMQuery`. The object you called it on is unchanged. |
| **self** | Returns `$this`. Any change is made in place. |
| **self / value** | Setter form returns `$this`; getter form returns a value. |
| Anything else | A plain value — `string`, `int`, `bool`, `array`, `DOMDocument`. |

This distinction is the most common source of surprise for people coming from jQuery. See
[Objects are not mutated in place](Getting-Started.md#objects-are-not-mutated-in-place).

## All methods

| Method | Returns | Summary |
|---|---|---|
| [`add()`](Manipulation.md#add) | self | Query from the document root and merge the results into the match set |
| [`addClass()`](Manipulation.md#addclass) | self | Append a class to every selected element |
| [`after()`](Manipulation.md#after) | self | Insert content as a following sibling |
| [`andSelf()`](Traversal-and-Filtering.md#andself) | self | Merge the previous match set into the current one |
| [`append()`](Manipulation.md#append) | self | Insert content as the last child |
| [`appendTo()`](Manipulation.md#appendto) | self | Append the selected elements into another object |
| [`attach()`](Manipulation.md#attach) | self | Re-insert the nodes remembered by the last `detach()` |
| [`attr()`](Manipulation.md#attr) | self / value | Get or set attributes |
| [`before()`](Manipulation.md#before) | self | Insert content as a preceding sibling |
| [`branch()`](Traversal-and-Filtering.md#branch) | new | Copy the query object, keeping the same document and nodes |
| [`children()`](Traversal-and-Filtering.md#children) | new | Immediate child elements |
| [`childrenText()`](Markup-and-Text.md#childrentext) | string | Concatenated text of the subtree |
| [`cloneAll()`](Manipulation.md#cloneall) | self | Deep-clone the selected nodes and select the copies |
| [`closest()`](Traversal-and-Filtering.md#closest) | new | Nearest match, testing the element itself then its ancestors |
| [`contents()`](Traversal-and-Filtering.md#contents) | new | All immediate child nodes, including text and comments |
| [`count()`](Document-and-Utility.md#count) | int | Number of selected nodes |
| [`css()`](Manipulation.md#css) | self / string | Get or set inline style declarations |
| [`dataURL()`](Markup-and-Text.md#dataurl) | self / array | Read or write an attribute as a data URL |
| [`deepest()`](Traversal-and-Filtering.md#deepest) | new | The furthest descendants of the selected elements |
| [`detach()`](Manipulation.md#detach) | new | Remove elements and remember them for `attach()` |
| [`document()`](Document-and-Utility.md#document) | DOMDocument | The underlying document |
| [`each()`](Traversal-and-Filtering.md#each) | self | Run a callback over each node |
| [`eachLambda()`](Traversal-and-Filtering.md#eachlambda) | — | **Broken on PHP 8.** Use `each()` |
| [`emptyElement()`](Manipulation.md#emptyelement) | self | Deprecated alias of `removeChildren()` |
| [`end()`](Traversal-and-Filtering.md#end) | self | Rewind to the previous match set |
| [`eq()`](Traversal-and-Filtering.md#eq) | new | Reduce to the element at a 0-based index |
| [`even()`](Traversal-and-Filtering.md#even) | new | Elements at odd indexes — the 2nd, 4th, … |
| [`filter()`](Traversal-and-Filtering.md#filter) | new | Keep the selected elements that match a selector |
| [`filterCallback()`](Traversal-and-Filtering.md#filtercallback) | new | Keep elements for which a callback does not return `false` |
| [`filterLambda()`](Traversal-and-Filtering.md#filterlambda) | — | **Broken on PHP 8.** Use `filterCallback()` |
| [`filterPreg()`](Traversal-and-Filtering.md#filterpreg) | new | Keep elements whose text matches a regex |
| [`find()`](Traversal-and-Filtering.md#find) | new | Search descendants with a CSS selector |
| [`findInPlace()`](Traversal-and-Filtering.md#findinplace) | self | `find()`, mutating the current object |
| [`first()`](Traversal-and-Filtering.md#first) | new | Reduce to the first element |
| [`firstChild()`](Traversal-and-Filtering.md#firstchild) | new | First child element (see the known issue) |
| [`get()`](Document-and-Utility.md#get) | array / node | The raw `DOMNode`s |
| [`getIterator()`](Document-and-Utility.md#getiterator) | Traversable | Yields `DOMQuery` objects to `foreach` |
| [`getOptions()`](Document-and-Utility.md#getoptions) | array | The effective options for this object |
| [`has()`](Traversal-and-Filtering.md#has) | new | Keep elements containing a match |
| [`hasAttr()`](Manipulation.md#hasattr) | bool | Whether **every** selected element has the attribute |
| [`hasClass()`](Manipulation.md#hasclass) | bool | Whether **any** selected element has the class |
| [`html()`](Markup-and-Text.md#html) | self / string | Get or set HTML 4.01 markup, element included |
| [`html5()`](Markup-and-Text.md#html5) | self / string | Get or set HTML5 markup, element included |
| [`index()`](Document-and-Utility.md#index) | int / false | Position of a node in the match set |
| [`innerHTML()`](Markup-and-Text.md#innerhtml) | string | Child markup — an alias of `innerXML()` |
| [`innerHTML5()`](Markup-and-Text.md#innerhtml5) | string | Child markup, HTML5-serialised |
| [`innerXHTML()`](Markup-and-Text.md#innerxhtml) | string | Child markup with closing tags everywhere |
| [`innerXML()`](Markup-and-Text.md#innerxml) | string | Child markup, XML-serialised |
| [`insertAfter()`](Manipulation.md#insertafter) | self | Insert the selected elements after another object's elements |
| [`insertBefore()`](Manipulation.md#insertbefore) | self | Insert the selected elements before another object's elements |
| [`is()`](Traversal-and-Filtering.md#is) | bool | Whether any selected element matches |
| [`last()`](Traversal-and-Filtering.md#last) | new | Reduce to the last element |
| [`lastChild()`](Traversal-and-Filtering.md#lastchild) | new | Last child element of each selected element |
| [`map()`](Traversal-and-Filtering.md#map) | new | Replace the match set with a callback's return values |
| [`next()`](Traversal-and-Filtering.md#next) | new | The next sibling element |
| [`nextAll()`](Traversal-and-Filtering.md#nextall) | new | All following siblings |
| [`nextUntil()`](Traversal-and-Filtering.md#nextuntil) | new | Following siblings, stopping before a match |
| [`not()`](Traversal-and-Filtering.md#not) | new | Drop the elements that match |
| [`ns()`](Document-and-Utility.md#ns) | string | Namespace URI of the first element |
| [`odd()`](Traversal-and-Filtering.md#odd) | new | Elements at even indexes — the 1st, 3rd, … |
| [`parent()`](Traversal-and-Filtering.md#parent) | new | Immediate parent, or nearest matching ancestor |
| [`parents()`](Traversal-and-Filtering.md#parents) | new | All ancestors |
| [`parentsUntil()`](Traversal-and-Filtering.md#parentsuntil) | new | Ancestors, stopping before a match |
| [`prepend()`](Manipulation.md#prepend) | self | Insert content as the first child |
| [`prependTo()`](Manipulation.md#prependto) | self | Prepend the selected elements into another object |
| [`prev()`](Traversal-and-Filtering.md#prev) | new | The previous sibling element |
| [`prevAll()`](Traversal-and-Filtering.md#prevall) | new | All preceding siblings, in reverse document order |
| [`prevUntil()`](Traversal-and-Filtering.md#prevuntil) | new | Preceding siblings, stopping before a match |
| [`remove()`](Manipulation.md#remove) | new | Remove elements; returns the removed nodes |
| [`removeAttr()`](Manipulation.md#removeattr) | self | Remove an attribute from every selected element |
| [`removeChildren()`](Manipulation.md#removechildren) | self | Remove all child nodes — jQuery's `empty()` |
| [`removeClass()`](Manipulation.md#removeclass) | self | Remove one class, or the whole attribute |
| [`replaceAll()`](Manipulation.md#replaceall) | new | Deprecated; replace matches in another document |
| [`replaceWith()`](Manipulation.md#replacewith) | new | Replace elements; returns the removed nodes |
| [`setMatches()`](Document-and-Utility.md#setmatches) | void | Expert-level: set the match set directly |
| [`siblings()`](Traversal-and-Filtering.md#siblings) | new | All siblings of each selected element |
| [`size()`](Document-and-Utility.md#size) | int | Deprecated alias of `count()` |
| [`slice()`](Traversal-and-Filtering.md#slice) | new | A contiguous run of the match set |
| [`sort()`](Document-and-Utility.md#sort) | new | Reorder the match set, and optionally the DOM |
| [`tag()`](Document-and-Utility.md#tag) | string | Tag name of the first element |
| [`text()`](Markup-and-Text.md#text) | self / string | Get or set text content |
| [`textAfter()`](Markup-and-Text.md#textafter) | self / string | Text immediately following each element |
| [`textBefore()`](Markup-and-Text.md#textbefore) | self / string | Text immediately preceding each element |
| [`textImplode()`](Markup-and-Text.md#textimplode) | string | Each element's text, joined by a separator |
| [`toArray()`](Document-and-Utility.md#toarray) | array | The raw `DOMNode`s |
| [`top()`](Traversal-and-Filtering.md#top) | new | Select the document element |
| [`unwrap()`](Manipulation.md#unwrap) | self | Remove each element's parent |
| [`val()`](Manipulation.md#val) | self / string | Deprecated shorthand for the `value` attribute |
| [`wrap()`](Manipulation.md#wrap) | self | Wrap each element individually |
| [`wrapAll()`](Manipulation.md#wrapall) | self | Wrap all elements in one wrapper |
| [`wrapInner()`](Manipulation.md#wrapinner) | self | Wrap the children of each element |
| [`writeHTML()`](Markup-and-Text.md#writehtml) | self | Write the document as HTML 4.01 |
| [`writeHTML5()`](Markup-and-Text.md#writehtml5) | **null** | Write the document as HTML5 — does not chain |
| [`writeXHTML()`](Markup-and-Text.md#writexhtml) | self | Write the document as XHTML |
| [`writeXML()`](Markup-and-Text.md#writexml) | self | Write the document as XML |
| [`xhtml()`](Markup-and-Text.md#xhtml) | self / string | Get or set XHTML markup |
| [`xinclude()`](Document-and-Utility.md#xinclude) | self | Process XInclude directives |
| [`xml()`](Markup-and-Text.md#xml) | self / string | Get or set XML markup |
| [`xpath()`](Traversal-and-Filtering.md#xpath) | new | Run an XPath query |

## Beyond `DOMQuery`

| Where | What |
|---|---|
| [`QueryPath::*`](Document-and-Utility.md#static-entry-points) | Static factories, extension registration, `encodeDataURL()` |
| [`QueryPath\Options`](Document-and-Utility.md#querypathoptions) | Global option defaults |
| [`QueryPath\ExtensionRegistry`](Writing-Extensions.md#managing-the-registry-directly) | Extension registration internals |
| [Bundled extensions](Writing-Extensions.md#the-bundled-extensions) | `QPXML`, `QPXSL`, `Format` |

## Known issues at a glance

Behaviours verified against the current release that are likely to surprise you:

| Method / syntax | Issue |
|---|---|
| `X > *` | Raises a `TypeError`. Use [`children()`](Traversal-and-Filtering.md#children) |
| `filterLambda()`, `eachLambda()` | Raise an `Error` on PHP 8 — built on the removed `create_function()` |
| `not($splObjectStorage)` | Inverted: keeps those nodes instead of removing them. Pass an array |
| `detach($selector)` | The selector is ignored. Use `find($selector)->detach()` |
| `firstChild()` | Returns at most one node regardless of how many elements are selected |
| `css()` | Pools styles across the whole match set and writes the union to all of them |
| `writeHTML5()` | Returns `null`, so it cannot be chained |
| `remove($selector)`, `replaceAll()` | Use the [legacy selector engine](CSS-Selector-Reference.md#two-selector-engines) |
| `hasAttr()` | Returns `true` on an empty match set |
| `QueryPath::VERSION` | Still reads `3.2.2`; not the installed version |
| `odd()` / `even()` | Named by 1-based ordinal, so `odd()` returns even indexes |
