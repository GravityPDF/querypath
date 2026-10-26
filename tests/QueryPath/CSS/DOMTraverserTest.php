<?php

namespace QueryPathTests\CSS;

use DOMDocument;
use DOMXPath;
use QueryPath\CSS\DOMTraverser;
use QueryPath\CSS\DOMTraverser\PseudoClass;
use QueryPathTests\TestCase;
use SPLObjectStorage;

define('TRAVERSER_XML', __DIR__ . '/../../DOMTraverserTest.xml');

/**
 * @ingroup querypath_tests
 * @group   CSS
 */
class DOMTraverserTest extends TestCase
{

	protected $xml_file = TRAVERSER_XML;

	public function debug($msg)
	{
		fwrite(STDOUT, PHP_EOL . $msg);
	}

	public function testConstructor()
	{
		$dom = new DOMDocument('1.0');
		$dom->load($this->xml_file);

		$splos = new SPLObjectStorage();
		$splos->offsetSet($dom);

		$traverser = new DOMTraverser($splos);

		$this->assertInstanceOf('\QueryPath\CSS\Traverser', $traverser);
		$this->assertInstanceOf('\QueryPath\CSS\DOMTraverser', $traverser);
	}

	protected function traverser($xml = null)
	{
		$dom = new DOMDocument('1.0');
		if ($xml === null) {
			$dom->load($this->xml_file);
		} else {
			$dom->loadXML($xml);
		}

		$splos = new SPLObjectStorage();
		$splos->offsetSet($dom->documentElement);

		$traverser = new DOMTraverser($splos);

		return $traverser;
	}

	protected function find($selector)
	{
		return $this->traverser()->find($selector)->matches();
	}

	public function testFind()
	{
		$res = $this->traverser()->find('root');

		// Ensure that return contract is not violated.
		$this->assertInstanceOf('\QueryPath\CSS\Traverser', $res);
	}

	public function testMatches()
	{
		$res = $this->traverser()->matches();
		$this->assertEquals(1, count($res));
	}

	public function testMatchElement()
	{
		// Canary: If element does not exist, must return FALSE.
		$matches = $this->find('NO_SUCH_ELEMENT');
		$this->assertEquals(0, count($matches));

		// Test without namespace
		$matches = $this->find('root');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('crowded');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('outside');
		$this->assertEquals(3, count($matches));

		// Check nested elements.
		$matches = $this->find('a');
		$this->assertEquals(3, count($matches));

		// Test wildcard.
		$traverser = $this->traverser();
		$matches   = $traverser->find('*')->matches();
		$actual    = $traverser->getDocument()->getElementsByTagName('*');
		$this->assertEquals($actual->length, count($matches));


		// Test with namespace
		//$this->markTestIncomplete();
		$matches = $this->find('ns_test');
		$this->assertEquals(3, count($matches));

		$matches = $this->find('test|ns_test');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('test|ns_test>ns_attr');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('*|ns_test');
		$this->assertEquals(3, count($matches));

		$matches = $this->find('test|*');
		$this->assertEquals(1, count($matches));

		// Test where namespace is declared on the element.
		$matches = $this->find('newns|my_element');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('test|ns_test>newns|my_element');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('test|*>newns|my_element');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('*|ns_test>newns|my_element');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('*|*>newns|my_element');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('*>newns|my_element');
		$this->assertEquals(1, count($matches));
	}

	public function testMatchAttributes()
	{

		$matches = $this->find('crowded[attr1]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('crowded[attr1=one]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('crowded[attr2^=tw]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('classtest[class~=two]');
		$this->assertEquals(1, count($matches));
		$matches = $this->find('classtest[class~=one]');
		$this->assertEquals(1, count($matches));
		$matches = $this->find('classtest[class~=seven]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('crowded[attr0]');
		$this->assertEquals(0, count($matches));

		$matches = $this->find('[level=1]');
		$this->assertEquals(3, count($matches));

		$matches = $this->find('[attr1]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('[test|myattr]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('[test|myattr=foo]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('[*|myattr=foo]');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('[|myattr=foo]');
		$this->assertEquals(0, count($matches));

		$matches = $this->find('[|level=1]');
		$this->assertEquals(3, count($matches));

		$matches = $this->find('[*|level=1]');
		$this->assertEquals(4, count($matches));

		// Test namespace on attr where namespace
		// is declared on that element
		$matches = $this->find('[nuther|ping]');
		$this->assertEquals(1, count($matches));

		// Test multiple namespaces on an element.
		$matches = $this->find('[*|ping=3]');
		$this->assertEquals(1, count($matches));

		// Test multiple namespaces on an element.
		$matches = $this->find('[*|ping]');
		$this->assertEquals(1, count($matches));
	}

	public function testMatchId()
	{
		$matches = $this->find('idtest#idtest-one');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('#idtest-one');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('outter#fake');
		$this->assertEquals(0, count($matches));

		$matches = $this->find('#fake');
		$this->assertEquals(0, count($matches));
	}

	public function testMatchClasses()
	{
		// Basic test.
		$matches = $this->find('a.a1');
		$this->assertEquals(1, count($matches));

		// Count multiple.
		$matches = $this->find('.first');
		$this->assertEquals(2, count($matches));

		// Grab one in the middle of a list.
		$matches = $this->find('.four');
		$this->assertEquals(1, count($matches));

		// One element with two classes.
		$matches = $this->find('.three.four');
		$this->assertEquals(1, count($matches));
	}

	public function testMatchPseudoClasses()
	{

		$matches = $this->find('ul>li:first');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('ul>li:not(.first)');
		$this->assertEquals(5, count($matches));
	}

	public function testMatchPseudoElements()
	{
		$matches = $this->find('p::first-line');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('p::first-letter');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('p::before');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('p::after');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('bottom::after');
		$this->assertEquals(0, count($matches));
	}

	public function testCombineAdjacent()
	{
		// Simple test
		$matches = $this->find('idtest + p');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('p', $m->tagName);
		}

		// Test ignoring PCDATA
		$matches = $this->find('p + one');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('one', $m->tagName);
		}

		// Test that non-adjacent elements don't match.
		$matches = $this->find('idtest + one');
		foreach ($matches as $m) {
			$this->assertEquals('one', $m->tagName);
		}
		$this->assertEquals(0, count($matches), 'Non-adjacents should not match.');

		// Test that elements BEFORE don't match
		$matches = $this->find('one + p');
		foreach ($matches as $m) {
			$this->assertEquals('one', $m->tagName);
		}
		$this->assertEquals(0, count($matches), 'Match only if b is after a');
	}

	public function testCombineSibling()
	{
		// Canary:
		$matches = $this->find('one ~ two');
		$this->assertEquals(0, count($matches));

		// Canary 2:
		$matches = $this->find('NO_SUCH_ELEMENT ~ two');
		$this->assertEquals(0, count($matches));

		// Simple test
		$matches = $this->find('idtest ~ p');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('p', $m->tagName);
		}

		// Simple test
		$matches = $this->find('outside ~ p');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('p', $m->tagName);
		}

		// Matches only go left, not right.
		$matches = $this->find('p ~ outside');
		$this->assertEquals(0, count($matches));
	}

	public function testCombineDirectDescendant()
	{
		// Canary:
		$matches = $this->find('one > four');
		$this->assertEquals(0, count($matches));

		$matches = $this->find('two>three');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('three', $m->tagName);
		}

		$matches = $this->find('one > two > three');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('three', $m->tagName);
		}

		$matches = $this->find('a>a>a');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('a>a');
		$this->assertEquals(2, count($matches));
	}

	public function testCombineAnyDescendant()
	{
		// Canary
		$matches = $this->find('four one');
		$this->assertEquals(0, count($matches));

		$matches = $this->find('one two');
		$this->assertEquals(1, count($matches));
		foreach ($matches as $m) {
			$this->assertEquals('two', $m->tagName);
		}

		$matches = $this->find('one four');
		$this->assertEquals(1, count($matches));

		$matches = $this->find('a a');
		$this->assertEquals(2, count($matches));

		$matches = $this->find('root two four');
		$this->assertEquals(1, count($matches));
	}

	public function testMultipleSelectors()
	{
		// fprintf(STDOUT, "=========TEST=========\n\n");
		$matches = $this->find('one, two');
		$this->assertEquals(2, count($matches));
	}

	/**
	 * The <ul> children are, by position: l1 p1 l2 l3 p2 l4 l5 p3.
	 */
	protected $nthXml = '<?xml version="1.0"?><root id="root"><ul id="ul">'
		. 'text<!-- comment --><li id="l1"/><p id="p1"/><li id="l2"/><!-- comment --><li id="l3"/>'
		. 'text<p id="p2"/><li id="l4"/><li id="l5"/><p id="p3"/>text'
		. '</ul><ol id="ol"><li id="o1"/><li id="o2"/></ol></root>';

	protected function ids($nodes): array
	{
		$ids = [];
		foreach ($nodes as $node) {
			$ids[] = $node->getAttribute('id');
		}

		return $ids;
	}

	public function nthPseudoClassProvider(): array
	{
		return [
			// :nth-child()
			['ul > :nth-child(odd)', ['l1', 'l2', 'p2', 'l5']],
			['ul > :nth-child(even)', ['p1', 'l3', 'l4', 'p3']],
			['ul > :nth-child(n)', ['l1', 'p1', 'l2', 'l3', 'p2', 'l4', 'l5', 'p3']],
			['ul > :nth-child(3)', ['l2']],
			['ul > :nth-child(-n+3)', ['l1', 'p1', 'l2']],
			['ul > :nth-child(3n-5)', ['l1', 'l3', 'l5']],
			['ul > :nth-child(0n+0)', []],
			['ul > :nth-child(-2n-1)', []],
			['ul > :nth-child(9)', []],
			['li:nth-child(2n+1)', ['l1', 'l2', 'l5', 'o1']],

			// :nth-last-child()
			['ul > :nth-last-child(1)', ['p3']],
			['ul > :nth-last-child(odd)', ['p1', 'l3', 'l4', 'p3']],
			['ul > :nth-last-child(-n+2)', ['l5', 'p3']],
			['ul > :nth-last-child(8)', ['l1']],
			['li:nth-last-child(2)', ['l5', 'o1']],

			// :nth-of-type(). The <li> are l1-l5 and the <p> p1-p3.
			['li:nth-of-type(odd)', ['l1', 'l3', 'l5', 'o1']],
			['p:nth-of-type(2)', ['p2']],
			['ul > :nth-of-type(2)', ['l2', 'p2']],
			['ul > :nth-of-type(-n+1)', ['l1', 'p1']],
			['ul > :nth-of-type(4)', ['l4']],

			// :nth-last-of-type()
			['li:nth-last-of-type(1)', ['l5', 'o2']],
			['ul > :nth-last-of-type(2)', ['p2', 'l4']],
			['p:nth-last-of-type(n+2)', ['p1', 'p2']],
			['ul > :nth-last-of-type(3n)', ['p1', 'l3']],

			// Several :nth-* in one query share the same sibling index.
			['li:nth-child(odd):nth-of-type(odd)', ['l1', 'l5', 'o1']],
			['ul > :nth-child(even):nth-last-of-type(1)', ['p3']],
			['ul > :nth-child(1), ul > :nth-last-child(1)', ['l1', 'p3']],

			// Nested queries.
			['ul > :not(:nth-child(odd))', ['p1', 'l3', 'l4', 'p3']],
			['ul > :not(:nth-last-of-type(-n+2))', ['l1', 'p1', 'l2', 'l3']],
		];
	}

	/**
	 * @dataProvider nthPseudoClassProvider
	 */
	public function testNthPseudoClasses($selector, array $expected)
	{
		$this->assertSame($expected, $this->ids($this->traverser($this->nthXml)->find($selector)->matches()));
	}

	public function testNthPseudoClassesInFilterAndChildren()
	{
		$this->assertSame(['l1', 'l2', 'p2', 'l5'], $this->ids(qp($this->nthXml, 'ul > *')->filter(':nth-child(odd)')->get()));
		$this->assertSame(['l5', 'p3'], $this->ids(qp($this->nthXml, 'ul')->children(':nth-last-of-type(1)')->get()));
		$this->assertSame(['l2', 'o2'], $this->ids(qp($this->nthXml, 'ul, ol')->children('li:nth-of-type(2)')->get()));
	}

	/**
	 * Sibling positions are indexed per query, so querying again after the DOM
	 * changes must see the new positions.
	 */
	public function testNthPseudoClassesAfterMutation()
	{
		$qp = qp('<?xml version="1.0"?><root><li id="a"/><li id="b"/><li id="c"/></root>');

		$this->assertSame(['b'], $this->ids($qp->find('li:nth-child(2)')->get()));
		$this->assertSame(['b'], $this->ids($qp->find('li:nth-last-of-type(2)')->get()));

		$qp->find('root')->prepend('<li id="new"/>')->append('<p/>');

		$this->assertSame(['a'], $this->ids($qp->find('li:nth-child(2)')->get()));
		$this->assertSame(['c'], $this->ids($qp->find('li:nth-last-child(2)')->get()));
		$this->assertSame(['a'], $this->ids($qp->find('li:nth-of-type(2)')->get()));
		$this->assertSame(['b'], $this->ids($qp->find('li:nth-last-of-type(2)')->get()));

		$qp->find('#b')->remove();

		$this->assertSame(['c'], $this->ids($qp->find('li:nth-child(3)')->get()));
		$this->assertSame(['new', 'c'], $this->ids($qp->find('li:nth-child(odd)')->get()));
	}

	/**
	 * Load $nthXml and collect the nodes with the given IDs, in the given order.
	 */
	protected function nthNodes(string ...$ids): SplObjectStorage
	{
		$dom = new DOMDocument('1.0');
		$dom->loadXML($this->nthXml);

		$xpath = new DOMXPath($dom);
		$nodes = new SplObjectStorage();
		foreach ($ids as $id) {
			$nodes->offsetSet($xpath->query("//*[@id='$id']")->item(0));
		}

		return $nodes;
	}

	public function filterNodesProvider(): array
	{
		return [
			'keeps the given order'          => [['l4', 'p1', 'l2', 'l1'], 'li', ['l4', 'l2', 'l1']],
			'tests the nodes, not below'     => [['ul', 'l1'], 'ul', ['ul']],
			'resolves combinators'           => [['o1', 'l2', 'l1'], 'ul > li', ['l2', 'l1']],
			'selector list'                  => [['p2', 'o2', 'l1'], 'p, ol > li', ['p2', 'o2']],
			':first is first in the set'     => [['l4', 'l2', 'l3'], 'li:first', ['l2']],
			':eq() counts within the set'    => [['l4', 'l2', 'l3'], ':eq(1)', ['l3']],
			':odd counts within the set'     => [['l5', 'l4', 'l2', 'l1'], 'li:odd', ['l5', 'l2']],
			':not() with a positional arg'   => [['l4', 'l2', 'l3'], 'li:not(:first)', ['l4', 'l3']],
			':matches() with positional arg' => [['l4', 'l2', 'l3'], ':matches(:last)', ['l4']],
			':nth-child counts siblings'     => [['l4', 'l2', 'l3'], ':nth-child(4)', ['l3']],
			'document element'               => [['root', 'ul'], 'root', ['root']],
			':root'                          => [['ul', 'root'], ':root', ['root']],
			':scope is the document element' => [['ul', 'root'], ':scope', ['root']],
		];
	}

	/**
	 * @dataProvider filterNodesProvider
	 */
	public function testFilterNodes(array $ids, $selector, array $expected)
	{
		$this->assertSame($expected, $this->ids(DOMTraverser::filterNodes($this->nthNodes(...$ids), $selector)));
	}

	public function testFilterNodesSkipsNodesThatAreNotElements()
	{
		$ul = $this->nthNodes('ul');
		$ul->rewind();

		// Every child of the <ul>, last first: text and comments interleave the elements.
		$nodes = new SplObjectStorage();
		for ($child = $ul->current()->lastChild; $child !== null; $child = $child->previousSibling) {
			$nodes->offsetSet($child);
		}

		$this->assertSame(['p3', 'l5', 'l4', 'p2', 'l3', 'l2', 'p1', 'l1'], $this->ids(DOMTraverser::filterNodes($nodes, '*')));
		$this->assertSame(['l5', 'l4', 'l3', 'l2', 'l1'], $this->ids(DOMTraverser::filterNodes($nodes, ':not(p)')));
		$this->assertSame(['l1'], $this->ids(DOMTraverser::filterNodes($nodes, ':first')));
	}

	public function testFilterNodesWithAnEmptySet()
	{
		$this->assertCount(0, DOMTraverser::filterNodes(new SplObjectStorage(), 'li'));
		$this->assertCount(0, DOMTraverser::filterNodes(new SplObjectStorage(), 'li:first'));
	}

	public function testFilterNodesWithAScopeNode()
	{
		$nodes = $this->nthNodes('ul', 'l1', 'l2', 'o1');
		$nodes->rewind();
		$ul = $nodes->current();

		$this->assertSame(['ul'], $this->ids(DOMTraverser::filterNodes($nodes, ':scope', $ul)));
		$this->assertSame(['l1', 'l2'], $this->ids(DOMTraverser::filterNodes($nodes, ':scope > li', $ul)));
	}

	/**
	 * A shared handler hands every query the same parsed selector, which the traverser must leave
	 * as it found it.
	 *
	 * @testWith ["#ul li", ["l1", "l2", "l3", "l4", "l5"]]
	 *           [".x", ["x"]]
	 *           ["ul:first > li", ["l1", "l2", "l3", "l4", "l5"]]
	 */
	public function testQueriesSharingAHandlerReuseTheParsedSelector($selector, array $expected)
	{
		$dom = new DOMDocument('1.0');
		$dom->loadXML(str_replace('<ol id="ol">', '<ol id="ol"><b id="x" class="x"/>', $this->nthXml));
		$splos = new SplObjectStorage();
		$splos->offsetSet($dom->documentElement);

		$ps = new PseudoClass();
		for ($i = 0; $i < 2; $i++) {
			$this->assertSame($expected, $this->ids((new DOMTraverser($splos, false, null, $ps))->find($selector)->matches()));
		}
	}
}
