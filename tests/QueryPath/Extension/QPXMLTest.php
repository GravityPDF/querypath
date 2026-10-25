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

	public function testCreateElementWithPrefix()
	{
		$xml = '<?xml version="1.0"?><root xmlns:x="urn:x"/>';

		// The namespace URI is looked up from the prefix of the element name.
		$element = qp($xml, 'root')->createElement('x:item')->get(0);
		$this->assertEquals('urn:x', $element->namespaceURI);
		$this->assertEquals('x:item', $element->nodeName);
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
}
