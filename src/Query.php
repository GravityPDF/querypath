<?php

namespace QueryPath;

/**
 * Interface Query
 *
 * @package QueryPath
 *
 * @method after($data)
 * @method before($data)
 */
interface Query
{

    public function __construct($document = NULL, $selector = NULL, $options = []);

    public function find($selector);

    public function top($selector = NULL);

    public function next($selector = NULL);

    public function prev($selector = NULL);

    public function siblings($selector = NULL);

    public function parent($selector = NULL);

    public function children($selector = NULL);
}
