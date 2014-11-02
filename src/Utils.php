<?php
namespace JmesPath;

class Utils
{
    /**
     * Gets the JMESPath type equivalent of a PHP variable.
     *
     * @param mixed $arg PHP variable
     * @return string Returns the JSON data type
     * @throws \InvalidArgumentException when an unknown type is given.
     */
    public static function type($arg)
    {
        static $map = [
            'boolean' => 'boolean',
            'string'  => 'string',
            'NULL'    => 'null',
            'double'  => 'number',
            'float'   => 'number',
            'integer' => 'number'
        ];

        $type = gettype($arg);
        if (isset($map[$type])) {
            return $map[$type];
        }

        if ($type == 'object') {
            if ($arg instanceof \stdClass) {
                return 'object';
            } elseif ($arg instanceof \Closure) {
                return 'expression';
            } elseif ($arg instanceof \ArrayAccess
                && $arg instanceof \Countable
            ) {
                return count($arg) == 0 || $arg->offsetExists(0)
                    ? 'array'
                    : 'object';
            }
            throw new \InvalidArgumentException(
                'Unable to determine JMESPath type from ' . get_class($arg)
            );
        }

        return !$arg || array_keys($arg)[0] === 0 ? 'array' : 'object';
    }

    /**
     * Determine if the provided value is a JMESPath compatible object.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isObject($value)
    {
        if (is_array($value)) {
            return !$value || array_keys($value)[0] !== 0;
        }

        // Handle array-like values. Must be empty or offset 0 does not exist
        return $value instanceof \Countable && $value instanceof \ArrayAccess
            ? count($value) == 0 || !$value->offsetExists(0)
            : $value instanceof \stdClass;
    }

    /**
     * Determine if the provided value is a JMESPath compatible array.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isArray($value)
    {
        if (is_array($value)) {
            return !$value || array_keys($value)[0] === 0;
        }

        // Handle array-like values. Must be empty or offset 0 exists.
        return $value instanceof \Countable && $value instanceof \ArrayAccess
            ? count($value) == 0 || $value->offsetExists(0)
            : false;
    }

    /**
     * JSON aware value comparison function.
     *
     * @param mixed $a First value to compare
     * @param mixed $b Second value to compare
     *
     * @return bool
     */
    public static function valueCmp($a, $b)
    {
        if ($a === $b) {
            return true;
        } elseif ($a instanceof \stdClass) {
            return self::valueCmp((array) $a, $b);
        } elseif ($b instanceof \stdClass) {
            return self::valueCmp($a, (array) $b);
        } else {
            return false;
        }
    }

    /**
     * JMESPath requires a stable sorting algorithm, so here we'll implement
     * a simple Schwartzian transform that uses array index positions as tie
     * breakers.
     *
     * @param array    $data   List or map of data to sort
     * @param callable $sortFn Callable used to sort values
     *
     * @return array Returns the sorted array
     * @link http://en.wikipedia.org/wiki/Schwartzian_transform
     */
    public static function stableSort(array $data, callable $sortFn)
    {
        // Decorate each item by creating an array of [value, index]
        array_walk($data, function (&$v, $k) { $v = [$v, $k]; });
        // Sort by the sort function and use the index as a tie-breaker
        uasort($data, function ($a, $b) use ($sortFn) {
            return $sortFn($a[0], $b[0]) ?: ($a[1] < $b[1] ? -1 : 1);
        });

        // Undecorate each item and return the resulting sorted array
        return array_map(function ($v) { return $v[0]; }, array_values($data));
    }
}