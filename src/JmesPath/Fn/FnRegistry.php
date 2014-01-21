<?php

namespace JmesPath\Fn;

/**
 * Handles the registration and invocation of JMESPath functions
 */
class FnRegistry
{
    /** @var array Map of function names to class names */
    private static $fnMap = array(
        'abs'         => 'JmesPath\Fn\FnAbs',
        'avg'         => 'JmesPath\Fn\FnAvg',
        'ceil'        => 'JmesPath\Fn\FnCeil',
        'concat'      => 'JmesPath\Fn\FnConcat',
        'contains'    => 'JmesPath\Fn\FnContains',
        'floor'       => 'JmesPath\Fn\FnFloor',
        'get'         => 'JmesPath\Fn\FnGet',
        'join'        => 'JmesPath\Fn\FnJoin',
        'keys'        => 'JmesPath\Fn\FnKeys',
        'matches'     => 'JmesPath\Fn\FnMatches',
        'max'         => 'JmesPath\Fn\FnMax',
        'min'         => 'JmesPath\Fn\FnMin',
        'length'      => 'JmesPath\Fn\FnLength',
        'lowercase'   => 'JmesPath\Fn\FnLowercase',
        'reverse'     => 'JmesPath\Fn\FnReverse',
        'sort'        => 'JmesPath\Fn\FnSort',
        'sort_by'     => 'JmesPath\Fn\FnSortBy',
        'substring'   => 'JmesPath\Fn\FnSubstring',
        'type'        => 'JmesPath\Fn\FnType',
        'union'       => 'JmesPath\Fn\FnUnion',
        'uppercase'   => 'JmesPath\Fn\FnUppercase',
        'values'      => 'JmesPath\Fn\FnValues',
        'array_slice' => 'JmesPath\Fn\FnArraySlice'
    );

    /** @var array Map of function names to instantiated function objects */
    private static $fn = array();

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
    public static function register($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        } elseif (isset(self::$fnMap)) {
            throw new \InvalidArgumentException(
                "Cannot override the built-in function, {$name}");
        }

        self::$fn[$name] = $fn;
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
    public static function invoke($name, $args)
    {
        if (!isset(self::$fn[$name])) {
            if (!isset(self::$fnMap[$name])) {
                throw new \RuntimeException("Call to undefined function: {$name}");
            } else {
                self::$fn[$name] = new self::$fnMap[$name];
            }
        }

        return call_user_func(self::$fn[$name], $args);
    }
}
