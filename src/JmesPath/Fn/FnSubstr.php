<?php

namespace JmesPath\Fn;

/**
 * Returns a subset of a string given a starting position and a total number of
 * characters. If the starting position or ending position is less than zero,
 * the position is calculated as the total length of the string minus the given
 * position. For example, -1 would represent the second to last character of a
 * string.
 *
 * Arguments:
 * 1. The first argument is the subject that is being sliced. SHOULD be a
 *    string; If anything other than a string is provided, this function will
 *    return null.
 * 2. The second argument is the starting position in the string. MUST be an
 *    integer.
 * 3. The third argument is the total number of characters to get from the
 *    string after the starting position. MUST be an integer.
 */
class FnSubstr extends AbstractFn
{
    protected $rules = array(
        'arity' => 3,
        'args'  => array(
            0 => array('type' => 'string', 'failure' => 'null'),
            1 => array('type' => 'int'),
            2 => array('type' => 'int')
        )
    );

    protected function execute(array $args)
    {
        return substr($args[0], $args[1], $args[2]);
    }
}
