<?php

namespace QueryPathTests\Extension;


use QueryPath\QueryPath;
use QueryPathTests\TestCase;
use QueryPath\Extension\Format;

class FormatTest extends TestCase
{

    /**
     * @test
     * @throws \QueryPath\CSS\ParseException
     */
    public function it_formats_tag_text_node()
    {
        QueryPath::enable(Format::class);
        $qp = qp('<?xml version="1.0"?><root><div>_apple_</div><div>_orange_</div></root>');
        $qp->find('div')->format('strtoupper')->format('trim', '_')->format(function ($text) {
            return '*' . $text . '*';
        });

        $this->assertEquals('*APPLE**ORANGE*', $qp->get(0)->textContent);
    }

    /**
     * @test
     * @throws \QueryPath\CSS\ParseException
     */
    public function it_formats_attribute()
    {
        QueryPath::enable(Format::class);
        $qp = qp('<?xml version="1.0"?><root>' .
            '<item label="_apple_" total="12,345,678" />' .
            '<item label="_orange_" total="987,654,321" />' .
            '</root>');

        $qp->find('item')
            ->formatAttr('label', 'trim', '_')
            ->formatAttr('total', 'str_replace[2]', ',', '');

        $this->assertEquals('apple', $qp->find('item')->attr('label'));
        $this->assertEquals('12345678', $qp->find('item')->attr('total'));
    }
}