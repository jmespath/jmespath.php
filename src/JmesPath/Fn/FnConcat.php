<?php

namespace JmesPath\Fn;

/**
 * String|null concat(String|Number $string1, String|Number $string2 [, String|Number $... ])
 *
 * Returns each argument concatenated one after the other.
 *
 * Any argument that is not a String or Number is excluded from the
 * concatenated result. If no arguments are Strings or Numbers, this function
 * MUST return null.
 */
class FnConcat extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, -1)
    );

    protected function execute(array $args)
    {
        $result = '';
        foreach ($args as $arg) {
            if (is_string($arg) || is_numeric($arg)) {
                $result .= $arg;
            }
        }

        return $result;
    }
}
