<?php

namespace JmesPath\Fn;

/**
 * String type(mixed $subject)
 *
 * Returns the JavaScript type of the given $subject argument as a string
 * value.
 *
 * The return value MUST be one of the following:
 *
 * - Number
 * - String
 * - Boolean
 * - Array
 * - Object
 * - null
 */
class FnType extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1)
    );

    protected function execute(array $args)
    {
        static $map = array(
            'boolean' => 'Boolean',
            'string'  => 'String',
            'NULL'    => 'null',
            'float'   => 'Number',
            'integer' => 'Number'
        );

        $type = gettype($args[0]);

        if (isset($map[$type])) {
            return $map[gettype($args[0])];
        }

        if (!($keys = array_keys($args[0]))) {
            return 'Array';
        } elseif ($keys[0] === 0) {
            return 'Array';
        } else {
            return 'Object';
        }
    }
}
