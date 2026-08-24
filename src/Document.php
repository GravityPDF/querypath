<?php

namespace QueryPath;

use DOMDocument;

/**
 * A DOMDocument that QueryPath parsed itself.
 *
 * The type is the marker: being an instance of this class is what says a document holds to
 * QueryPath's processing instruction invariant -- that the data of a processing instruction never
 * carries the closing "?" of its "?>". Every parser QueryPath drives satisfies it, libxml's XML
 * parser and Masterminds natively and libxml's HTML parser once
 * DOM::normalizeProcessingInstructions() has run over the result.
 *
 * The invariant cannot be established by inspection after the fact, because the XML parser reading
 * `<?php $a = 1; ??>` leaves exactly the trailing "?" that the HTML parser leaves for
 * `<?php $a = 1; ?>`. Nor can it be re-established by normalising again, which would strip a "?"
 * that legitimately belongs to the content. It has to be recorded when the document is built, and
 * recording it on the document rather than on the query object means it survives every route by
 * which a second DOMQuery comes to share the same document -- iteration, add(), remove(),
 * replaceAll(), branch(), and the extensions.
 *
 * A DOMDocument supplied by the caller is a plain DOMDocument and makes no such promise, so
 * QueryPath serializes it exactly as it was handed over.
 *
 * @see DOM::normalizeProcessingInstructions()
 * @see DOMQuery::saveDocumentHTML()
 *
 * @ingroup querypath_core
 */
class Document extends DOMDocument
{
}
