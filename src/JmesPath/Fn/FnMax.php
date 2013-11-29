<?php

namespace JmesPath\Fn;

/**
 * Returns the highest found Number in the provided Array or Object argument.
 * If the provided argument is not an Array or Object, this method MUST return
 * null. Any element in the sequence that is not a Number MUST be ignored from
 * the calculated result. If no Numeric values are found, this method MUST
 * return null.
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
