<?php

namespace QueryPathTests;

use QueryPath\DOMQuery;

/**
 * Test the XMLish functions of QueryPath.
 *
 * This uses a testing harness, XMLishMock, to test
 * a protected method of QueryPath.
 *
 * @ingroup querypath_test
 */
class XMLIshTest extends TestCase
{

	public function testXMLishMock()
	{
		$tests = [
			'this/is/a/path'                                   => false,
			"this is just some plain\ntext with a line break." => false,
			'2 > 1'                                            => false,
			'1 < 2'                                            => false,
			//'1 < 2 > 1' => FALSE,
			'<html/>'                                          => true,
			'<?xml version="1.0"?><root/>'                     => true,
			'<tag/><tag/><tag/>'                               => true, // It's not valid, but HTML parser will try it.
		];
		foreach ($tests as $test => $correct) {
			$mock = new XMLishMock();
			$this->assertEquals($correct, $mock->exposedIsXMLish($test), "Testing $test");
		}
	}

	public function testXMLishWithBrokenHTML()
	{
		$html = '<div id="qp-top"><div class=header>Abe H. Rosenbloom Field<br></div> <p> Located in a natural bowl north of 10th Avenue, Rosenbloom Field was made possible by a gift from Virginia Whitney Rosenbloom \'36 and Abe H. Rosenbloom \'34. The Pioneers observed the occasion of the field\'s dedication on Oct. 4, 1975, by defeating Carleton 36-26. Rosenbloom Field has a seating capacity of 1,500. <br> <br> A former member of the Grinnell Advisory Board and other college committees, Abe Rosenbloom played football at Grinnell from 1931 to 1933. He played guard and was one of the Missouri Valley Conference\'s smallest gridders (5\'6" and 170 pounds). He averaged more than 45 minutes a game playing time during a 24-game varsity career and was named to the Des Moines Register\'s all-Missouri Valley Conference squad in 1932 and 1933. <br> <br> On the south side of the field, a memorial recalls the 100th anniversary of the first intercollegiate football game played west of the Mississippi. The game took place on the Grinnell campus on Nov. 16, 1889. On the north side, a marker commemorates the first 50 years of football in the west, and recalls the same game, played in 1889, Grinnell College vs. the University of Iowa. Grinnell won, 24-0. </p></div>';
		$mock = new XMLishMock();
		$this->assertEquals(true, $mock->exposedIsXMLish($html), "Testing broken HTML");
	}
}

/**
 * A testing class for XMLish tests.
 *
 * @ingroup querypath_tests
 */
class XMLishMock extends DOMQuery
{

	public function exposedIsXMLish($str)
	{
		return $this->isXMLish($str);
	}
}
