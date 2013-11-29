<?php

namespace JmesPath\Fn;

/**
 * length($subject)
 *
 * Returns the length of the string passed in the $subject argument. If
 * $subject is not a string, this method returns null.
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
