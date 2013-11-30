<?php

namespace JmesPath\Fn;

/**
 * String|null length(String $subject)
 *
 * Returns the length of the String passed in the $subject argument.
 *
 * If $subject is not a String, this function MUST return null.
 */
class FnLength extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => 'string', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return strlen($args[0]);
    }
}
