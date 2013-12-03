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
 * If any of the elements of the given array are not Objects or the object does
 * not contain the given key, $key, the element is pushed down in the list.
 *
 * Values are sorted lexicographically. When a value is not a String, the
 * element is sorted in the following order (the lower the number means the
 * sooner in the list the element appears):
 *
 * 1. Object
 * 2. Array
 * 3. null
 * 4. Boolean
 * 5. Number
 * 6. String
 */
class FnSortBy extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, 2),
        'args'  => array(
            0 => array('type' => 'array', 'failure' => 'null'),
            1 => array('type' => 'string'),
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
