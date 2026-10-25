<?php

namespace QueryPathTests;

use RuntimeException;

/**
 * A stream wrapper that throws a non-QueryPath exception when a stream is opened.
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
