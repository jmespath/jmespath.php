<?php

namespace JmesPath\Fn;

/**
 * Returns ``true`` if the given PCRE regular expression ``$pattern`` matches
 * the provided ``$subject`` string or ``false`` if the it does not match. This
 * function returns ``null`` if the provided ``$subject`` argument is not a
 * string. This function MUST fail if the provided ``$pattern`` argument is not
 * a string or if the provided ``$flags`` argument is not a string.
 *
 * This method accepts an optional argument, ``$flags``, to set options for
 * the interpretation of the regular expression. The argument accepts a
 * string, in which individual letters are used to set options. The presence of
 * a letter within the string indicates that the option is on; its absence
 * indicates that the option is off. Letters may appear in any order and may be
 * repeated.
 */
class FnMatches extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, 3),
        'args'  => array(
            0 => array('type' => 'string', 'failure' => 'null'),
            1 => array('type' => 'string'),
            2 => array('type' => 'string')
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
