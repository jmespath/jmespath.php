<?php

namespace JmesPath\Fn;

/**
 * array _array_slice(array $arr, $start = null, $end = null, $step = null)
 *
 * Slices an array using Python slicing syntax.
 * @link http://docs.python.org/2/tutorial/introduction.html#strings
 */
class FnArraySlice extends AbstractFn
{
    protected $rules = array(
        'arity' => array(4, 4),
        'args' => array(
            0 => array('type' => array('array'), 'failure' => 'null'),
            1 => array('type' => array('integer', 'NULL')),
            2 => array('type' => array('integer', 'NULL')),
            3 => array('type' => array('integer', 'NULL'))
        )
    );

    protected function execute(array $args)
    {
        // Do not operate on objects
        if ($args && !isset($args[0][0])) {
            return null;
        }

        return $this->sliceIndices($args[0], $args[1], $args[2], $args[3]);
    }

    private function adjustEndpoint($length, $endpoint, $step)
    {
        if ($endpoint < 0) {
            $endpoint += $length;
            if ($endpoint < 0) {
                $endpoint = $step < 0 ? -1 : 0;
            }
        } elseif ($endpoint >= $length) {
            $endpoint = $step < 0 ? $length - 1 : $length;
        }

        return $endpoint;
    }

    private function adjustSlice($length, $start, $stop, $step)
    {
        if ($step === null) {
            $step = 1;
        } elseif ($step === 0) {
            throw new \RuntimeException('step cannot be 0');
        }

        if ($start === null) {
            $start = $step < 0 ? -1 : 0;
        } else {
            $start = $this->adjustEndpoint($length, $start, $step);
        }

        if ($stop === null) {
            $stop = $step < 0 ? -1 : $length;
        } else {
            $stop = $this->adjustEndpoint($length, $stop, $step);
        }

        return array($start, $stop, $step);
    }

    private function sliceIndices($arr, $start, $stop, $step)
    {
        $length = count($arr);
        list($start, $stop, $step) = $this->adjustSlice($length, $start, $stop, $step);

        $result = array();
        if ($step > 0) {
            for ($i = $start; $i < $stop; $i += $step) {
                $result[] = $arr[$i];
            }
        } else {
            for ($i = $start; $i >= $stop; $i += $step) {
                $result[] = $arr[$i];
            }
        }

        return $result;
    }
}
