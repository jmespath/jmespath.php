<?php

namespace JmesPath\Fn;

/**
 * String|null substring(String $subject, Number $start [, Number $length])
 *
 * Returns a subset of the given string in the $subject argument starting at
 * the given $start position. If no $length argument is provided, the function
 * will return the entire remainder of a string after the given $start
 * position. If the $length argument is provided, the function will return a
 * subset of the string starting at the given $start position and ending at the
 * $start position + $length position.
 *
 * The provided $start and $length arguments MUST be an integer. If a negative
 * integer is provided for the $start argument, the start position is
 * calculated as the total length of the string + the provided $start argument.
 *
 * If the given $subject is not a String, this function returns null.
 *
 * This function MUST raise an error if the given $start or $length arguments
 * are not Numbers.
 */
class FnSubstring extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, 3),
        'args'  => array(
            0 => array('type' => array('string'), 'failure' => 'null'),
            1 => array('type' => array('integer')),
            2 => array('type' => array('integer'))
        )
    );

    protected function execute(array $args)
    {
        return !isset($args[2])
            ? substr($args[0], $args[1])
            : substr($args[0], $args[1], $args[2]);
    }
}
