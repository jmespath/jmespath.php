<?php

namespace JmesPath\Fn;

/**
 * String|null length(String $subject)
 *
 * Returns the length of the given argument using the following types:
 *
 * 1. String: returns the number of characters in the String
 * 2. Array: returns the number of elements in the Array
 * 3. Object: returns the number of key-value pairs in the Object
 * 4. Boolean, null: returns null
 */
class FnLength extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => array('string', 'array'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return is_array($args[0]) ? count($args[0]) : strlen($args[0]);
    }
}
