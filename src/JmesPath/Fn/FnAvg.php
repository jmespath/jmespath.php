<?php

namespace JmesPath\Fn;

/**
 * avg($collection)
 *
 * Returns the average of the elements in the provided Array or Object of
 * Numbers. If the provided argument, $collection, is not an Array or Object,
 * this function MUST return null. Elements in the collection that are not
 * Numbers are excluded from the averaged result. If no elements are Numbers,
 * then this method MUST return null.
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
