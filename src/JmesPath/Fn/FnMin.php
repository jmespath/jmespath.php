<?php

namespace JmesPath\Fn;

/**
 * Number|null min(Array $collection)
 *
 * Returns the lowest found Number in the provided Array argument.
 *
 * Any element in the sequence that is not a Number MUST be ignored from the
 * calculated result. If no Numeric values are found, this function MUST return
 * null.
 *
 * This function MUST return null if the provided argument is not an Array.
 */
class FnMin extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            'default' => array('type' => array('array'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        $currentMin = null;
        foreach ($args[0] as $element) {
            if (is_numeric($element) && ($currentMin === null || $element < $currentMin)) {
                $currentMin = $element;
            }
        }

        return $currentMin;
    }
}
