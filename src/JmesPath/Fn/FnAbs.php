<?php

namespace JmesPath\Fn;

/**
 * Returns the absolute value of the provided argument. If the provided
 * argument is not a Number, then this method MUST return null.
 */
class FnAbs extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            'default' => array('type' => array('integer', 'double'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return abs($args[0]);
    }
}
