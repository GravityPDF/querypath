<?php
/**
 * @file
 *
 * Regression tests for https://github.com/GravityPDF/querypath/issues/62
 *
 * `parents()` (and the other selector-filtered traversal methods) tested the
 * ancestor by running the selector against its *descendants*, so any ancestor
 * that merely contained a matching element was treated as a match itself.
 */

namespace QueryPathTests;

use QueryPath\Exception;

class Issue62Test extends TestCase
{
	public const AMPLIFY_FILE = 'tests/amplify.xml';

	/**
	 * Collect the tag names of every element in a result set.
	 *
	 * @param \QueryPath\Query $query
	 *
	 * @return array
	 */
	private function tags($query): array
	{
		$tags = [];
		foreach ($query as $item) {
			$tags[] = $item->tag();
		}

		return $tags;
	}

	/**
	 * @throws Exception
	 */
	public function testParentsFiltersAncestorsBySelector()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(1, $qp->count());
		$this->assertEquals(['Demographics'], $this->tags($qp->parents('Demographics')));
	}

	/**
	 * The ancestor must be matched as an element, not by asking whether it
	 * contains something matching the selector.
	 *
	 * @throws Exception
	 */
	public function testParentsDoesNotMatchAncestorsThatMerelyContainTheSelector()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['Age'], $this->tags($qp->parents('Age')));
		$this->assertEquals(['AmplifyReturn'], $this->tags($qp->parents('AmplifyReturn')));
		$this->assertEquals(0, $qp->parents('Name')->count());
		$this->assertEquals(0, $qp->parents('Value')->count());
	}

	/**
	 * @throws Exception
	 */
	public function testParentsWithoutSelectorReturnsEveryAncestor()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(
			['Age', 'Demographics', 'AmplifyReturn', 'ns1:AmplifyResponse'],
			$this->tags($qp->parents())
		);
	}

	/**
	 * @throws Exception
	 */
	public function testParentsMatchesNamespacedAncestors()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['ns1:AmplifyResponse'], $this->tags($qp->parents('ns1|AmplifyResponse')));
		$this->assertEquals(['ns1:AmplifyResponse'], $this->tags($qp->parents('*|AmplifyResponse')));

		// The namespaced root must not be reported for a selector it only contains.
		$this->assertEquals(0, $qp->parents('ns1|Demographics')->count());
	}

	/**
	 * @throws Exception
	 */
	public function testParentsAcceptsFullSelectorsWithCombinators()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['Demographics'], $this->tags($qp->parents('AmplifyReturn > Demographics')));
		$this->assertEquals(0, $qp->parents('Styles > Demographics')->count());
	}

	/**
	 * jQuery returns ancestors closest-first, and for a set built from more than
	 * one element the result is in reverse document order with duplicates removed.
	 *
	 * @throws Exception
	 */
	public function testParentsReturnsReverseDocumentOrder()
	{
		$xml = '<?xml version="1.0"?><root><a><b><i id="one"/></b></a><c><d><i id="two"/></d></c></root>';
		$qp  = qp($xml, 'i');

		$this->assertEquals(2, $qp->count());
		$this->assertEquals(['d', 'c', 'b', 'a', 'root'], $this->tags($qp->parents()));
	}

	/**
	 * Shared ancestors must appear exactly once.
	 *
	 * @throws Exception
	 */
	public function testParentsRemovesDuplicates()
	{
		$xml = '<?xml version="1.0"?><root><a><i id="one"/><i id="two"/></a></root>';
		$qp  = qp($xml, 'i');

		$this->assertEquals(2, $qp->count());
		$this->assertEquals(['a', 'root'], $this->tags($qp->parents()));
	}

	/**
	 * @throws Exception
	 */
	public function testParentsUntilStopsAtTheMatchingAncestor()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['Age'], $this->tags($qp->parentsUntil('Demographics')));

		// Before the fix AmplifyReturn was collected, because AmplifyReturn does
		// not contain a descendant called AmplifyReturn.
		$this->assertEquals(['Age', 'Demographics'], $this->tags($qp->parentsUntil('AmplifyReturn')));
		$this->assertEquals(
			['Age', 'Demographics', 'AmplifyReturn'],
			$this->tags($qp->parentsUntil('ns1|AmplifyResponse'))
		);
	}

	/**
	 * @throws Exception
	 */
	public function testClosestMatchesTheAncestorItself()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['Age'], $this->tags($qp->closest('Age')));
		$this->assertEquals(['Demographics'], $this->tags($qp->closest('Demographics')));

		// Before the fix this returned ns1:AmplifyResponse, the first ancestor
		// that contained an AmplifyReturn element.
		$this->assertEquals(['AmplifyReturn'], $this->tags($qp->closest('AmplifyReturn')));
		$this->assertEquals(['Name'], $this->tags($qp->closest('Name')));
	}

	/**
	 * @throws Exception
	 */
	public function testParentMatchesTheAncestorItself()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['Age'], $this->tags($qp->parent('Age')));
		$this->assertEquals(['AmplifyReturn'], $this->tags($qp->parent('AmplifyReturn')));
		$this->assertEquals(0, $qp->parent('Name')->count());
	}

	/**
	 * @throws Exception
	 */
	public function testSiblingTraversalMatchesTheSiblingItself()
	{
		$qp = qp(self::AMPLIFY_FILE, 'Demographics > Age > Name');

		$this->assertEquals(['Value'], $this->tags($qp->siblings('Value')));
		$this->assertEquals(['Value'], $this->tags($qp->nextAll('Value')));
		$this->assertEquals(['Value'], $this->tags($qp->next('Value')));
		$this->assertEquals(0, $qp->siblings('Name')->count());
	}

	/**
	 * `nextUntil()`/`prevUntil()` must stop on a sibling that matches, not on a
	 * sibling that contains a match.
	 *
	 * @throws Exception
	 */
	public function testNextUntilAndPrevUntilStopOnMatchingSibling()
	{
		$xml = '<?xml version="1.0"?><root><a/><b><a/></b><c/><d/></root>';

		$this->assertEquals(['b'], $this->tags(qp($xml, 'root > a')->nextUntil('c')));
		$this->assertEquals([], $this->tags(qp($xml, 'root > a')->nextUntil('b')));
		$this->assertEquals(['c', 'b'], $this->tags(qp($xml, 'root > d')->prevUntil('a')));
	}

	/**
	 * `not()` must exclude elements that match the selector themselves.
	 *
	 * @throws Exception
	 */
	public function testNotExcludesElementsThatMatchTheSelector()
	{
		$xml = '<?xml version="1.0"?><root><a class="keep"><b/></a><b/></root>';
		$qp  = qp($xml, 'a, b');

		$this->assertEquals(3, $qp->count());
		$this->assertEquals(['b', 'b'], $this->tags($qp->not('a')));
		$this->assertEquals(['a'], $this->tags($qp->not('b')));
	}

	/**
	 * The candidate node must not be passed to the traverser as its scope node, or every
	 * candidate matches :scope and the selector stops filtering anything at all.
	 *
	 * @see \QueryPath\Helpers\NodeMatcher
	 */
	public function testScopePseudoClassIsResolvedAgainstTheDocument(): void
	{
		$xml = '<?xml version="1.0"?><root><a><b><c>x</c></b></a></root>';

		$this->assertSame('root', qp($xml, 'c')->top()->find(':scope')->tag());

		$parents = qp($xml, 'c')->parents(':scope');
		$this->assertCount(1, $parents, ':scope must match the document element, not every ancestor');
		$this->assertSame('root', $parents->tag());

		$this->assertTrue(qp($xml, 'root')->is(':scope'));
		$this->assertFalse(qp($xml, 'c')->is(':scope'));
	}
}
