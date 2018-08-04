<?php

namespace QueryPathTests;

use QueryPath\CSS\EventHandler;


/**
 * Testing harness for the EventHandler.
 *
 * @ingroup querypath_tests
 * @group   CSS
 */
class TestEventHandler implements EventHandler
{

    public $stack;
    public $expect = [];

    public function __construct()
    {
        $this->stack = [];
    }

    public function getStack()
    {
        return $this->stack;
    }

    public function dumpStack()
    {
        print "\nExpected:\n";
        $format = "Element %d: %s\n";
        foreach ($this->expect as $item) {
            printf($format, $item->eventType(), implode(',', $item->params()));
        }

        print "Got:\n";
        foreach ($this->stack as $item) {
            printf($format, $item->eventType(), implode(',', $item->params()));
        }
    }

    public function expectsSmth($stack)
    {
        $this->expect = $stack;
    }

    public function success()
    {
        return ($this->expect == $this->stack);
    }

    public function elementID($id)
    {
        $this->stack[] = new TestEvent(TestEvent::ELEMENT_ID, $id);
    }

    public function element($name)
    {
        $this->stack[] = new TestEvent(TestEvent::ELEMENT, $name);
    }

    public function elementNS($name, $namespace = NULL)
    {
        $this->stack[] = new TestEvent(TestEvent::ELEMENT_NS, $name, $namespace);
    }

    public function anyElement()
    {
        $this->stack[] = new TestEvent(TestEvent::ANY_ELEMENT);
    }

    public function anyElementInNS($ns)
    {
        $this->stack[] = new TestEvent(TestEvent::ANY_ELEMENT_IN_NS, $ns);
    }

    public function elementClass($name)
    {
        $this->stack[] = new TestEvent(TestEvent::ELEMENT_CLASS, $name);
    }

    public function attribute($name, $value = NULL, $operation = EventHandler::IS_EXACTLY)
    {
        $this->stack[] = new TestEvent(TestEvent::ATTRIBUTE, $name, $value, $operation);
    }

    public function attributeNS($name, $ns, $value = NULL, $operation = EventHandler::IS_EXACTLY)
    {
        $this->stack[] = new TestEvent(TestEvent::ATTRIBUTE_NS, $name, $ns, $value, $operation);
    }

    public function pseudoClass($name, $value = NULL)
    {
        $this->stack[] = new TestEvent(TestEvent::PSEUDO_CLASS, $name, $value);
    }

    public function pseudoElement($name)
    {
        $this->stack[] = new TestEvent(TestEvent::PSEUDO_ELEMENT, $name);
    }

    public function directDescendant()
    {
        $this->stack[] = new TestEvent(TestEvent::DIRECT_DESCENDANT);
    }

    public function anyDescendant()
    {
        $this->stack[] = new TestEvent(TestEvent::ANY_DESCENDANT);
    }

    public function adjacent()
    {
        $this->stack[] = new TestEvent(TestEvent::ADJACENT);
    }

    public function anotherSelector()
    {
        $this->stack[] = new TestEvent(TestEvent::ANOTHER_SELECTOR);
    }

    public function sibling()
    {
        $this->stack[] = new TestEvent(TestEvent::SIBLING);
    }
}