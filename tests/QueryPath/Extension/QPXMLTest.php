<?php

namespace QueryPathTests\Extension;

use QueryPath\QueryPath;
use QueryPath\Extension\QPXML;
use QueryPathTests\TestCase;

/**
 * @ingroup querypath_tests
 * @group   extension
 */
class QPXMLTest extends TestCase
{

	protected $file = './tests/advanced.xml';

	public static function set_up_before_class()
	{
		QueryPath::enable(QPXML::class);
	}

	public function testCDATA()
	{
		$this->assertEquals('This is a CDATA section.', qp($this->file, 'first')->cdata());

		$msg = 'Another CDATA Section';
		$this->assertEquals($msg, qp($this->file, 'second')->cdata($msg)->top()->find('second')->cdata());
	}

	public function testComment()
	{
		$this->assertEquals('This is a comment.', trim(qp($this->file, 'root')->comment()));
		$msg = "Message";
		$this->assertEquals($msg, qp($this->file, 'second')->comment($msg)->top()->find('second')->comment());
	}

	public function testProcessingInstruction()
	{
		$this->assertEquals('This is a processing instruction.', trim(qp($this->file, 'third')->pi()));
		$msg = "Message";
		$this->assertEquals($msg, qp($this->file, 'second')->pi('qp', $msg)->top()->find('second')->pi());
	}

	public function testProcessingInstructionByPrefix()
	{
		$xml = '<?xml version="1.0"?><root><?first one?><?second two?></root>';

		$this->assertEquals('two', qp($xml, 'root')->pi('second'));
		$this->assertNull(qp($xml, 'root')->pi('missing'));

		// A processing instruction written by prefix can be read back by the same prefix.
		$this->assertEquals('Message', qp($this->file, 'second')->pi('qp', 'Message')->top()->find('second')->pi('qp'));
	}
}
