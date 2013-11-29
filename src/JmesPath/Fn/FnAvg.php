<?php

namespace JmesPath\Fn;

/**
 * Returns the average of the provided Array or Object of Numbers. If the
 * provided argument is not an Array or Object, this function MUST return null.
 * If any of the provided elements in the sequence are not Numbers, the element
 * is excluded from the averaged result.
 */
class FnAvg extends AbstractFn
{
    protected $rules = array(
        'arity' => array(1, 1),
        'args' => array(
            0 => array('type' => array('array'), 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        $sum = $total = 0;
        foreach ($args[0] as $v) {
            if (is_numeric($v)) {
                $total++;
                $sum += $v;
            }
        }

        return $total ? $sum / $total : 0;
    }
}
