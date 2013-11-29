<?php

namespace JmesPath\Fn;

/**
 * uppercase($subject)
 *
 * Returns the provided argument in uppercase characters. If the provided
 * argument is not a String, this function MUST return null.
 */
class FnUppercase extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => 'string', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return strtoupper($args[0]);
    }
}
