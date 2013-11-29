<?php

namespace JmesPath\Fn;

/**
 * Returns an Object (hash) containing all of the provided arguments merged into
 * a single Object. If a key collision occurs, the first key value is used.
 *
 * This method requires at least two arguments. If any of the provided
 * arguments are not Objects, the argument is ignored from the resulting merged
 * object. If no Objects are found, this method MUST return null.
 */
class FnUnion extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, -1)
    );

    protected function execute(array $args)
    {
        $result = array();
        foreach ($args as $arg) {
            if ($arg && is_array($arg)) {
                if (!isset($arg[0])) {
                    $result += $arg;
                }
            }
        }

        return $result ?: null;
    }
}
