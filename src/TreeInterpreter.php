<?php
namespace JmesPath;

/**
 * Tree visitor used to evaluates JMESPath AST expressions.
 */
class TreeInterpreter
{
    /** @var callable */
    private $fnDispatcher;

    /**
     * @param callable $fnDispatcher Function dispatching function that accepts
     *                               a function name argument and an array of
     *                               function arguments and returns the result.
     */
    public function __construct(callable $fnDispatcher = null)
    {
        $this->fnDispatcher = $fnDispatcher ?: FnDispatcher::getInstance();
    }

    /**
     * Visits each node in a JMESPath AST and returns the evaluated result.
     *
     * @param array $node JMESPath AST node
     * @param mixed $data Data to evaluate
     *
     * @return mixed
     */
    public function visit(array $node, $data)
    {
        return $this->dispatch($node, $data);
    }

    /**
     * Determine if the provided value is a JMESPath compatible object.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isObject($value)
    {
        if (is_array($value)) {
            return !$value || array_keys($value)[0] !== 0;
        }

        // Handle array-like values. Must be empty or offset 0 does not exist
        return $value instanceof \Countable && $value instanceof \ArrayAccess
            ? count($value) == 0 || !$value->offsetExists(0)
            : $value instanceof \stdClass;
    }

    /**
     * Determine if the provided value is a JMESPath compatible array.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isArray($value)
    {
        if (is_array($value)) {
            return !$value || array_keys($value)[0] === 0;
        }

        // Handle array-like values. Must be empty or offset 0 exists.
        return $value instanceof \Countable && $value instanceof \ArrayAccess
            ? count($value) == 0 || $value->offsetExists(0)
            : false;
    }

    /**
     * JSON aware value comparison function.
     *
     * @param mixed $a First value to compare
     * @param mixed $b Second value to compare
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

    /**
     * Recursively traverses an AST using depth-first, pre-order traversal.
     * The evaluation logic for each node type is embedded into a large switch
     * statement to avoid the cost of "double dispatch".
     */
    private function dispatch(array $node, $value)
    {
        $dispatcher = $this->fnDispatcher;

        switch ($node['type']) {

            case 'field':
                if (is_array($value) || $value instanceof \ArrayAccess) {
                    return isset($value[$node['key']]) ? $value[$node['key']] : null;
                } elseif ($value instanceof \stdClass) {
                    return isset($value->{$node['key']}) ? $value->{$node['key']} : null;
                }
                return null;

            case 'subexpression':
                return $this->dispatch(
                    $node['children'][1],
                    $this->dispatch($node['children'][0], $value)
                );

            case 'index':
                if (!self::isArray($value)) {
                    return null;
                }
                $idx = $node['index'] >= 0
                    ? $node['index']
                    : $node['index'] + count($value);
                return isset($value[$idx]) ? $value[$idx] : null;

            case 'projection':
                $left = $this->dispatch($node['children'][0], $value);
                switch ($node['from']) {
                    case 'object':
                        if (!self::isObject($left)) {
                            return null;
                        }
                        break;
                    case 'array':
                        if (!self::isArray($left)) {
                            return null;
                        }
                        break;
                    default:
                        if (!is_array($left) || !($left instanceof \stdClass)) {
                            return null;
                        }
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
                return $node['value'];

            case 'current':
                return $value;

            case 'or':
                $result = $this->dispatch($node['children'][0], $value);
                if (!$result && $result !== '0' && $result !== 0) {
                    $result = $this->dispatch($node['children'][1], $value);
                }

                return $result;

            case 'pipe':
                return $this->dispatch(
                    $node['children'][1],
                    $this->dispatch($node['children'][0], $value)
                );

            case 'multi_select_list':
                if ($value === null) {
                    return null;
                }

                $collected = [];
                foreach ($node['children'] as $node) {
                    $collected[] = $this->dispatch($node, $value);
                }

                return $collected;

            case 'multi_select_hash':
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
                $left = $this->dispatch($node['children'][0], $value);
                $right = $this->dispatch($node['children'][1], $value);
                if ($node['relation'] == '==') {
                    return self::valueCmp($left, $right);
                } elseif ($node['relation'] == '!=') {
                    return !self::valueCmp($left, $right);
                } else {
                    return self::relativeCmp($left, $right, $node['relation']);
                }

            case 'condition':
                return true === $this->dispatch($node['children'][0], $value)
                    ? $this->dispatch($node['children'][1], $value)
                    : null;

            case 'function':
                $args = [];
                foreach ($node['children'] as $arg) {
                    $args[] = $this->dispatch($arg, $value);
                }
                return $dispatcher($node['fn'], $args);

            case 'slice':
                return $dispatcher('slice', [
                    $value,
                    $node['args'][0],
                    $node['args'][1],
                    $node['args'][2],
                ]);

            case 'expression':
                $apply = $node['children'][0];
                return function ($value) use ($apply) {
                    $this->visit($apply, $value);
                };

            default:
                throw new \RuntimeException("Unknown node type: {$node['type']}");
        }
    }

    private static function relativeCmp($left, $right, $cmp)
    {
        if (!is_int($left) || !is_int($right)) {
            return false;
        }

        switch ($cmp) {
            case '>': return $left > $right;
            case '>=': return $left >= $right;
            case '<': return $left < $right;
            case '<=': return $left <= $right;
            default: throw new \RuntimeException("Invalid comparison: $cmp");
        }
    }
}
