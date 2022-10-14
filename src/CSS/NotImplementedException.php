<?php
/**
 * @file
 * An exception for CSS errors.
 */

namespace QueryPath\CSS;

use Exception;

/**
 * Exception thrown for unimplemented CSS.
 *
 * This is thrown in cases where some feature is expected, but the current
 * implementation does not support that feature.
 *
 * @ingroup querypath_css
 */
class NotImplementedException extends Exception
{
}
