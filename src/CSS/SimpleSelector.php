<?php
/** @file
 *
 * A simple selector.
 *
 */

namespace QueryPath\CSS;

use Exception;
use QueryPath\CSS\DOMTraverser\Util;

/**
 * Models a simple selector.
 *
 * CSS Selectors are composed of one or more simple selectors, where
 * each simple selector may have any of the following components:
 *
 * - An element name (or wildcard *)
 * - An ID (#foo)
 * - One or more classes (.foo.bar)
 * - One or more attribute matchers ([foo=bar])
 * - One or more pseudo-classes (:foo)
 * - One or more pseudo-elements (::first)
 *
 * For performance reasons, this object has been kept as sparse as
 * possible.
 *
 * @since  QueryPath 3.x
 * @author M Butcher
 *
 */
class SimpleSelector
{

	public const ADJACENT = 0x1;
	public const DIRECT_DESCENDANT = 0x2;
	public const ANOTHER_SELECTOR = 0x4;
	public const SIBLING = 0x8;
	public const ANY_DESCENDANT = 0x10;

	public $element;
	public $ns;
	public $id;
	public $classes = [];
	public $attributes = [];
	public $pseudoClasses = [];
	public $pseudoElements = [];
	public $combinator;

	/**
	 * The pseudo-classes split into set-level filters and per-node tests.
	 *
	 * Which side a pseudo-class falls on is a property of the parsed selector, not of any
	 * node, so it is worked out once. Deciding it per node cost two strtolower() and two
	 * in_array() calls for every node the selector was tested against.
	 *
	 * @var array|null
	 */
	private $pseudoClassSplit;

	/**
	 * @param $code
	 *
	 * @return string
	 */
	public static function attributeOperator($code): string
	{
		switch ($code) {
			case EventHandler::CONTAINS_WITH_SPACE:
				return '~=';
			case EventHandler::CONTAINS_WITH_HYPHEN:
				return '|=';
			case EventHandler::CONTAINS_IN_STRING:
				return '*=';
			case EventHandler::BEGINS_WITH:
				return '^=';
			case EventHandler::ENDS_WITH:
				return '$=';
			default:
				return '=';
		}
	}

	/**
	 * @param $code
	 *
	 * @return string
	 */
	public static function combinatorOperator($code): string
	{
		switch ($code) {
			case self::ADJACENT:
				return '+';
			case self::DIRECT_DESCENDANT:
				return '>';
			case self::SIBLING:
				return '~';
			case self::ANOTHER_SELECTOR:
				return ', ';
			case self::ANY_DESCENDANT:
				return '   ';
			default:
				return '';
			break;
		}
	}

	public function __construct()
	{
	}

	/**
	 * Get the pseudo-classes that have to be applied to a whole result set.
	 *
	 * The jQuery positional pseudo-classes (:eq(), :first, :last, :lt(), :gt(),
	 * :odd, :even) filter an ordered result set rather than testing an
	 * individual node, so a traverser must apply these after it has collected
	 * its matches. The same is true of :not(), :has() and :matches() when their
	 * argument contains one.
	 *
	 * They are returned in the order they were written, which is the order they
	 * must be applied in.
	 *
	 * @return array
	 * @see    \QueryPath\CSS\DOMTraverser\Util::POSITIONAL_PSEUDO_CLASSES
	 */
	public function setFilterPseudoClasses(): array
	{
		$split = $this->splitPseudoClasses();

		return $split['set'];
	}

	/**
	 * The pseudo-classes that are tested against an individual node.
	 *
	 * The complement of setFilterPseudoClasses(): everything that is not a filter over the
	 * ordered result set.
	 *
	 * @return array
	 */
	public function nodePseudoClasses(): array
	{
		$split = $this->splitPseudoClasses();

		return $split['node'];
	}

	/**
	 * Split the pseudo-classes into set-level filters and per-node tests, once.
	 *
	 * @return array
	 */
	private function splitPseudoClasses(): array
	{
		if ($this->pseudoClassSplit !== null) {
			return $this->pseudoClassSplit;
		}

		$split = ['set' => [], 'node' => []];
		foreach ($this->pseudoClasses as $pseudoClass) {
			$value = isset($pseudoClass['value']) ? $pseudoClass['value'] : null;
			$key   = Util::isSetFilterPseudoClass($pseudoClass['name'], $value) ? 'set' : 'node';

			$split[$key][] = $pseudoClass;
		}

		$this->pseudoClassSplit = $split;

		return $split;
	}

	/**
	 * Whether this simple selector carries any set-level pseudo-class.
	 *
	 * @return bool
	 */
	public function hasSetFilterPseudoClasses(): bool
	{
		return count($this->setFilterPseudoClasses()) > 0;
	}

	/**
	 * @return bool
	 */
	public function notEmpty(): bool
	{
		return ! empty($this->element)
			   && ! empty($this->id)
			   && ! empty($this->classes)
			   && ! empty($this->combinator)
			   && ! empty($this->attributes)
			   && ! empty($this->pseudoClasses)
			   && ! empty($this->pseudoElements);
	}

	public function __toString()
	{
		$buffer = [];
		try {
			if (! empty($this->ns)) {
				$buffer[] = $this->ns;
				$buffer[] = '|';
			}
			if (! empty($this->element)) {
				$buffer[] = $this->element;
			}
			if (! empty($this->id)) {
				$buffer[] = '#' . $this->id;
			}
			if (! empty($this->attributes)) {
				foreach ($this->attributes as $attr) {
					$buffer[] = '[';
					if (! empty($attr['ns'])) {
						$buffer[] = $attr['ns'] . '|';
					}
					$buffer[] = $attr['name'];
					if (! empty($attr['value'])) {
						$buffer[] = self::attributeOperator($attr['op']);
						$buffer[] = $attr['value'];
					}
					$buffer[] = ']';
				}
			}
			if (! empty($this->pseudoClasses)) {
				foreach ($this->pseudoClasses as $ps) {
					$buffer[] = ':' . $ps['name'];
					if (isset($ps['value'])) {
						$buffer[] = '(' . $ps['value'] . ')';
					}
				}
			}
			foreach ($this->pseudoElements as $pe) {
				$buffer[] = '::' . $pe;
			}

			if (! empty($this->combinator)) {
				$buffer[] = self::combinatorOperator($this->combinator);
			}
		} catch (Exception $e) {
			return $e->getMessage();
		}

		return implode('', $buffer);
	}
}
