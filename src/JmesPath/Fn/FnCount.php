<?php

namespace JmesPath\Fn;

/**
 * count($collection)
 *
 * Returns the number of elements in the $collection argument if
 * $collection is an Array or Object. Returns null if $collection
 * is not an Array or Object.
 */
class FnCount extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args'  => array(
            0 => array('type' => 'array', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        return count($args[0]);
    }
}
