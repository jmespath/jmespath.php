<?php

namespace JmesPath\Fn;

/**
 * Returns the number of characters in a string.
 *
 * Arguments:
 * 1. The string to count the number of characters. SHOULD be a string. If
 *    anything other than a string is passed, this function will return null.
 */
class FnStrlen extends AbstractFn
{
    protected $rules = array(
        'arity' => 1,
        'args'  => array(
            0 => array('type' => 'string', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return strlen($args[0]);
    }
}
