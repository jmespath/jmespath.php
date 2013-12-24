<?php

namespace JmesPath\Fn;

/**
 * Array|null sort(Array $list, String $key)
 *
 * This function accepts an Array ($list) argument that contains Objects and
 * returns the Array of Objects sorted lexicographically by a specific key
 * ($key) of each Object.
 *
 * This function MUST return null if the provided argument is not an Array.
 *
 * Elements in the resulting Array are sorted in the following order:
 *
 * 1. Objects that contain the ``$key`` argument sorted lexicographically by
 *    value
 * 2. Objects that do not contain the ``$key`` argument
 * 3. Arrays
 * 4. nulls
 * 5. Booleans
 * 6. Numbers
 * 7. Strings
 */
class FnSortBy extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, 2),
        'args'  => array(
            0 => array('type' => array('array'), 'failure' => 'null'),
            1 => array('type' => array('string')),
        )
    );

    protected function execute(array $args)
    {
        static $order = array(
            'array'   => 0,
            'NULL'    => 1,
            'boolean' => 2,
            'integer' => 3,
            'double'  => 3,
            'string'  => 4
        );

        // Must be an Array
        if ($args[0] && !isset($args[0][0])) {
            return null;
        }

        $key = $args[1];

        usort($args[0], function ($a, $b) use ($key, $order) {
            // Different types, so no comparison
            if ($typeCmp = $order[gettype($a)] - $order[gettype($b)]) {
                return $typeCmp;
            } elseif (!isset($a[$key])) {
                return !isset($b[$key]) ? 0 : 1;
            } elseif (!isset($b[$key])) {
                return -1;
            } else {
                return strnatcmp($a[$key], $b[$key]);
            }
        });

        return $args[0];
    }
}
