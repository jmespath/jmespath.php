<?php

namespace JmesPath\Fn;

/**
 * Number|null max(Array $collection)
 *
 * Returns the highest found Number in the provided Array argument. Any element
 * in the sequence that is not a Number MUST be ignored from the calculated
 * result.
 *
 * If the provided argument is not an Array, this function MUST return null.
 *
 * If no Numeric values are found, this function MUST return null.
 */
class FnMax extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            'default' => array('type' => array('array'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        $currentMax = null;
        foreach ($args[0] as $element) {
            if (is_numeric($element) && ($currentMax === null || $element > $currentMax)) {
                $currentMax = $element;
            }
        }

        return $currentMax;
    }
}
