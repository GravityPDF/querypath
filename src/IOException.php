<?php
/**
 * @file
 *
 * General IO exception.
 */

namespace QueryPath;

/**
 * Indicates that an input/output exception has occurred.
 *
 * @ingroup querypath_core
 */
class IOException extends ParseException
{

	public static function initializeFromError($errno, $errstr, $errfile, $errline, $context = null)
	{
		$class = __CLASS__;
		throw new $class($errno, (int) $errstr, $errfile, $errline);
	}
}
