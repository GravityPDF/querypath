<?php

namespace QueryPathTests;

use QueryPath\Entities;

/**
 * @ingroup querypath_tests
 */
class EntitiesTest extends TestCase
{

	public function testReplaceEntity()
	{
		$entity = 'amp';
		$this->assertEquals('38', Entities::replaceEntity($entity));

		$entity = 'lceil';
		$this->assertEquals('8968', Entities::replaceEntity($entity));
	}

	public function testReplaceAllEntities()
	{
		$test   = '<?xml version="1.0"?><root>&amp;&copy;&#38;& nothing.</root>';
		$expect = '<?xml version="1.0"?><root>&#38;&#169;&#38;&#38; nothing.</root>';
		$this->assertEquals($expect, Entities::replaceAllEntities($test));

		$test   = '&&& ';
		$expect = '&#38;&#38;&#38; ';
		$this->assertEquals($expect, Entities::replaceAllEntities($test));

		$test   = "&eacute;\n";
		$expect = "&#233;\n";
		$this->assertEquals($expect, Entities::replaceAllEntities($test));
	}

	public function testReplaceHexEntities()
	{
		$test   = '&#xA9;';
		$expect = '&#xA9;';
		$this->assertEquals($expect, Entities::replaceAllEntities($test));
	}

	public function testQPEntityReplacement()
	{
		$test = '<?xml version="1.0"?><root>&amp;&copy;&#38;& nothing.</root>';
		/*$expect = '<?xml version="1.0"?><root>&#38;&#169;&#38;&#38; nothing.</root>';*/
		// We get this because the DOM serializer re-converts entities.
		$expect = '<?xml version="1.0"?>
<root>&amp;&#xA9;&amp;&amp; nothing.</root>';

		$qp = qp($test, null, ['replace_entities' => true]);
		// Interestingly, the XML serializer converts decimal to hex and ampersands
		// to &amp;.
		$this->assertEquals($expect, trim($qp->xml()));
	}
}
