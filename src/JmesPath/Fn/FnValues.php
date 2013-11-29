<?php

namespace JmesPath\Fn;

/**
 * Array|null values(Object $obj)
 *
 * Returns the values of the provided Object. If the given argument is an
 * Array, this function transparently returns the given argument. If the given
 * argument is not an Object or Array, this function MUST return null.
 */
class FnValues extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            0 => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return array_values($args[0]);
    }
}
