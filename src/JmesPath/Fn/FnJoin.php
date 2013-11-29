<?php

namespace JmesPath\Fn;

/**
 * This method accepts a variable number of arguments, and concatenates each
 * argument using the provided $join argument in position 0. The first
 * argument, $join, MUST be a string. If anything other than a string is
 * passed, this function MUST raise an error. The second argument SHOULD be an
 * Array or Object. If anything else is passed, this function MUST return null.
 */
class FnJoin extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, -1),
        'args' => array(
            0 => array('type' => 'string', 'failure' => 'null'),
            'default' => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return implode(array_shift($args), $args[0]);
    }
}
