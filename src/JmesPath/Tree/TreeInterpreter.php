<?php

namespace JmesPath\Tree;

/**
 * Tree visitor used to evaluates JMESPath AST expressions.
 */
class TreeInterpreter implements TreeVisitorInterface
{
    /** @var mixed The current evaluated root node */
    private $root;

    /** @var array Map of function names to class names */
    private $fnMap = array(
        'abs' => 'JmesPath\Fn\FnAbs',
        'avg' => 'JmesPath\Fn\FnAvg',
        'ceil' => 'JmesPath\Fn\FnCeil',
        'concat' => 'JmesPath\Fn\FnConcat',
        'contains' => 'JmesPath\Fn\FnContains',
        'floor' => 'JmesPath\Fn\FnFloor',
        'get' => 'JmesPath\Fn\FnGet',
        'join' => 'JmesPath\Fn\FnJoin',
        'keys' => 'JmesPath\Fn\FnKeys',
        'matches' => 'JmesPath\Fn\FnMatches',
        'max' => 'JmesPath\Fn\FnMax',
        'min' => 'JmesPath\Fn\FnMin',
        'length' => 'JmesPath\Fn\FnLength',
        'lowercase' => 'JmesPath\Fn\FnLowercase',
        'reverse' => 'JmesPath\Fn\FnReverse',
        'sort' => 'JmesPath\Fn\FnSort',
        'sort_by' => 'JmesPath\Fn\FnSortBy',
        'substring' => 'JmesPath\Fn\FnSubstring',
        'type' => 'JmesPath\Fn\FnType',
        'union' => 'JmesPath\Fn\FnUnion',
        'uppercase' => 'JmesPath\Fn\FnUppercase',
        'values' => 'JmesPath\Fn\FnValues',
        '_array_slice' => 'JmesPath\Fn\FnArraySlice'
    );

    /** @var array Map of function names to instantiated function objects */
    private $fn = array();

    /**
     * Handles evaluating undefined types without paying the cost of validation
     */
    public function __call($method, $args)
    {
        throw new \RuntimeException('Invalid node encountered: ' . json_encode($args[0]));
    }

    /**
     * Register a custom function with the interpreter.
     *
     * A function must be callable, receives an array of arguments, and returns
     * a function return value.
     *
     * @param string   $name Name of the function
     * @param callable $fn   Function
     *
     * @throws \InvalidArgumentException
     */
    public function registerFunction($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        }

        $this->fn[$name] = $fn;
    }

    public function visit(array $node, array $args = null)
    {
        $this->root = $args;

        return $this->dispatch($node, $args);
    }

    /**
     * Calls a visit method based on the current node. Error handling is
     * performed in the `__call()` method.
     */
    private function dispatch(array $node, $value)
    {
        return $this->{"visit_{$node['type']}"}($node, $value);
    }

    /**
     * Evaluates the left child, and if it yields a null value, then it
     * evaluates and returns the result of the right child.
     */
    private function visit_or(array $node, $value)
    {
        $result = $this->dispatch($node['children'][0], $value);
        if ($result === null) {
            $result = $this->dispatch($node['children'][1], $value);
        }

        return $result;
    }

    /**
     * Evaluates the left child and passes the result to the evaluation of the
     * right child.
     */
    private function visit_subexpression(array $node, $value)
    {
        return $this->dispatch(
            $node['children'][1],
            $this->dispatch($node['children'][0], $value)
        );
    }

    /**
     * Returns the key value of a hash or null if is not a hash or if the key
     * does not exist.
     */
    private function visit_field(array $node, $value)
    {
        if (!is_array($value)) {
            return null;
        }

        return isset($value[$node['key']])
            ? $value[$node['key']]
            : null;
    }

    /**
     * Returns an array index value or null if is not an array or if the key
     * does not exist.
     */
    private function visit_index(array $node, $value)
    {
        if (!is_array($value)) {
            return null;
        }

        $index = $node['index'];
        if ($node['index'] < 0) {
            $index += count($value);
        }

        return isset($value[$index])
            ? $value[$index]
            : null;
    }

    /**
     * Returns a literal value
     */
    private function visit_literal(array $node, $value)
    {
        return $node['value'];
    }

    /**
     * Parses the left child, resets the parser state, and passes the result
     * of the left child to the right child.
     */
    private function visit_pipe(array $node, $value)
    {
        $value = $this->dispatch($node['children'][0], $value);
        $this->root = $value;

        return $this->dispatch($node['children'][1], $value);
    }

    /**
     * Returns the evaluation of a multi-select-list.
     *
     * If the current value is not an array or hash, then returns null. For
     * each child, it collects the results in an array and finally returns the
     * array.
     */
    private function visit_multi_select_list(array $node, $value)
    {
        if ($value === null) {
            return null;
        }

        $collected = array();
        foreach ($node['children'] as $node) {
            $collected[] = $this->dispatch($node, $value);
        }

        return $collected;
    }

    /**
     * Returns the evaluation of a multi-select-hash.
     *
     * If the current value is not an array or object, then returns null. For
     * each child, it collects the results in an array and finally returns the
     * array.
     */
    private function visit_multi_select_hash(array $node, $value)
    {
        if (!is_array($value)) {
            return null;
        }

        $collected = array();
        foreach ($node['children'] as $node) {
            $collected[$node['key']] = $this->dispatch(
                $node['children'][0],
                $value
            );
        }

        return $collected;
    }

    /**
     * Returns the comparison of the evaluation of the left child to the
     * evaluation of the right child.
     */
    private function visit_operator(array $node, $value)
    {
        $left = $this->dispatch($node['children'][0], $value);
        $right = $this->dispatch($node['children'][1], $value);

        switch ($node['relation']) {
            case 'eq': return $left === $right;
            case 'not': return $left !== $right;
            case 'gt': return is_int($left) && is_int($right) && $left > $right;
            case 'gte': return is_int($left) && is_int($right) && $left >= $right;
            case 'lt': return is_int($left) && is_int($right) && $left < $right;
            case 'lte': return is_int($left) && is_int($right) && $left <= $right;
        }

        throw new \RuntimeException('Invalid relation: ' . $node['relation']);
    }

    /**
     * Executes a registered function by name.
     *
     * Each child node is evaluated and collected into a list of arguments.
     * The list of arguments are then fed to the function registered with the
     * tree visitor.
     */
    private function visit_function(array $node, $value)
    {
        $args = array();
        foreach ($node['children'] as $arg) {
            $args[] = $this->dispatch($arg, $value);
        }

        return $this->callFunction($node['fn'], $args);
    }

    /**
     * Returns an array slice of the current value
     */
    private function visit_slice(array $node, $value)
    {
        return $this->callFunction('_array_slice', array(
            $value,
            $node['args'][0],
            $node['args'][1],
            $node['args'][2],
        ));
    }

    /**
     * No-op used to return the current node and ensures binary nodes have
     * a left and right child.
     */
    private function visit_current_node(array $node, $value)
    {
        return $value;
    }

    /**
     * Merges the current $value and creates a projection.
     *
     * First the left child is evaluated. If the left child evaluates to a
     * non-array value, then null is returned. Otherwise, each value of the
     * child is fed into a projection and results are collected into an array.
     */
    private function visit_merge(array $node, $value)
    {
        static $skipElement = array();

        $result = $this->dispatch($node['children'][0], $value);
        if (!is_array($result)) {
            return null;
        }

        // Ensure that it is not an object (hash)
        if ($result) {
            $keys = array_keys($result);
            if ($keys[0] !== 0) {
                return null;
            }
        }

        $merged = array();
        foreach ($result as $values) {
            // Only merge up arrays lists and not hashes
            if (is_array($values) && isset($values[0])) {
                $merged = array_merge($merged, $values);
            } elseif ($values != $skipElement) {
                $merged[] = $values;
            }
        }

        return $this->projection($node['children'][1], $merged);
    }

    /**
     * Creates a projection for arrays (similar to merge but w/o the merge)
     */
    private function visit_wildcard_index(array $node, $value)
    {
        $result = $this->dispatch($node['children'][0], $value);
        // Validate that it is not an object
        if (!is_array($result)) {
            return null;
        }

        // Ensure that this is an array
        $keys = array_keys($result);
        if ($keys && $keys[0] !== 0) {
            return null;
        }

        return $this->projection($node['children'][1], $result);
    }

    /**
     * Creates a projection for objects (similar to merge but w/o the merge)
     */
    private function visit_wildcard_values(array $node, $value)
    {
        $result = $this->dispatch($node['children'][0], $value);
        // Validate that it is not a list
        if (!is_array($result)) {
            return null;
        }

        // Ensure that this is a hash
        $keys = array_keys($result);
        if ($keys && $keys[0] === 0) {
            return null;
        }

        return $this->projection($node['children'][1], $result);
    }

    /**
     * Creates a basic projection.
     *
     * Each element of the provided value is run through the provided $node
     * AST. Elements that yield non-null values are added to the collected
     * array of projection matches and returned.
     */
    private function projection(array $node, $value)
    {
        $collected = array();
        foreach ($value as $val) {
            if (null !== ($result = $this->dispatch($node, $val))) {
                $collected[] = $result;
            }
        }

        return $collected;
    }

    /**
     * Visits a filter expression.
     *
     * The left child is the value being filtered. The 'expression' attribute
     * is the AST used to evaluate the left expression using a projection, and
     * the right child is the AST representing the nodes to extract from the
     * elements that pass through the filter into the projection.
     *
     * child[0] -> expression -> projection -> child[1]
     */
    private function visit_filter(array $node, $value)
    {
        $result = $this->dispatch($node['children'][0], $value);

        if (!is_array($result)) {
            return null;
        } elseif (!$node['children']) {
            return array_values($result);
        }

        $collected = array();
        foreach ($result as $val) {
            $valid = $this->dispatch($node['expression'], $val);
            if ($valid === true) {
                if (null !== ($childResult = $this->dispatch($node['children'][1], $val))) {
                    $collected[] = $childResult;
                }
            }
        }

        return $collected;
    }

    /**
     * Invokes a named function. If the function has not already been
     * instantiated, the function object is created and cached.
     *
     * @param string $name Name of the function to invoke
     * @param array  $args Function arguments
     * @return mixed Returns the function invocation result
     * @throws \RuntimeException If the function is undefined
     */
    private function callFunction($name, $args)
    {
        if (!isset($this->fn[$name])) {
            if (!isset($this->fnMap[$name])) {
                throw new \RuntimeException("Call to undefined function: {$name}");
            } else {
                $this->fn[$name] = new $this->fnMap[$name];
            }
        }

        return call_user_func($this->fn[$name], $args);
    }
}
