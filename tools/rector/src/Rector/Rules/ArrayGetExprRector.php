<?php
/*
 * ArrayGetExprRector.php
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

use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\NodeManipulator\AssignManipulator;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use PhpParser\Node;

use PhpParser\Node\Arg;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\ShellExec;
use PhpParser\Node\Expr\Variable;

use PhpParser\Node\Scalar;
use PhpPArser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Scalar\String_;

use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;

use Rector\NodeTypeResolver\Node\AttributeKey;

use Tools\Rector\Rector\Helpers;
 
final class ArrayGetExprRector extends AbstractRector implements ConfigurableRectorInterface
{
	/**
	 * Dictionary of var => get function pairs
	 * @var array
	 */
	private $vars = [];


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

	public function configure(array $configuration): void {
		$this->vars = $configuration;
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
		return new RuleDefinition('Convert direct variable array access to a get function call', [new CodeSample(<<<'CODE_SAMPLE'
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
		if (empty($this->vars)) {
			return null;
		}

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

		// is the variable named in our dict?
		$var_getter = null;
		foreach  ($this->vars as $_var_name => $_var_getter) {
			if ($this->isName($var, $_var_name)) {
				$var_getter = $_var_getter;
				break;
			}
		}
		if ($var_getter === null) {
			// skip
			return null;
		}

		// resolve the array keys	
		$arrayPathNodes = $this->resolveArrayDimFetchPathNodes($node);

		// build the path argument
		$pathArgument = $this->buildPathArgNode($arrayPathNodes);

		if ($pathArgument === null) {
			// skip
			return null;
		}

		// return a new function call node
		return ($this->nodeFactory->createFuncCall($var_getter, [$pathArgument]));
	}

	/**
	 * 
	 * @param &Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return String_|Null
	 */
	public function buildPathStringNode(array &$nodes, string $delimiter = '/') : ?String_
	{
		$strtmp = [];
		while(!empty($nodes) && $nodes[0] instanceof Scalar) {
			$strtmp[] = array_shift($nodes)->value;
		}
		if (!empty($strtmp)) {
			$node = new String_(implode($delimiter, $strtmp));
			return $node;
		} else {
			return null;
		}
	}
		
	/**
	 * @param &Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return Encapsed|Null
	 */
	public function buildPathEncapsedNode(array &$nodes, string $delimiter = '/') : ?Encapsed
	{
		$parts = [];
		$last_var = false;
		while(!empty($nodes)) {
			if ($nodes[0] instanceof String_) {
				$part = $this->buildPathStringNode($nodes, $delimiter);
				if ($part !== null) {
					/* Prepend slash if part follows variable */
					if ($last_var) {
						$part->value = $delimiter . $part->value;
						$last_var = false;
					}
					/* Append slash if more follows */
					if (!empty($nodes)) {
						$part->value .= $delimiter;
					}
 					$parts[] = new EncapsedStringPart($part->value);
				} else {
					return null;
				}
			} else if ($nodes[0] instanceof Variable) {
				$last_var = true;
				$parts[] = array_shift($nodes);
			} else {
				$parts[] = new EncapsedStringPart(array_shift($nodes)->getType());
			}
		}
		return (new Encapsed($parts));
	}

	/**
	 * @param &Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return Scalar|null
	 */
	public function buildPathEncapsedOrStringNode(array &$nodes, string $delimiter = '/') : ?Scalar
	{
		$node = null;
		$strnode = null;

		while(!empty($nodes)) {
			if ($nodes[0] instanceof String_) {
				$strnode = $this->buildPathStringNode($nodes, $delimiter);
			} else if ($nodes[0] instanceof Variable) {
				if ($strnode != null) {
					array_unshift($nodes, $strnode);
					$strnode = null;
				}
				/* Start encapsed node */
				$node = $this->buildPathEncapsedNode($nodes, $delimiter);
			} else {
				break;
			}
		}
		
		if ($strnode !== null) {
			return $strnode;
		}
		return $node;
	}

	/**
	 * @param &Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return Concat|Null
	 */
	public function buildPathConcatNode(array &$nodes, string $delimiter = '/') : ?Concat
	{
		/* expect nodes[0] is the lhs */
		$lhs = array_shift($nodes);
		if ($nodes[0] instanceof String_ || $nodes[0] instanceof Variable) {
			$rhs = $this->buildPathEncapsedOrStringNode($nodes, $delimiter);
		} else {
			$rhs = array_shift($nodes);
		}
		return new Concat($lhs, $rhs);
	}
	
	/**
	 * @param &Node node
	 * @param string delimiter
	 *
	 * @return void
	 */
	public function appendDelimiter(Node &$node, $delimiter = '/'): void
	{
		if ($node instanceof String_) {
			$node->value .= $delimiter;
		} else if ($node instanceof Encapsed) {
			$lastnode = &$node->parts[count($node->parts) - 1];
			if ($lastnode instanceof String_) {
				$lastnode->value .= $delimiter;
			} else {
				$node->parts[] = new String_($delimiter);
			}
		}
	}

	/**
	 * @param &Node node
	 * @param string delimiter
	 *
	 * @return void
	 */
	public function prependDelimiter(Node &$node, $delimiter = '/'): void
	{
		if ($node instanceof String_) {
			$node->value = $delimiter . $node->value;
		} else if ($node instanceof Encapsed) {
			$firstnode = &$node->parts[0];
			if ($firstnode instanceof String_) {
				$firstnode->value .= $delimiter;
			} else {
				array_unshift($node->parts, new String_($delimiter));
			}
		}

	}
	
	/**
	 * @param Node[] $nodes
	 * @param string $delimiter
	 *
	 * @return Arg|Null
	 */
	public function buildPathArgNode(array $nodes, string $delimiter = '/') : ?Arg
	{
		$rootnode = null;
		do {
			if ($nodes[0] instanceof String_ || $nodes[0] instanceof Variable) {
				$node = $this->buildPathEncapsedOrStringNode($nodes, $delimiter);
				if ($rootnode !== null) {
					$this->prependDelimiter($node, $delimiter);
				}
				if (!empty($nodes)) {
					$this->appendDelimiter($node, $delimiter);
				}
				if ($rootnode instanceof Concat) {
					array_unshift($nodes, $node);
					array_unshift($nodes, $rootnode);
					$rootnode = $this->buildPathConcatNode($nodes, $delimiter);
				} else {
					$rootnode = $node;
				}
			} else if ($nodes[0] instanceof FuncCall) {
				if ($rootnode !== null) {
					array_unshift($nodes, $rootnode);
				}
				$rootnode = $this->buildPathConcatNode($nodes, $delimiter);
			} else {
				/* unhandled type */
				return null;
			}
		} while ($rootnode !== null && !empty($nodes));

		return ($rootnode !== null ? new Arg($rootnode) : null);
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
	 * @return Node[]|null
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
