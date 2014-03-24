<?php

namespace JmesPath;

/**
 * Provides implementations for all of the built-in JMESPath functions
 */
class DefaultFunctions
{
    public static function validate($args, $types, $minArity = 1, $maxArity = 1)
    {
        self::validateArity($minArity, $maxArity, $args);
        $default = isset($types['...']) ? $types['...'] : null;

        foreach ($args as $index => $value) {
            if ($typeList = isset($types[$index]) ? $types[$index] : $default) {
                if (!in_array(self::gettype($value), $typeList)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function abs(array $args)
    {
        if (!self::validate($args, array(array('number')))) {
            return null;
        }

        return abs($args[0]);
    }

    public static function avg(array $args)
    {
        if (!self::validate($args, array(array('array'))) ||
            !isset($args[0][0])
        ) {
            return null;
        }

        $sum = $total = 0;
        foreach ($args[0] as $v) {
            $type = self::gettype($v);
            if ($type == 'number') {
                $total++;
                $sum += $v;
            }
        }

        return $total ? $sum / $total : null;
    }

    public static function ceil(array $args)
    {
        if (!self::validate($args, array(array('number')))) {
            return null;
        }

        return ceil($args[0]);
    }

    public static function contains(array $args)
    {
        if (!self::validate($args, array(
            0 => array('string', 'array')
        ), 2, 2)) {
            return null;
        }

        if (is_array($args[0])) {
            return in_array($args[1], $args[0]);
        } elseif (is_string($args[1])) {
            return strpos($args[0], $args[1]) !== false;
        }

        return null;
    }

    public static function floor(array $args)
    {
        if (!self::validate($args, array(array('number')))) {
            return null;
        }

        return floor($args[0]);
    }

    public static function not_null(array $args)
    {
        if (!self::validate($args, array(), 1, -1)) {
            return null;
        }

        foreach ($args as $arg) {
            if ($arg !== null) {
                return $arg;
            }
        }

        return null;
    }

    public static function join(array $args)
    {
        if (!self::validate($args, array(
            0 => array('string'),
            '...' => array('array')
        ), 2, -1)) {
            return null;
        }

        return implode(array_shift($args), array_filter($args[0], function ($arg) {
            return is_string($arg) || is_numeric($arg);
        }));
    }

    public static function keys(array $args)
    {
        if (!self::validate($args, array(array('array', 'object')), 1, 1)) {
            return null;
        }

        if (!$args[0]) {
            return array();
        } elseif (isset($args[0][0])) {
            return null;
        } else {
            return array_keys($args[0]);
        }
    }

    public static function length(array $args)
    {
        if (!self::validate($args, array(array('string', 'array', 'object')))) {
            return null;
        }

        return is_array($args[0]) ? count($args[0]) : strlen($args[0]);
    }

    public static function max(array $args)
    {
        if (!self::validate($args, array(array('array')), 1, 1)) {
            return null;
        }

        $currentMax = null;
        foreach ($args[0] as $element) {
            $type = self::gettype($element);
            if (($type == 'number') &&
                ($currentMax === null || $element > $currentMax)
            ) {
                $currentMax = $element;
            }
        }

        return $currentMax;
    }

    public static function min(array $args)
    {
        if (!self::validate($args, array(array('array')), 1, 1)) {
            return null;
        }

        $currentMin = null;
        foreach ($args[0] as $element) {
            $type = self::gettype($element);
            if ($type == 'number' && ($currentMin === null || $element < $currentMin)) {
                $currentMin = $element;
            }
        }

        return $currentMin;
    }

    public static function sort(array $args)
    {
        if (!self::validate($args, array(array('array')), 1, 1) ||
            ($args[0] && !isset($args[0][0]))
        ) {
            return null;
        }

        natsort($args[0]);

        return $args[0];
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
        switch (self::gettype($args[0])) {
            case 'number':
                return $args[0];
            case 'string':
                return strpos($args[0], '.') ? (float) $args[0] : (int) $args[0];
            default:
                return null;
        }
    }

    public static function union(array $args)
    {
        self::validateArity(2, -1, $args);

        $result = array();
        foreach ($args as $arg) {
            if ($arg && is_array($arg)) {
                if (!isset($arg[0])) {
                    $result += $arg;
                }
            }
        }

        return $result ?: null;
    }

    public static function values(array $args)
    {
        if (!self::validate($args, array(array('array', 'object')), 1, 1)) {
            return null;
        }

        return array_values($args[0]);
    }

    public static function slice(array $args)
    {
        if (!self::validate($args, array(
                0 => array('array', 'string'),
                1 => array('number', 'null'),
                2 => array('number', 'null'),
                3 => array('number', 'null')
            ), 4, 4) ||
            ($args && !isset($args[0][0]))
        ) {
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
}
