<?php

namespace JmesPath\Fn;

/**
 * String|null join(String $glue, Array $stringsArray)
 *
 * Returns all of the elements from the provided $stringsArray Array joined
 * together using the $glue argument as a separator between each.
 *
 * Any element that is not a String or Number is excluded from the joined
 * result.
 *
 * This function MUST return null if $stringsArray is not an Array.
 *
 * This function MUST raise an error if the provided $glue argument is not a
 * String.
 */
class FnJoin extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, -1),
        'args' => array(
            0 => array('type' => array('string'), 'failure' => 'null'),
            'default' => array('type' => array('array'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return implode(array_shift($args), array_filter($args[0], function ($arg) {
            return is_string($arg) || is_numeric($arg);
        }));
    }
}
