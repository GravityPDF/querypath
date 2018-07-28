<?php
/** @file
 *
 * A simple selector.
 *
 */

namespace QueryPath\CSS;

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

    public const ADJACENT          = 1;
    public const DIRECT_DESCENDANT = 2;
    public const ANOTHER_SELECTOR  = 4;
    public const SIBLING           = 8;
    public const ANY_DESCENDANT    = 16;

    public $element;
    public $ns;
    public $id;
    public $classes = [];
    public $attributes = [];
    public $pseudoClasses = [];
    public $pseudoElements = [];
    public $combinator;

    /**
     * @param $code
     * @return string
     */
    public static function attributeOperator($code): string
    {
        switch ($code) {
            case EventHandler::containsWithSpace:
                return '~=';
            case EventHandler::containsWithHyphen:
                return '|=';
            case EventHandler::containsInString:
                return '*=';
            case EventHandler::beginsWith:
                return '^=';
            case EventHandler::endsWith:
                return '$=';
            default:
                return '=';
        }
    }

    /**
     * @param $code
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
     * @return bool
     */
    public function notEmpty(): bool
    {
        return !empty($this->element)
            && !empty($this->id)
            && !empty($this->classes)
            && !empty($this->combinator)
            && !empty($this->attributes)
            && !empty($this->pseudoClasses)
            && !empty($this->pseudoElements);
    }

    public function __toString()
    {
        $buffer = [];
        try {

            if (!empty($this->ns)) {
                $buffer[] = $this->ns;
                $buffer[] = '|';
            }
            if (!empty($this->element)) {
                $buffer[] = $this->element;
            }
            if (!empty($this->id)) {
                $buffer[] = '#' . $this->id;
            }
            if (!empty($this->attributes)) {
                foreach ($this->attributes as $attr) {
                    $buffer[] = '[';
                    if (!empty($attr['ns'])) {
                        $buffer[] = $attr['ns'] . '|';
                    }
                    $buffer[] = $attr['name'];
                    if (!empty($attr['value'])) {
                        $buffer[] = self::attributeOperator($attr['op']);
                        $buffer[] = $attr['value'];
                    }
                    $buffer[] = ']';
                }
            }
            if (!empty($this->pseudoClasses)) {
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

            if (!empty($this->combinator)) {
                $buffer[] = self::combinatorOperator($this->combinator);
            }

        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return implode('', $buffer);
    }

}
