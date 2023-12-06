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
     * @param callable|null $fnDispatcher Function dispatching function that accepts
     *                                    a function name argument and an array of
     *                                    function arguments and returns the result.
     */
    public function __construct(callable $fnDispatcher = null)
    {
        $this->fnDispatcher = $fnDispatcher ?: FnDispatcher::getInstance();
    }

    /**
     * Visits each node in a JMESPath AST and returns the evaluated result.
     *
     * @param array $node     JMESPath AST node
     * @param mixed $data     Data to evaluate
     * @param array $bindings Predefined variable bindings (keyed by variable name)
     *
     * @return mixed
     */
    public function visit(array $node, $data, array $bindings = [])
    {
        return $this->dispatch($node, $data);
    }

    /**
     * Recursively traverses an AST using depth-first, pre-order traversal.
     * The evaluation logic for each node type is embedded into a large switch
     * statement to avoid the cost of "double dispatch".
     * @return mixed
     */
    private function dispatch(array $node, $value, array $bindings = [])
    {
        $dispatcher = $this->fnDispatcher;

        switch ($node['type']) {

            case 'field':
                if (is_array($value) || $value instanceof \ArrayAccess) {
                    return isset($value[$node['value']]) ? $value[$node['value']] : null;
                } elseif ($value instanceof \stdClass) {
                    return isset($value->{$node['value']}) ? $value->{$node['value']} : null;
                }
                return null;

            case 'subexpression':
                return $this->dispatch(
                    $node['children'][1],
                    $this->dispatch($node['children'][0], $value, $bindings),
                    $bindings
                );

            case 'index':
                if (!Utils::isArray($value)) {
                    return null;
                }
                $idx = $node['value'] >= 0
                    ? $node['value']
                    : $node['value'] + count($value);
                return isset($value[$idx]) ? $value[$idx] : null;

            case 'projection':
                $left = $this->dispatch($node['children'][0], $value, $bindings);
                switch ($node['from']) {
                    case 'object':
                        if (!Utils::isObject($left)) {
                            return null;
                        }
                        break;
                    case 'array':
                        if (!Utils::isArray($left)) {
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
                    $result = $this->dispatch($node['children'][1], $val, $bindings);
                    if ($result !== null) {
                        $collected[] = $result;
                    }
                }

                return $collected;

            case 'flatten':
                static $skipElement = [];
                $value = $this->dispatch($node['children'][0], $value, $bindings);

                if (!Utils::isArray($value)) {
                    return null;
                }

                $merged = [];
                foreach ($value as $values) {
                    // Only merge up arrays lists and not hashes
                    if (is_array($values) && array_key_exists(0, $values)) {
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
                $result = $this->dispatch($node['children'][0], $value, $bindings);
                return Utils::isTruthy($result)
                    ? $result
                    : $this->dispatch($node['children'][1], $value, $bindings);

            case 'and':
                $result = $this->dispatch($node['children'][0], $value, $bindings);
                return Utils::isTruthy($result)
                    ? $this->dispatch($node['children'][1], $value, $bindings)
                    : $result;

            case 'not':
                return !Utils::isTruthy(
                    $this->dispatch($node['children'][0], $value, $bindings)
                );

            case 'pipe':
                return $this->dispatch(
                    $node['children'][1],
                    $this->dispatch($node['children'][0], $value, $bindings)
                );

            case 'multi_select_list':
                if ($value === null) {
                    return null;
                }

                $collected = [];
                foreach ($node['children'] as $node) {
                    $collected[] = $this->dispatch($node, $value, $bindings);
                }

                return $collected;

            case 'multi_select_hash':
                if ($value === null) {
                    return null;
                }

                $collected = [];
                foreach ($node['children'] as $node) {
                    $collected[$node['value']] = $this->dispatch(
                        $node['children'][0],
                        $value,
                        $bindings
                    );
                }

                return $collected;

            case 'comparator':
                $left = $this->dispatch($node['children'][0], $value, $bindings);
                $right = $this->dispatch($node['children'][1], $value, $bindings);
                if ($node['value'] == '==') {
                    return Utils::isEqual($left, $right);
                } elseif ($node['value'] == '!=') {
                    return !Utils::isEqual($left, $right);
                } else {
                    return self::relativeCmp($left, $right, $node['value']);
                }

            case 'condition':
                return Utils::isTruthy($this->dispatch($node['children'][0], $value, $bindings))
                    ? $this->dispatch($node['children'][1], $value, $bindings)
                    : null;

            case 'function':
                $args = [];
                foreach ($node['children'] as $arg) {
                    $args[] = $this->dispatch($arg, $value, $bindings);
                }
                return $dispatcher($node['value'], $args);

            case 'slice':
                return is_string($value) || Utils::isArray($value)
                    ? Utils::slice(
                        $value,
                        $node['value'][0],
                        $node['value'][1],
                        $node['value'][2]
                    ) : null;

            case 'expref':
                $apply = $node['children'][0];
                return function ($value) use ($apply, $bindings) {
                    return $this->visit($apply, $value, $bindings);
                };

            case 'let':
                return $this->dispatch(
                    $node['children'][1],
                    $value,
                    array_merge($bindings, $this->dispatch($node['children'][0], $value, $bindings))
                );

            case 'bindings':
                $newBindings = [];
                foreach ($node['children'] as $bindingNode) {
                    $newBindings[$bindingNode['value']] = $this->dispatch($bindingNode['children'][0], $value, $bindings);
                }
                return $newBindings;

            case 'variable':
                if (!array_key_exists($node['value'], $bindings)) {
                    throw new \RuntimeException("Undefined variable: \${$node['value']}");
                }

                return $bindings[$node['value']];

            default:
                throw new \RuntimeException("Unknown node type: {$node['type']}");
        }
    }

    /**
     * @return bool
     */
    private static function relativeCmp($left, $right, $cmp)
    {
        if (!(is_int($left) || is_float($left)) || !(is_int($right) || is_float($right))) {
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
