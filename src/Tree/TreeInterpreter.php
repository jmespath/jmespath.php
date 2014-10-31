<?php
namespace JmesPath\Tree;

use JmesPath\Runtime\RuntimeInterface;

/**
 * Tree visitor used to evaluates JMESPath AST expressions.
 */
class TreeInterpreter implements TreeVisitorInterface
{
    /** @var RuntimeInterface Runtime used to manage function calls */
    private $runtime;

    public function __construct(RuntimeInterface $runtime)
    {
        $this->runtime = $runtime;
    }

    public function visit(array $node, $data, array $args = null)
    {
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
                if (is_array($value) || $value instanceof \ArrayAccess) {
                    return isset($value[$node['key']])
                        ? $value[$node['key']]
                        : null;
                } elseif ($value instanceof \stdClass) {
                    return isset($value->{$node['key']})
                        ? $value->{$node['key']}
                        : null;
                } else {
                    return null;
                }

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
                if (!self::isArray($value)) {
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

                // Validate the expected type of the projection
                if ($node['from'] == 'object') {
                    if (!self::isObject($left)) {
                        return null;
                    }
                } elseif ($node['from'] == 'array') {
                    if (!self::isArray($left)) {
                        return null;
                    }
                } elseif (!is_array($left) || !($left instanceof \stdClass)) {
                    return null;
                }

                $collected = [];
                foreach ((array) $left as $val) {
                    $result = $this->dispatch($node['children'][1], $val);
                    if ($result !== null) {
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

                if (!self::isArray($value)) {
                    return null;
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
                    case '==': return self::valueCmp($left, $right);
                    case '!=': return !self::valueCmp($left, $right);
                    case '>': return is_int($left) && is_int($right) && $left > $right;
                    case '>=': return is_int($left) && is_int($right) && $left >= $right;
                    case '<': return is_int($left) && is_int($right) && $left < $right;
                    case '<=': return is_int($left) && is_int($right) && $left <= $right;
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

    public static function isObject($value)
    {
        if (!is_array($value)) {
            // Handle array-like values. Must be empty or offset 0 does not exist
            return $value instanceof \Countable && $value instanceof \ArrayAccess
                ? count($value) == 0 || !$value->offsetExists(0)
                : $value instanceof \stdClass;
        }

        return !$value || array_keys($value)[0] !== 0;
    }

    public static function isArray($value)
    {
        if (!is_array($value)) {
            // Handle array-like values. Must be empty or offset 0 exists.
            return $value instanceof \Countable && $value instanceof \ArrayAccess
                ? count($value) == 0 || $value->offsetExists(0)
                : false;
        }

        return !$value || array_keys($value)[0] === 0;
    }

    /**
     * JSON aware value comparison function.
     *
     * @param $a
     * @param $b
     *
     * @return bool
     */
    public static function valueCmp($a, $b)
    {
        if ($a === $b) {
            return true;
        } elseif ($a instanceof \stdClass) {
            return self::valueCmp((array) $a, $b);
        } elseif ($b instanceof \stdClass) {
            return self::valueCmp($a, (array) $b);
        } else {
            return false;
        }
    }
}
