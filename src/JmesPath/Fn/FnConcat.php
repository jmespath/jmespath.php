<?php

namespace JmesPath\Fn;

/**
 * concat($string1, $string2 [, $... ])
 *
 * Returns each argument concatenated one after the other. Any argument that
 * is not a String or Number is excluded from the concatenated result.
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
