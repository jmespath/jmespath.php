<?php

namespace JmesPath\Fn;

/**
 * Number|null ceil(Number $number)
 *
 * Returns the next highest integer value by rounding up if necessary.
 *
 * This function MUST return null if the provided argument is not a Number.
 */
class FnCeil extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            'default' => array('type' => array('integer', 'double'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return ceil($args[0]);
    }
}
