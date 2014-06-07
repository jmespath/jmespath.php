<?php

namespace JmesPath;

use JmesPath\Tree\ExprNode;

/**
 * Provides implementations for all of the built-in JMESPath functions
 */
class DefaultFunctions
{
    public static function abs(array $args)
    {
        self::validate($args, array(array('number')));

        return abs($args[0]);
    }

    public static function avg(array $args)
    {
        self::validate($args, array(array('array')));

        $sum = $total = 0;
        foreach ($args[0] as $v) {
            $type = self::gettype($v);
            if ($type != 'number') {
                self::typeError('avg', 0, 'an array of numbers');
            }
            $total++;
            $sum += $v;
        }

        return $total ? $sum / $total : null;
    }

    public static function ceil(array $args)
    {
        self::validate($args, array(array('number')));

        return ceil($args[0]);
    }

    public static function contains(array $args)
    {
        self::validate($args, array(
            0 => array('string', 'array')
        ), 2, 2);

        if (is_array($args[0])) {
            return in_array($args[1], $args[0]);
        } elseif (is_string($args[1])) {
            return strpos($args[0], $args[1]) !== false;
        }

        return null;
    }

    public static function floor(array $args)
    {
        self::validate($args, array(array('number')));

        return floor($args[0]);
    }

    public static function not_null(array $args)
    {
        self::validate($args, array(), 1, -1);

        foreach ($args as $arg) {
            if ($arg !== null) {
                return $arg;
            }
        }

        return null;
    }

    public static function join(array $args)
    {
        self::validate($args, array(
            0     => array('string'),
            '...' => array('array')
        ), 2, -1);

        $result = '';
        foreach ($args[1] as $ele) {
            if (!is_string($ele)) {
                self::typeError('join', 1, 'must be an array of strings');
            }
            $result .= $ele . $args[0];
        }

        return rtrim($result, $args[0]);
    }

    public static function keys(array $args)
    {
        self::validateArity(1, 1, $args);

        if (empty($args[0]) && is_array($args[0])) {
            return array();
        } elseif (!self::validate($args, array(array('object')), 1, 1)) {
            self::typeError('keys', 0, 'must be an object');
        } else {
            return array_keys($args[0]);
        }
    }

    public static function length(array $args)
    {
        self::validate($args, array(array('string', 'array', 'object')));

        return is_array($args[0]) ? count($args[0]) : strlen($args[0]);
    }

    public static function max(array $args)
    {
        self::validate($args, array(array('array')), 1, 1);

        $currentMax = null;
        foreach ($args[0] as $element) {
            $type = self::gettype($element);
            if ($type !== 'number') {
                self::typeError('max', 0, 'must be an array of numbers');
            } elseif ($currentMax === null || $element > $currentMax) {
                $currentMax = $element;
            }
        }

        return $currentMax;
    }

    public static function min(array $args)
    {
        self::validate($args, array(array('array')), 1, 1);

        $currentMin = null;
        foreach ($args[0] as $element) {
            $type = self::gettype($element);
            if ($type !== 'number') {
                self::typeError('min', 0, 'must be an array of numbers');
            } elseif ($currentMin === null || $element < $currentMin) {
                $currentMin = $element;
            }
        }

        return $currentMin;
    }

    public static function sum(array $args)
    {
        self::validate($args, array(array('array')), 1, 1);

        $sum = 0;
        foreach ($args[0] as $element) {
            $type = self::gettype($element);
            if ($type != 'number') {
                self::typeError('sum', 0, 'must be an array of numbers');
            }
            $sum += $element;
        }

        return $sum;
    }

    public static function sort(array $args)
    {
        self::validate($args, [['array']], 1, 1);

        if (empty($args[0])) {
            return array();
        }

        usort($args[0], function ($a, $b) {
            $at = self::gettype($a);
            $bt = self::gettype($b);
            if ($at != $bt || ($at != 'number' && $at != 'string')) {
                self::typeError('sort', 0, 'must be an array of string or numbers');
            }
            return strnatcmp($a, $b);
        });

        return array_values($args[0]);
    }

    public static function sort_by(array $args)
    {
        self::validate($args, [['array'], ['expression']], 2, 2);

        $i = $args[1]->interpreter;
        $expr = $args[1]->node;
        usort($args[0], function ($a, $b) use ($i, $expr) {
            $va = $i->visit($expr, $a);
            $vb = $i->visit($expr, $b);
            $ta = DefaultFunctions::gettype($va);
            $tb = DefaultFunctions::gettype($vb);
            if ($ta != $tb || ($ta != 'number' && $ta != 'string')) {
                DefaultFunctions::typeError(
                    'sort_by', 1, 'must be strings or numbers of the same type'
                );
            }
            return strnatcmp($va, $vb);
        });

        return $args[0];
    }

    public static function min_by(array $args)
    {
        return self::numberCmpBy($args, '<');
    }

    public static function max_by(array $args)
    {
        return self::numberCmpBy($args, '>');
    }

    private static function numberCmpBy(array $args, $cmp)
    {
        self::validate($args, [['array'], ['expression']], 2, 2);
        $i = $args[1]->interpreter;
        $expr = $args[1]->node;

        $cur = $curValue = null;
        foreach ($args[0] as $value) {
            $result = $i->visit($expr, $value);
            if (self::gettype($result) != 'number') {
                throw new \InvalidArgumentException('Expected a number result');
            }
            if ($cur === null || (
                    ($cmp == '<' && $result < $cur) ||
                    ($cmp == '>' && $result > $cur)
                )
            ) {
                $cur = $result;
                $curValue = $value;
            }
        }

        return $curValue;
    }

    public static function type(array $args)
    {
        self::validateArity(1, 1, $args);
        return self::gettype($args[0]);
    }

    public static function to_string(array $args)
    {
        self::validateArity(1, 1, $args);
        return is_string($args[0]) ? $args[0] : json_encode($args[0]);
    }

    public static function to_number(array $args)
    {
        self::validateArity(1, 1, $args);

        if (!is_numeric($args[0])) {
            return null;
        }

        switch (self::gettype($args[0])) {
            case 'number':
                return $args[0];
            case 'string':
                return strpos($args[0], '.')
                    ? (float) $args[0] : (int) $args[0];
            default:
                return null;
        }
    }

    public static function values(array $args)
    {
        self::validate($args, array(array('array', 'object')), 1, 1);

        return array_values($args[0]);
    }

    public static function slice(array $args)
    {
        try {
            self::validate($args, array(
                0 => array('array', 'string'),
                1 => array('number', 'null'),
                2 => array('number', 'null'),
                3 => array('number', 'null')
            ), 4, 4);
        } catch (\Exception $e) {
            return null;
        }

        return self::sliceIndices($args[0], $args[1], $args[2], $args[3]);
    }

    private static function adjustEndpoint($length, $endpoint, $step)
    {
        if ($endpoint < 0) {
            $endpoint += $length;
            if ($endpoint < 0) {
                $endpoint = 0;
            }
        } elseif ($endpoint >= $length) {
            $endpoint = $step < 0 ? $length - 1 : $length;
        }

        return $endpoint;
    }

    private static function adjustSlice($length, $start, $stop, $step)
    {
        if ($step === null) {
            $step = 1;
        } elseif ($step === 0) {
            throw new \RuntimeException('step cannot be 0');
        }

        if ($start === null) {
            $start = $step < 0 ? $length - 1 : 0;
        } else {
            $start = self::adjustEndpoint($length, $start, $step);
        }

        if ($stop === null) {
            $stop = $step < 0 ? -1 : $length;
        } else {
            $stop = self::adjustEndpoint($length, $stop, $step);
        }

        return array($start, $stop, $step);
    }

    private static function sliceIndices($subject, $start, $stop, $step)
    {
        $type = gettype($subject);
        list($start, $stop, $step) = self::adjustSlice(
            $type == 'string' ? strlen($subject) : count($subject),
            $start,
            $stop,
            $step
        );

        $result = array();
        if ($step > 0) {
            for ($i = $start; $i < $stop; $i += $step) {
                $result[] = $subject[$i];
            }
        } else {
            for ($i = $start; $i > $stop; $i += $step) {
                $result[] = $subject[$i];
            }
        }

        return $type == 'string' ? implode($result, '') : $result;
    }

    /**
     * Converts a PHP type to a JSON data type
     *
     * @param mixed $arg PHP variable
     * @return string Returns the JSON data type
     */
    private static function gettype($arg)
    {
        static $map = array(
            'boolean' => 'boolean',
            'string'  => 'string',
            'NULL'    => 'null',
            'double'  => 'number',
            'integer' => 'number'
        );

        if ($arg instanceof ExprNode) {
            return 'expression';
        }

        $type = gettype($arg);
        if (isset($map[$type])) {
            return $map[gettype($arg)];
        }

        if (!($keys = array_keys($arg))) {
            return 'array';
        } elseif ($keys[0] === 0) {
            return 'array';
        } else {
            return 'object';
        }
    }

    /**
     * Validates the arity of a function against the given arguments
     */
    private static function validateArity($min, $max, array $args)
    {
        $err = null;
        $ct = count($args);
        if ($ct < $min || ($ct > $max && $max != -1)) {
            if ($min == $max) {
                $err = "%s() expects {$min} arguments, {$ct} were provided";
            } elseif ($max == -1) {
                $err = "%s() expects from {$min} to {$max} arguments, {$ct} were provided";
            } else {
                $err = "%s() expects at least {$min} arguments, {$ct} were provided";
            }
        }

        if ($err) {
            $callers = debug_backtrace();
            $fn = $callers[2]['function'];
            throw new \RuntimeException(sprintf($err, $fn));
        }
    }

    private static function typeError($fn, $index, $failure)
    {
        throw new \RuntimeException(sprintf(
            'Argument %d of %s %s',
            $index,
            $fn,
            $failure
        ));
    }

    private static function validate(
        $args,
        $types,
        $minArity = 1,
        $maxArity = 1
    ) {
        self::validateArity($minArity, $maxArity, $args);
        $default = isset($types['...']) ? $types['...'] : null;

        foreach ($args as $index => $value) {
            $tl = isset($types[$index]) ? $types[$index] : $default;
            if (!$tl) {
                continue;
            }
            if (!in_array(self::gettype($value), $tl)) {
                $callers = debug_backtrace();
                self::typeError(
                    $callers[1]['function'],
                    $index,
                    'must be one of the following types: ' . implode(', ', $tl)
                    . ', ' . self::gettype($value) . ' given'
                );
            }
        }

        return true;
    }
}
