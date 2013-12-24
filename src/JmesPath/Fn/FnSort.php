<?php

namespace JmesPath\Fn;

/**
 * Array|null sort(Array $list)
 *
 * This function accepts an Array $list argument and returns the
 * lexicographically sorted elements of the $list as an Array.
 *
 * This function MUST return null if the provided argument is not an Array.
 *
 * Array element types are sorted in the following order (the lower the number
 * means the sooner in the list the element appears):
 *
 * - Object
 * - Array
 * - null
 * - Boolean
 * - Number
 * - String
 */
class FnSort extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => array('array'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        if ($args[0] && !isset($args[0][0])) {
            return null;
        }

        natsort($args[0]);

        return $args[0];
    }
}
