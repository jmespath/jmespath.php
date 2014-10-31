<?php
namespace JmesPath\Runtime;

/**
 * Represents a JMESPath runtime environment, encapsulating how JMESPath
 * expressions are parsed and evaluated.
 */
interface RuntimeInterface
{
    /**
     * Returns data from the provided input that matches a given JMESPath
     * expression.
     *
     * @param string $expression JMESPath expression to evaluate
     * @param mixed  $data       Data to search. This data should be data that
     *                           is similar to data returned from json_decode
     *                           using associative arrays rather than objects.
     *
     * @return mixed|null Returns the matching data or null
     */
    public function search($expression, $data);

    /**
     * Executes a JMESPath expression while emitting debug information to the
     * provided fopen resource.
     *
     * @param string   $expression JMESPath expression to evaluate
     * @param mixed    $data       JSON like data to search
     * @param resource $out        Resource as returned from fopen to write to
     *
     * @return mixed Returns the expression result
     */
    public function debug($expression, $data, $out = STDOUT);

    /**
     * Register a custom function with the interpreter.
     *
     * A function must be callable, receives an array of arguments, and returns
     * a function return value.
     *
     * @param string   $name Name of the function
     * @param callable $fn   Function
     *
     * @throws \InvalidArgumentException if the function is not callable or is
     *                                   a built-in function.
     */
    public function registerFunction($name, callable $fn);

    /**
     * Invokes a named function.
     *
     * @param string $name Name of the function to invoke
     * @param array  $args Function arguments
     * @return mixed Returns the function invocation result
     * @throws \RuntimeException If the function is undefined
     */
    public function callFunction($name, $args);

    /**
     * Clears the internal cache of the runtime
     */
    public function clearCache();
}
