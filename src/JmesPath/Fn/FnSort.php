<?php

namespace JmesPath\Fn;

/**
 * sort($list)
 *
 * This function accepts an Array $list argument and returns the
 * lexicographically sorted elements of the $list as an Array. If the
 * provided $list is not an Array, this function returns null.
 *
 * Array element types are sorted in the following order (the lower the number
 * means the sooner in the list the element appears):
 *
 * 1. Object
 * 2. Array
 * 3. null
 * 4. Boolean
 * 5. Number
 * 6. String
 */
class FnSort extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        sort($args[0], SORT_NATURAL);

        return $args[0];
    }
}
