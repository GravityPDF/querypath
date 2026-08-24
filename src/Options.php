<?php
/**
 * @file
 *
 * Options management.
 */

namespace QueryPath;

/**
 * Manage default options.
 *
 * This class stores the default options applied to every QueryPath object created afterwards.
 *
 * It defines no options of its own — it is a central place to override QueryPath's own defaults.
 * Options are resolved in this order, highest priority first:
 *
 * 1. Options passed into qp() and the other factories
 * 2. Options set here
 * 3. QueryPath's built-in defaults
 *
 * ```php
 * QueryPath\Options::set(['format_output' => false]);
 * QueryPath\Options::merge(['replace_entities' => true]);
 * ```
 *
 * @see qp()
 * @see https://github.com/GravityPDF/querypath/wiki/Parser-Options
 */
class Options
{

	/**
	 * This is the static options array.
	 *
	 * Use the {@link set()}, {@link get()}, and {@link merge()} to
	 * modify this array.
	 */
	static $options = [];

	/**
	 * Set the default options.
	 *
	 * The passed-in array will be used as the default options list.
	 *
	 * @param array $array
	 *  An associative array of options.
	 */
	static function set($array)
	{
		self::$options = $array;
	}

	/**
	 * Get the default options.
	 *
	 * Get all options currently set as default.
	 *
	 * @return array
	 *  An array of options. Note that only explicitly set options are
	 *  returned. {@link QueryPath} defines default options which are not
	 *  stored in this object.
	 */
	static function get()
	{
		return self::$options;
	}

	/**
	 * Merge the provided array with existing options.
	 *
	 * On duplicate keys, the value in $array will overwrite the
	 * value stored in the options.
	 *
	 * @param array $array
	 *  Associative array of options to merge into the existing options.
	 */
	static function merge($array)
	{
		self::$options = $array + self::$options;
	}

	/**
	 * Returns true of the specified key is already overridden in this object.
	 *
	 * @param string $key
	 *  The key to search for.
	 */
	static function has($key)
	{
		return array_key_exists($key, self::$options);
	}
}
