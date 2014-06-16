<?php
namespace JmesPath\Tree;

use JmesPath\Lexer;
use JmesPath\Runtime\RuntimeInterface;

/**
 * Tree visitor used to evaluates JMESPath AST expressions.
 */
class TreeInterpreter implements TreeVisitorInterface
{
    /** @var RuntimeInterface Runtime used to manage function calls */
    private $runtime;

    public function __construct(RuntimeInterface $runtime = null)
    {
        $this->runtime = $runtime;
    }

    public function visit(array $node, $data, array $args = null)
    {
        if (!$this->runtime) {
            $this->runtime = isset($args['runtime'])
                ? $args['runtime']
                : \JmesPath\createRuntime();
        }

        return $this->dispatch($node, $data);
    }

    /**
     * Recursively traverses an AST using depth-first, pre-order traversal.
     * The evaluation logic for each node type is embedded into a large switch
     * statement to avoid the cost of "double dispatch".
     */
    private function dispatch(array $node, $value)
    {
        switch ($node['type']) {

            case 'field':
                // Returns the key value of a hash or null if is not a hash or
                // if the key does not exist.
                return is_array($value) && isset($value[$node['key']])
                    ? $value[$node['key']]
                    : null;

            case 'subexpression':
                // Evaluates the left child and passes the result to the
                // evaluation of the right child
                return $this->dispatch(
                    $node['children'][1],
                    $this->dispatch($node['children'][0], $value)
                );

            case 'index':
                // Returns an array index value or null if is not an array or
                // if the key does not exist.
                if (!is_array($value)) {
                    return null;
                }

                $index = $node['index'];
                if ($node['index'] < 0) {
                    $index += count($value);
                }

                return isset($value[$index]) ? $value[$index] : null;

            case 'projection':
                // Interprets a projection node, passing the values of the left
                // child through the values of the right child and aggregating
                // the non-null results into the return value.
                $left = $this->dispatch($node['children'][0], $value);

                if (!is_array($left)) {
                    return null;
                }

                // Validate the expected type of the projection
                if (isset($node['from']) && $left) {
                    $keys = array_keys($left);
                    if ($node['from'] == 'object' && $keys[0] === 0) {
                        return null;
                    } elseif ($node['from'] == 'array' && $keys[0] !== 0) {
                        return null;
                    }
                }

                $collected = [];
                foreach ($left as $val) {
                    if (null !== ($result = $this->dispatch($node['children'][1], $val))) {
                        $collected[] = $result;
                    }
                }

                return $collected;

            case 'flatten':
                // Evaluates the left child, then merges up arrays in the
                // values of the evaluation if the result is an array. After
                // merging, the result is passed to the evaluation of the right
                // child.
                static $skipElement = [];
                $value = $this->dispatch($node['children'][0], $value);

                if (!is_array($value)) {
                    return null;
                }

                // Ensure that it is not an object (hash)
                if ($value && ($keys = array_keys($value))) {
                    if ($keys[0] !== 0) {
                        return null;
                    }
                }

                $merged = [];
                foreach ($value as $values) {
                    // Only merge up arrays lists and not hashes
                    if (is_array($values) && isset($values[0])) {
                        $merged = array_merge($merged, $values);
                    } elseif ($values !== $skipElement) {
                        $merged[] = $values;
                    }
                }

                return $merged;

            case 'literal':
                // Returns the literal value
                return $node['value'];

            case 'current':
                // No-op used to return the current node and ensures binary
                // nodes have a left and right child.
                return $value;

            case 'or':
                // Evaluates the left child, and if it yields a falsey value,
                // then it evaluates and returns the result of the right child.
                $result = $this->dispatch($node['children'][0], $value);
                if (!$result && $result !== '0' && $result !== 0) {
                    $result = $this->dispatch($node['children'][1], $value);
                }

                return $result;

            case 'pipe':
                // Parses the left child, resets the parser state, and passes
                // the result of the left child to the right child.
                return $this->dispatch(
                    $node['children'][1],
                    $this->dispatch($node['children'][0], $value)
                );

            case 'multi_select_list':
                // If the current value is not an array or hash, then returns
                // null. For each child, it collects the results in an array
                // and finally returns the array.
                if ($value === null) {
                    return null;
                }

                $collected = [];
                foreach ($node['children'] as $node) {
                    $collected[] = $this->dispatch($node, $value);
                }

                return $collected;

            case 'multi_select_hash':
                // If the current value is not an array or object, then returns
                // null. For each child, it collects the results in an array and
                // finally returns the array.
                if ($value === null) {
                    return null;
                }

                $collected = [];
                foreach ($node['children'] as $node) {
                    $collected[$node['key']] = $this->dispatch(
                        $node['children'][0],
                        $value
                    );
                }

                return $collected;

            case 'comparator':
                // Returns the comparison of the evaluation of the left child
                // to the evaluation of the right child.
                $left = $this->dispatch($node['children'][0], $value);
                $right = $this->dispatch($node['children'][1], $value);
                switch ($node['relation']) {
                    case '==': return $left === $right;
                    case '!=': return $left !== $right;
                    case '>': return is_int($left) && is_int($right) && $left > $right;
                    case '>=': return is_int($left) && is_int($right) && $left >= $right;
                    case '<': return is_int($left) && is_int($right) && $left < $right;
                    case '<=': return is_int($left) && is_int($right) && $left <= $right;
                    default: return Lexer::validateBinaryOperator($node['relation']);
                }

            case 'condition':
                // Evaluates the left child, and if the left child returns
                // anything other than true, then a condition node returns
                // null. Otherwise, the condition node returns the result of
                // evaluation the right child.
                return true === $this->dispatch($node['children'][0], $value)
                    ? $this->dispatch($node['children'][1], $value)
                    : null;

            case 'function':
                // Executes a registered function by name.
                // Each child node is evaluated and collected into a list of
                // arguments. The list of arguments are then fed to the function
                // registered with the tree visitor.
                $args = [];
                foreach ($node['children'] as $arg) {
                    $args[] = $this->dispatch($arg, $value);
                }

                return $this->runtime->callFunction($node['fn'], $args);

            case 'slice':
                // Returns an array slice of the current value
                return $this->runtime->callFunction('slice', [
                    $value,
                    $node['args'][0],
                    $node['args'][1],
                    $node['args'][2],
                ]);

            case 'expression':
                // Handles expression tokens by executing child 0
                return new ExprNode($this, $node['children'][0]);

            default:
                throw new \RuntimeException("Unknown node type: {$node['type']}");
        }
    }
}
