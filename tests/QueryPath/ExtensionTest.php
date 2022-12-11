<?php

namespace QueryPathTests;

use QueryPath\Exception;
use QueryPath\Extension;
use QueryPath\ExtensionRegistry;
use QueryPath\Query;

/**
 * Run all of the usual tests, plus some extras, with some extensions loaded.
 *
 * @ingroup querypath_tests
 * @group   extension
 */
class ExtensionTest extends TestCase
{

	public static function set_up_before_class()
	{
		ExtensionRegistry::extend(StubExtensionOne::class);
		ExtensionRegistry::extend(StubExtensionTwo::class);
	}

	public function testExtensions()
	{
		$this->assertNotNull(qp());
	}

	public function testHasExtension()
	{
		$this->assertTrue(ExtensionRegistry::hasExtension(StubExtensionOne::class));
	}

	public function testStubToe()
	{
		$this->assertEquals(1, qp(self::DATA_FILE_XML, 'unary')->stubToe()->top(':root > toe')->size());
	}

	public function testStuble()
	{
		$this->assertEquals('arg1arg2', qp(self::DATA_FILE_XML)->stuble('arg1', 'arg2'));
	}

	public function testNoRegistry()
	{
		$this->expectException(Exception::class);

		ExtensionRegistry::$useRegistry = false;
		try {
			qp(self::DATA_FILE_XML)->stuble('arg1', 'arg2');
		} catch (Exception $e) {
			ExtensionRegistry::$useRegistry = true;
			throw $e;
		}
	}

	public function testExtend()
	{
		$this->assertFalse(ExtensionRegistry::hasExtension(StubExtensionThree::class));
		ExtensionRegistry::extend(StubExtensionThree::class);
		$this->assertTrue(ExtensionRegistry::hasExtension(StubExtensionThree::class));
	}

	public function tear_down()
	{
		ExtensionRegistry::$useRegistry = true;
	}

	public function testAutoloadExtensions()
	{
		$this->expectException(Exception::class);

		// FIXME: This isn't really much of a test.
		ExtensionRegistry::autoloadExtensions(false);
		try {
			qp()->stubToe();
		} catch (Exception $e) {
			ExtensionRegistry::autoloadExtensions(true);
			throw $e;
		}
	}

	public function testCallFailure()
	{
		$this->expectException(Exception::class);

		qp()->foo();
	}

	// This does not (and will not) throw an exception.
	// /**
	//   * @expectedException QueryPathException
	//   */
	//  public function testExtendNoSuchClass() {
	//    ExtensionRegistry::extend('StubExtensionFour');
	//  }
}

// Create a stub extension:

/**
 * Create a stub extension
 *
 * @ingroup querypath_tests
 */
class StubExtensionOne implements Extension
{

	private $qp = null;

	public function __construct(Query $qp)
	{
		$this->qp = $qp;
	}

	public function stubToe()
	{
		$this->qp->top()->append('<toe/>')->end();

		return $this->qp;
	}
}

/**
 * Create a stub extension
 *
 * @ingroup querypath_tests
 */
class StubExtensionTwo implements Extension
{

	private $qp = null;

	public function __construct(Query $qp)
	{
		$this->qp = $qp;
	}

	public function stuble($arg1, $arg2)
	{
		return $arg1 . $arg2;
	}
}

/**
 * Create a stub extension
 *
 * @ingroup querypath_tests
 */
class StubExtensionThree implements Extension
{

	private $qp;

	public function __construct(Query $qp)
	{
		$this->qp = $qp;
	}

	public function stuble($arg1, $arg2)
	{
		return $arg1 . $arg2;
	}
}

//ExtensionRegistry::extend('StubExtensionOne');
//ExtensionRegistry::extend('StubExtensionTwo');
