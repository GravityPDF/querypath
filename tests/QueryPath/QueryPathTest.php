<?php

namespace QueryPathTests;

use QueryPath\Extension;
use QueryPath\Query;
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

	public function testVersionMajorMatchesVersion()
	{
		$this->assertSame((int) explode('.', QueryPath::VERSION)[0], QueryPath::VERSION_MAJOR);
	}

	/**
	 * Releasing turns "# Unreleased changes" into a version heading, so VERSION must be bumped alongside it.
	 */
	public function testVersionMatchesLatestChangelogRelease()
	{
		preg_match('/^# (\d+\.\d+\.\d+)$/m', file_get_contents(__DIR__ . '/../../CHANGELOG.md'), $matches);

		$this->assertSame($matches[1], QueryPath::VERSION);
	}
}

class DummyExtension implements Extension
{

	/**
	 * @var Query
	 */
	protected $qp;

	public function __construct(Query $qp)
	{
		$this->qp = $qp;
	}

	public function grrrrrrr()
	{
		return true;
	}
}
