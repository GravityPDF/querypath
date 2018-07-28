<?php

namespace QueryPathTests;


/**
 * Simple utility object for use with the TestEventHandler.
 *
 * @ingroup querypath_tests
 * @group   CSS
 */
class TestEvent
{

    public const ELEMENT_ID        = 0;
    public const ELEMENT           = 1;
    public const ELEMENT_NS        = 2;
    public const ANY_ELEMENT       = 3;
    public const ELEMENT_CLASS     = 4;
    public const ATTRIBUTE         = 5;
    public const ATTRIBUTE_NS      = 6;
    public const PSEUDO_CLASS      = 7;
    public const PSEUDO_ELEMENT    = 8;
    public const DIRECT_DESCENDANT = 9;
    public const ADJACENT          = 10;
    public const ANOTHER_SELECTOR  = 11;
    public const SIBLING           = 12;
    public const ANY_ELEMENT_IN_NS = 13;
    public const ANY_DESCENDANT    = 14;

    public $type;
    public $params;

    public function __construct($event_type)
    {
        $this->type = $event_type;
        $args = func_get_args();
        array_shift($args);
        $this->params = $args;
    }

    public function eventType()
    {
        return $this->type;
    }

    public function params()
    {
        return $this->params;
    }
}