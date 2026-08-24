<?php
/**
 * The QueryPath extension system.
 *
 * @author  M Butcher <matt@aleph-null.tv>
 * @license MIT
 */

namespace QueryPath;

/**
 * An Extension adds methods to a Query object.
 *
 * Implement this interface and register the class with QueryPath::enable(). Its methods then
 * become callable on any DOMQuery, dispatched through DOMQuery::__call().
 *
 * The only requirement is a constructor taking the Query object. Store it — you need it to do
 * anything useful, and returning it from your methods is what keeps the fluent interface intact.
 *
 * ```php
 * class ShoutExtension implements \QueryPath\Extension
 * {
 *     private $qp;
 *
 *     public function __construct(\QueryPath\Query $qp)
 *     {
 *         $this->qp = $qp;
 *     }
 *
 *     public function shout()
 *     {
 *         foreach ($this->qp->get() as $node) {
 *             $node->textContent = strtoupper($node->textContent);
 *         }
 *
 *         return $this->qp;
 *     }
 * }
 *
 * QueryPath::enable(ShoutExtension::class);
 *
 * echo html5qp('<p>hi</p>')->find('p')->shout()->html5(); // <p>HI</p>
 * ```
 *
 * Extensions are instantiated lazily, on the first unknown method call, so registering one costs
 * nothing until it is used. A method whose name collides with an existing DOMQuery method is never
 * reached, because __call() only fires for methods that do not already exist.
 *
 * @see ExtensionRegistry::extend()
 * @see https://github.com/GravityPDF/querypath/wiki/Writing-Extensions
 */
interface Extension
{
	public function __construct(Query $qp);
}
