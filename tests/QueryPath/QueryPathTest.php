<?php

namespace QueryPathTests;

use QueryPath\QueryPath;

class QueryPathTest extends TestCase
{

    public function testWith()
    {
        $qp = QueryPath::with(QueryPath::XHTML_STUB);

        $this->assertInstanceOf('\QueryPath\DOMQuery', $qp);

    }

    public function testWithHTML()
    {
        $qp = QueryPath::with(QueryPath::HTML_STUB);

        $this->assertInstanceOf('\QueryPath\DOMQuery', $qp);
    }

    public function testWithHTML5()
    {
        $qp = QueryPath::withHTML5(QueryPath::HTML5_STUB);

        $this->assertInstanceOf('\QueryPath\DOMQuery', $qp);
    }

    public function testWithXML()
    {
        $qp = QueryPath::with(QueryPath::XHTML_STUB);

        $this->assertInstanceOf('\QueryPath\DOMQuery', $qp);
    }

    public function testEnable()
    {
        QueryPath::enable(DummyExtension::class);

        $qp = QueryPath::with(QueryPath::XHTML_STUB);

        $this->assertTrue($qp->grrrrrrr());

    }

}

class DummyExtension implements \QueryPath\Extension
{

    public function __construct(\QueryPath\Query $qp)
    {
        $this->qp = $qp;
    }

    public function grrrrrrr()
    {
        return true;
    }

}
