<?php

namespace JmesPath\Fn;

/**
 * Returns each argument concatenated one after the other. Any argument that
 * is not a String or Number is excluded from the concatenated result. If no
 * arguments are Strings or Numbers, this method MUST return null.
 */
class FnConcat extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, -1)
    );

    protected function execute(array $args)
    {
        $found = 0;
        $result = '';
        foreach ($args as $arg) {
            if (is_string($arg) || is_integer($arg)) {
                $result .= $arg;
                $found++;
            }
        }

        return $found ? $result : null;
    }
}
