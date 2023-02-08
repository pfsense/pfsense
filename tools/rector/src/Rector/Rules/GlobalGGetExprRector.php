<?php
/*
 * GlobalGGetExprRector.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2023 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);
namespace Tools\Rector\Rector\Rules;

use Rector\Core\Rector\AbstractRector;
use Rector\Core\NodeManipulator\AssignManipulator;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use PhpParser\Node;

use PhpParser\Node\Arg;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\ShellExec;
use PhpParser\Node\Expr\Variable;

use PhpParser\Node\Scalar;
use PhpPArser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\String_;

use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;

use Rector\NodeTypeResolver\Node\AttributeKey;

use Tools\Rector\Rector\Helpers;
 
final class GlobalGGetExprRector extends AbstractRector
{
	/**
	 * Internal state to set when traversing an array access
	 *
	 * This variable is set to true when first visiting an
	 * ArrayDimFetch node and then reset when visiting a
	 * Variable node.
	 *
	 * @var bool
	 */
	private $isTraversingArray = false;

	/**
	 * @readonly
	 * @var \Rector\Core\NodeManipulator\AssignManipulator
	 */
	private $assignManipulator;
	public function __construct(AssignManipulator $assignManipulator)
	{
		$this->assignManipulator = $assignManipulator;
	}

	/**
	 * The nodes of interest
	 *
	 * We NEVER modify parent nodes. We only modify self or children.
	 * Variable is used only for internal state keeping.
	 *
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [
			Variable::class,
			ArrayDimFetch::class,
		];
	}

	/**
	 * Return the rule definition and before/after code samples
	 *
	 * @return RuleDefinition
	 */
	public function getRuleDefinition() : RuleDefinition
    	{
		return new RuleDefinition('Convert direct $g access to g_get()', [new CodeSample(<<<'CODE_SAMPLE'
$a = $g['scalar'];
$b = some_function($g['scalar']);
CODE_SAMPLE
), <<<'CODE_SAMPLE'
$a = g_get('scalar');
$b = some_function(g_get('scalar');
CODE_SAMPLE
]);
	}

	/**
	 * The main entrypoint to the Rector
	 *
	 * This function is called when visiting nodes.
	 * We keep this function simple and dispatch to private functions
	 *
	 * @param Node $node The node we are visiting
	 *
	 * @return Node|null
	 */
	public function refactor(Node $node) : ?Node
	{
		// dispatch to Variable node visitor
		if ($node instanceof Variable) {
			return ($this->onVariableNode($node));
		}

		// dispatch to ArrayDimFetch visitor
		if ($node instanceof ArrayDimFetch) {
			return ($this->onArrayDimFetchNode($node));
		}

		// should never happen...
		return null;
	}

	/**
	 * Visit Variable nodes
	 *
	 * @param Variable $node The variable node we are visiting
	 *
	 * @return node|null
	 */
	private function onVariableNode(Variable $node) : ?Node
	{
		// we are visiting a variable, reset internal state
		$this->isTraversingArray = false;

		return null;
	}

	/**
	 * Visit ArrayDimFetch nodes
	 *
	 * @param ArrayDimFetch $node The ArrayDimFetch node we are visiting
	 *
	 * @return node|null
	 */
	private function onArrayDimFetchNode(ArrayDimFetch $node) : ?Node
	{
		// are we visiting an intermediate array access node?
		if ($this->isTraversingArray) {
			// skip
			return null;
		}

		// we are visiting the top-level array access, set internal state
		$this->isTraversingArray = true;

		// are we on the left side of an assignment expression?
		// e.g. $g['key'] = 'value';
		if ($this->isLeftPartOfAssign($node)) {
			// skip
			return null;
		}

		// are we inside an isset or unset?
		// e.g. isset($g['key']);
		// e.g. unset($g['key']);
		if ($this->isInsideIssetUnset($node)) {
			// skip
			return null;
		}

		// are we inside of a backtick shell execution?
		// e.g. `/my/command --arg {$g['key']`;
		if ($this->isInsideBackTickShellExec($node)) {
			// skip
			return null;
		}

		// are we inside an encapsed string?
		// e.g. "something-{$g['key']}-else";
		if ($this->isInsideEncapsedString($node)) {
			// skip
			return null;
		}
		
		// does this array access have a variable at the bottom of tree?
		// e.g. $g['key'] and not func()['key']
		if (!($var = $this->resolveArrayDimFetchVariable($node))) {
			// skip
			return null;
		}

		// is the variable named 'g'?
		if (!$this->isName($var, 'g')) {
			// skip
			return null;
		}

		// resolve the array keys	
		$arrayPathNodes = $this->resolveArrayDimFetchPathNodes($node);

		// this rector only handles array accesses with one level
		if (count($arrayPathNodes) > 1) {
			return null;
		}
		// skip if any are not scalars, this is the simple case
		if (!$this->areScalarNodes($arrayPathNodes)) {
			// skip
		    	return null;
		}
		
		// build the path argument
		$pathArgument = $this->buildPathArgNode($arrayPathNodes);

		// return a new function call node
		return ($this->nodeFactory->createFuncCall('g_get', [$pathArgument]));
	}

	/**
	 * @param Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return String_
	 */
	public function buildPathStringNode(array $nodes, string $delimiter = '/') : String_ 
	{
		array_walk($nodes, function(&$node) {
			$node = $node->value;
		});

		return (new String_(implode($delimiter, $nodes)));		
	}

	/**
	 * @param Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return Arg
	 */
	public function buildPathArgNode(array $nodes, string $delimiter = '/') : Arg
	{
		return (new Arg($this->buildPathStringNode($nodes, $delimiter)));
	}

	/**
	 * Determine if a given node is a child of a backtick shell execution
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function isInsideBackTickShellExec(Node $node) : bool
	{
		return (bool) ($this->betterNodeFinder->findParentType($node, ShellExec::class));
	}

	/**
	 * Determine if a given node is inside an encapsed string
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function isInsideEncapsedString(Node $node) : bool
	{
		return (bool) ($this->betterNodeFinder->findParentType($node, Encapsed::class));
	}

	/**
	 * Determine if a given node is a  of isset
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function isInsideIssetUnset(Node $node) : bool
	{
		return (bool) ($this->betterNodeFinder->findParentByTypes($node, [Isset_::class, Unset_::class]));
	}

	/**
	 * Determine if a given node is the left-hand side of an assignment expression
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	public function isLeftPartOfAssign(Node $node) : bool
	{
		return ($this->assignManipulator->isLeftPartOfAssign($node));
		if ($parent = $this->betterNodeFinder->findParentType($node, Assign::class)) {
			return ($this->nodeComparator->areNodesEqual($parent->var, $node));
		}

		return false;
	}

	/**
	 * @param Node $node The top-level ArraydimFetch node
	 *
	 * @return Node|null
	 */
	public function resolveArrayDimFetchPathNodes(Node $node) : ?array
	{
		$nodes = [];
		while ($node instanceof ArrayDimFetch) {
			array_unshift($nodes, $node->dim);
			$node = $node->var;
		}

		// node should now be a Variable node
		return (($node instanceof Variable) ? $nodes : null);
	}

	/**
	 * @param Node[] $nodes
	 *
	 * @return bool
	 */
	public function areScalarNodes(array $nodes) : bool
	{
		foreach ($nodes as $node) {
			if (!$node instanceof Scalar) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return the root variable of an array access of arbitrary depth
	 *
	 * @param Node $node The top-level ArrayDimFetch node
	 *
	 * @return Variable|null
	 */ 
	public function resolveArrayDimFetchVariable(Node $node) : ?Variable
	{
		// walk down the chain of array fetches		
		while ($node instanceof ArrayDimFetch) {
			$node = $node->var;
		}

		// last node should be a variable
		return (($node instanceof Variable) ? $node : null);
	}
}
