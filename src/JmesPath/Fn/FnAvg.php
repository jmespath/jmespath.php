<?php

namespace JmesPath\Fn;

/**
 * Number|null avg(Array $arr)
 *
 * Returns the average of the elements in the provided Array.
 *
 * Elements in the Array that are not Numbers are excluded from the averaged
 * result. If no elements are Numbers, then this function MUST return null.
 *
 * If the provided argument, $arr, is not an Array, this function MUST return null.
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

        return $total ? $sum / $total : null;
    }
}
