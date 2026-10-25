<?php

namespace QueryPathTests;

use RuntimeException;

/**
 * A stream wrapper that throws when a stream is opened.
 *
 * Gives a way to raise an exception that is not a QueryPath\Exception from inside libxml's and
 * PHP's own file handling.
 */
class ThrowingStreamWrapper
{
	public const PROTOCOL = 'qpthrow';

	public $context;

	public function stream_open($path, $mode, $options, &$opened_path) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
	{
		throw new RuntimeException('Cannot open ' . $path);
	}
}
