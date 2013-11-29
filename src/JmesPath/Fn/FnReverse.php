<?php

namespace JmesPath\Fn;

/**
 * reverse($list)
 *
 * This function accepts an Array $list argument and returns the the
 * elements in reverse order. If the provided $list is not an array, this
 * function MUST return null.
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
