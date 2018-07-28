<?php

namespace QueryPathTests;

use \QueryPath\Options;

/**
 * @ingroup querypath_tests
 */
class OptionsTest extends TestCase
{

    public function testOptions()
    {
        $expect = ['test1' => 'val1', 'test2' => 'val2'];
        $options = ['test1' => 'val1', 'test2' => 'val2'];

        Options::set($options);

        $results = Options::get();
        $this->assertEquals($expect, $results);

        $this->assertEquals('val1', $results['test1']);
    }

    public function testQPOverrideOrder()
    {
        $expect = ['test1' => 'val3', 'test2' => 'val2'];
        $options = ['test1' => 'val1', 'test2' => 'val2'];

        Options::set($options);
        $qpOpts = qp(NULL, NULL, ['test1' => 'val3', 'replace_entities' => true])->getOptions();

        $this->assertEquals($expect['test1'], $qpOpts['test1']);
        $this->assertEquals(true, $qpOpts['replace_entities']);
        $this->assertNull($qpOpts['parser_flags']);
        $this->assertEquals($expect['test2'], $qpOpts['test2']);
    }

    public function testQPHas()
    {
        $options = ['test1' => 'val1', 'test2' => 'val2'];

        Options::set($options);
        $this->assertTrue(Options::has('test1'));
        $this->assertFalse(Options::has('test3'));
    }

    public function testQPMerge()
    {
        $options = ['test1' => 'val1', 'test2' => 'val2'];
        $options2 = ['test1' => 'val3', 'test4' => 'val4'];

        Options::set($options);
        Options::merge($options2);

        $results = Options::get();
        $this->assertTrue(Options::has('test4'));
        $this->assertEquals('val3', $results['test1']);
    }

}
