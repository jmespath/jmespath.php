<?php

namespace JmesPath\Fn;

/**
 * get($subject [, $... ])
 *
 * This method accepts a variable number of arguments, each of which can be of
 * any type. This method returns the first argument that is not falsey, or
 * returns null if all of the provided arguments are falsey.
 *
 * Falsey is defined using the following semantics:
 *
 * 1. Boolean false
 * 2. Empty string
 * 3. null
 * 4. Empty Array
 * 5. Empty Object (hash)
 *
 * Note that 0 is NOT a falsey value.
 */
class FnGet extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, -1)
    );

    protected function execute(array $args)
    {
        foreach ($args as $arg) {
            if ($arg || $arg === 0) {
                return $arg;
            }
        }

        return null;
    }
}
