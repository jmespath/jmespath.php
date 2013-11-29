<?php

namespace JmesPath\Fn;

/**
 * Returns the provided argument in lowercase characters. If the provided
 * argument is not a String, this function MUST return null.
 */
class FnLowercase extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => 'string', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return strtolower($args[0]);
    }
}
