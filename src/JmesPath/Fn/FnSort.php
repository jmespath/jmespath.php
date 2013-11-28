<?php

namespace JmesPath\Fn;

/**
 * This function accepts an Array ``$subject`` argument and returns the
 * Lexicographically sorted elements of the ``$subject`` as an Array. If the
 * provided ``$subject`` is not an Array, this function returns ``null``.
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
