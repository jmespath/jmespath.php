<?php

namespace JmesPath\Fn;

/**
 * Returns an integer representing the number of elements in an array of hash.
 *
 * Arguments:
 * 1. Array or hash to count. SHOULD be an array or hash. If anything else is
 *    passed, this function will return null.
 */
class FnCount extends AbstractFn
{
    protected $rules = array(
        'arity' => 1,
        'args'  => array(
            0 => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return count($args[0]);
    }
}
