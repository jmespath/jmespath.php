<?php

namespace JmesPath\Fn;

/**
 * join($glue, $stringsArray)
 *
 * Returns all of the elements from the provided $stringsArray Array joined
 * together using the $glue argument as a separator between each. Any
 * element that is not a String or Number is excluded from the joined result.
 * If no arguments are Strings or Numbers, this method MUST return null.
 *
 * This function MUST raise an error if the provided $glue argument is not a
 * String.
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
        return implode(array_shift($args), array_filter($args, function ($arg) {
            return is_string($arg) || is_numeric($arg);
        }));
    }
}
