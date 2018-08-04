<?php

namespace QueryPath\Extension;

use QueryPath\DOMQuery;
use QueryPath\Extension;
use QueryPath\Query;
use QueryPath\Exception;

/**
 * A QueryPath extension that adds extra methods for formatting node values.
 *
 * This extension provides two methods:
 *
 * - format()
 * - formatAttr()
 *
 * Usage:
 * <code>
 * <?php
 * QueryPath::enable('Noi\QueryPath\FormatExtension');
 * $qp = qp('<?xml version="1.0"?><root><item score="12000">TEST A</item><item score="9876.54">TEST B</item></root>');
 *
 * $qp->find('item')->format(function ($text) {
 *     return ucwords(strtolower($text));
 * });
 * $qp->find('item')->formatAttr('score', 'number_format', 2);
 *
 * $qp->writeXML();
 * </code>
 *
 * OUTPUT:
 * <code>
 * <?xml version="1.0"?>
 * <root>
 *   <item score="12,000.00">Test A</item>
 *   <item score="9,876.54">Test B</item>
 * </root>
 * </code>
 *
 * @see FormatExtension::format()
 * @see FormatExtension::formatAttr()
 *
 * @author Akihiro Yamanoi <akihiro.yamanoi@gmail.com>
 */
class Format implements Extension
{
    protected $qp;

    public function __construct(Query $qp)
    {
        $this->qp = $qp;
    }

    /**
     * Formats the text content of each selected element in the current DOMQuery object.
     *
     * Usage:
     * <code>
     * <?php
     * QueryPath::enable('Noi\QueryPath\FormatExtension');
     * $qp = qp('<?xml version="1.0"?><root><div>Apple</div><div>Orange</div></root>');
     *
     * $qp->find('div')->format('strtoupper');
     * $qp->find('div')->format(function ($text) {
     *     return '*' . $text . '*';
     * });
     *
     * $qp->writeXML();
     * </code>
     *
     * OUTPUT:
     * <code>
     * <?xml version="1.0"?>
     * <root>
     *   <div>*APPLE*</div>
     *   <div>*ORANGE*</div>
     * </root>
     * </code>
     *
     * @param callable $callback The callable to be called on every element.
     * @param mixed $args        [optional] Zero or more parameters to be passed to the callback.
     * @param null $additional
     * @return DOMQuery The DOMQuery object with the same element(s) selected.
     * @throws Exception
     */
    public function format($callback, $args = null, $additional = null): Query
    {
        if (isset($additional)) {
            $args = func_get_args();
            array_shift($args);
        }

        $getter = function ($qp) {
            return $qp->text();
        };

        $setter = function ($qp, $value) {
            $qp->text($value);
        };

        return $this->forAll($callback, $args, $getter, $setter);
    }

    /**
     * Formats the given attribute of each selected element in the current DOMQuery object.
     *
     * Usage:
     * <code>
     * QueryPath::enable('Noi\QueryPath\FormatExtension');
     * $qp = qp('<?xml version="1.0"?><root><item label="_apple_" total="12,345,678" /><item label="_orange_" total="987,654,321" /></root>');
     *
     * $qp->find('item')
     *     ->formatAttr('label', 'trim', '_')
     *     ->formatAttr('total', 'str_replace[2]', ',', '');
     *
     * $qp->find('item')->formatAttr('label', function ($value) {
     *     return ucfirst(strtolower($value));
     * });
     *
     * $qp->writeXML();
     * </code>
     *
     * OUTPUT:
     * <code>
     * <?xml version="1.0"?>
     * <root>
     *   <item label="Apple" total="12345678"/>
     *   <item label="Orange" total="987654321"/>
     * </root>
     * </code>
     *
     * @param string $attrName   The attribute name.
     * @param callable $callback The callable to be called on every element.
     * @param mixed $args        [optional] Zero or more parameters to be passed to the callback.
     * @param null $additional
     * @return DOMQuery The DOMQuery object with the same element(s) selected.
     * @throws Exception
     */
    public function formatAttr($attrName, $callback, $args = null, $additional = null): Query
    {
        if (isset($additional)) {
            $args = array_slice(func_get_args(), 2);
        }

        $getter = function ($qp) use ($attrName) {
            return $qp->attr($attrName);
        };

        $setter = function ($qp, $value) use ($attrName) {
            return $qp->attr($attrName, $value);
        };

        return $this->forAll($callback, $args, $getter, $setter);
    }

    /**
     * @param $callback
     * @param $args
     * @param $getter
     * @param $setter
     * @return Query
     * @throws Exception
     */
    protected function forAll($callback, $args, $getter, $setter): Query
    {
        [$callback, $pos] = $this->prepareCallback($callback);
        if (!is_callable($callback)) {
            throw new Exception('Callback is not callable.');
        }

        $padded = $this->prepareArgs($args, $pos);
        foreach ($this->qp as $qp) {
            $padded[$pos] = $getter($qp);
            $setter($qp, call_user_func_array($callback, $padded));
        }

        return $this->qp;
    }

    /**
     * @param $callback
     * @return array
     */
    protected function prepareCallback($callback)
    {
        if (is_string($callback)) {
            [$callback, $trail] = $this->splitFunctionName($callback);
            $pos = (int)$trail;
        } elseif (is_array($callback) && isset($callback[2])) {
            $pos = $callback[2];
            $callback = array($callback[0], $callback[1]);
        } else {
            $pos = 0;
        }
        return array($callback, $pos);
    }

    /**
     * @param string $string
     * @return array[]|false|string[]
     */
    protected function splitFunctionName(string $string)
    {
        // 'func_name:2', 'func_name@3', 'func_name[1]', ...
        return preg_split('/[^a-zA-Z0-9_\x7f-\xff][^\d]*|$/', $string, 2);
    }

    /**
     * @param $args
     * @param $pos
     * @return array
     */
    protected function prepareArgs($args, $pos): array
    {
        $padded = array_pad((array) $args, (0 < $pos) ? $pos - 1 : 0, null);
        array_splice($padded, $pos, 0, array(null)); // insert null as a place holder
        return $padded;
    }
}