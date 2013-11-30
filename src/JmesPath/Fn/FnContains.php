<?php

namespace JmesPath\Fn;

/**
 * Boolean|null contains(Array|String $subject, String|Number $search)
 *
 * Returns true if the given $subject contains the provided $search String. The
 * $search argument can be either a String or Number.
 *
 * If $subject is an Array, this function returns true if one of the elements
 * in the Array is equal to the provided $search value.
 *
 * If the provided $subject is a String, this function returns true if the
 * string contains the provided $search argument.
 *
 * This function returns null if the given $subject argument is not an Array or
 * String.
 *
 * This function MUST raise an error if the provided $search argument is not a
 * String or Number.
 */
class FnContains extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 2),
        'args'  => array(
            0 => array('type' => array('string', 'array'), 'failure' => 'null'),
            1 => array('type' => array('string', 'integer'))
        )
    );

    protected function execute(array $args)
    {
        return is_array($args[0])
            ? in_array($args[1], $args[0])
            : strpos($args[0], $args[1]) !== false;
    }
}
