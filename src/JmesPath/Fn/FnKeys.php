<?php

namespace JmesPath\Fn;

/**
 * Array|null keys(Object $obj)
 *
 * Returns an Array containing the hash keys of the provided Object.
 *
 * This function MUST return null if the provided argument is not an Object.
 */
class FnKeys extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            0 => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        if (!$args) {
            return array();
        } elseif (!isset($args[0][0])) {
            return null;
        } else {
            return array_keys($args[0][0]);
        }
    }
}
