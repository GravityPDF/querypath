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

	public function testCreateElementWithPrefixDeclaredBelowRoot()
	{
		$xml = '<?xml version="1.0"?><root xmlns:x="urn:x"><child xmlns:y="urn:y"/></root>';

		$element = qp($xml, 'child')->createElement('y:item')->get(0);
		$this->assertEquals('urn:y', $element->namespaceURI);
		$this->assertEquals('y:item', $element->nodeName);

		$element = qp($xml, 'child')->createElement('x:item')->get(0);
		$this->assertEquals('urn:x', $element->namespaceURI);
	}

	public function testCreateElementWithPrefixOutOfScope()
	{
		$xml = '<?xml version="1.0"?><root><a xmlns:y="urn:y"/><b/></root>';

		$this->expectException(\QueryPath\Exception::class);
		$this->expectExceptionMessage('Undefined namespace for: y:item');
		qp($xml, 'b')->createElement('y:item');
	}

	public function testCreateElementUsesFirstMatch()
	{
		$xml = '<?xml version="1.0"?><root><a xmlns:y="urn:a"/><b xmlns:y="urn:b"/></root>';

		$element = qp($xml, 'a, b')->createElement('y:item');
		$this->assertCount(1, $element);
		$this->assertEquals('urn:a', $element->get(0)->namespaceURI);
	}

	public function testCreateElementWithNoMatches()
	{
		$xml = '<?xml version="1.0"?><root xmlns:x="urn:x"/>';

		$this->assertCount(0, qp($xml, 'missing')->createElement('x:item'));
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
		$this->assertEquals($msg, qp($this->file, 'second')->pi('qp', $msg)->top()->find('second')->pi('qp'));
	}

	public function testProcessingInstructionByPrefix()
	{
		$xml = '<?xml version="1.0"?><root><?first one?><?second two?></root>';

		$qp = qp($xml, 'root');

		$this->assertEquals('two', $qp->pi('second'));
		$this->assertNull($qp->pi('missing'));
	}

	public function testGettersReturnNullWhenNothingFound()
	{
		$xml = '<?xml version="1.0"?><root><a>text<b><![CDATA[nested]]><!--nested--><?nested pi?></b></a></root>';

		// Only child nodes of the matches are searched, not deeper descendants.
		$qp = qp($xml, 'a');
		$this->assertNull($qp->cdata());
		$this->assertNull($qp->comment());
		$this->assertNull($qp->pi());
		$this->assertNull($qp->pi('nested'));

		$qp = qp($xml, 'missing');
		$this->assertNull($qp->cdata());
		$this->assertNull($qp->comment());
		$this->assertNull($qp->pi());
	}

	public function testGettersReturnFirstFoundAcrossMatches()
	{
		$xml = '<?xml version="1.0"?><root>'
			. '<a/>'
			. '<a><![CDATA[one]]><!--one--><?x one?><?y one?></a>'
			. '<a><![CDATA[two]]><!--two--><?x two?><?y two?></a>'
			. '</root>';

		$qp = qp($xml, 'a');
		$this->assertEquals('one', $qp->cdata());
		$this->assertEquals('one', $qp->comment());
		$this->assertEquals('one', $qp->pi());
		$this->assertEquals('one', $qp->pi('y'));
	}
}
