<?php

namespace JmesPath\Fn;

/**
 * String|null matches(String $subject, String $pattern [, String $flags])
 *
 * Returns true if the given PCRE regular expression $pattern matches the
 * provided $subject string or false if it does not match.
 *
 * This function accepts an optional argument, $flags, to set options for the
 * interpretation of the regular expression. The argument accepts a string in
 * which individual letters are used to set options. The presence of a letter
 * within the string indicates that the option is on; its absence indicates
 * that the option is off. Letters may appear in any order and may be repeated.
 *
 * This function returns null if the provided $subject argument is not a string.
 *
 * This function MUST fail if the provided $pattern argument is not a string or
 * if the provided $flags argument is not a string.
 *
 * Flags
 *
 * - i: Case-insensitive matching.
 * - m: multiline; treat beginning and end characters (^ and $) as working over
 *   multiple lines (i.e., match the beginning or end of each line (delimited
 *   by n or r), not only the very beginning or end of the whole input string)

 */
class FnMatches extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, 3),
        'args'  => array(
            0 => array('type' => array('string'), 'failure' => 'null'),
            1 => array('type' => array('string')),
            2 => array('type' => array('string'))
        )
    );

    protected function execute(array $args)
    {
        $pattern = '/' . $args[1] . '/';
        if (isset($args[2])) {
            $pattern .= $args[2];
        }

        return (bool) preg_match($pattern, $args[0]);
    }
}
