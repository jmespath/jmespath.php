<?php

namespace JmesPath\Fn;

/**
 * Array|null reverse(Array $list)
 *
 * This function accepts an Array $list argument and returns the the elements
 * in reverse order.
 *
 * This function MUST return null if the provided argument is not an Array.
 */
class FnReverse extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return array_reverse($args[0]);
    }
}
