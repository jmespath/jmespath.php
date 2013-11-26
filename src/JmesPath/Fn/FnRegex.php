<?php

namespace JmesPath\Fn;

/**
 * Performs a PCRE regular expression match and returns the Boolean result.
 *
 * Arguments:
 * 1. Regular expression pattern. MUST be a string.
 * 2. The subject of the regular expression. SHOULD be a string. When anything
 *    other than a string is supplied, this function will return null.
 */
class FnRegex extends AbstractFn
{
    protected $rules = array(
        'arity' => 2,
        'args'  => array(
            0 => array('type' => 'string'),
            1 => array('type' => 'string', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return (bool) preg_match($args[0], $args[1]);
    }
}
