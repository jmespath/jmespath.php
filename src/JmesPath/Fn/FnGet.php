<?php

namespace JmesPath\Fn;

/**
 * mixed|null get(mixed $subject [, mixed $... ])
 *
 * This function accepts a variable number of arguments, each of which can be
 * of any type and returns the first argument that is not "falsey".
 *
 * This function MUST return null if all arguments are "falsey".
 *
 * "Falsey" is defined using the following semantics:
 *
 * - Boolean false
 * - Empty string
 * - null
 * - Empty Array
 * - Empty Object (hash)
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
