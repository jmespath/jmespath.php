<?php

namespace JmesPath\Fn;

/**
 * Number|null floor(Number $number)
 *
 * Returns the next lowest integer value by rounding down if necessary.
 *
 * This function MUST return null if the provided argument is not a Number.
 */
class FnFloor extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            'default' => array('type' => array('integer', 'double'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return floor($args[0]);
    }
}
