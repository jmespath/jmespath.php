<?php
namespace JmesPath;

/**
 * Dispatches to named JMESPath functions using a single function that has the
 * following signature:
 *
 *     mixed $result = fn(string $function_name, array $args)
 */
class FnDispatcher
{
    /**
     * Gets a cached instance of the default function implementations.
     *
     * @return FnDispatcher
     */
    public static function getInstance()
    {
        static $instance = null;
        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Gets the JMESPath type equivalent of a PHP variable.
     *
     * @param mixed $arg PHP variable
     * @return string Returns the JSON data type
     */
    public static function type($arg)
    {
        static $map = [
            'boolean' => 'boolean',
            'string'  => 'string',
            'NULL'    => 'null',
            'double'  => 'number',
            'integer' => 'number',
            'object'  => 'object'
        ];

        if (is_callable($arg)) {
            return 'expression';
        }

        $type = gettype($arg);
        if (isset($map[$type])) {
            return $map[$type];
        }

        return !$arg || array_keys($arg)[0] === 0 ? 'array' : 'object';
    }

    /**
     * @param string $fn   Function name.
     * @param array  $args Function arguments.
     *
     * @return mixed
     */
    public function __invoke($fn, array $args)
    {
        return $this->{'fn_' . $fn}($args);
    }

    private function fn_abs(array $args)
    {
        $this->validate('abs', $args, [['number']]);
        return abs($args[0]);
    }

    private function fn_avg(array $args)
    {
        $this->validate('avg', $args, [['array']]);
        $sum = $this->reduce('avg:0', $args[0], ['number'], function ($a, $b) {
            return $a + $b;
        });
        return $args[0] ? ($sum / count($args[0])) : null;
    }

    private function fn_ceil(array $args)
    {
        $this->validate('ceil', $args, [['number']]);
        return ceil($args[0]);
    }

    private function fn_contains(array $args)
    {
        $this->validate('contains', $args, [['string', 'array'], ['any']]);
        if (is_array($args[0])) {
            return in_array($args[1], $args[0]);
        } elseif (is_string($args[1])) {
            return strpos($args[0], $args[1]) !== false;
        } else {
            return null;
        }
    }

    private function fn_ends_with(array $args)
    {
        $this->validate('ends_with', $args, [['string'], ['string']]);
        list($search, $suffix) = $args;
        return $suffix === '' || substr($search, -strlen($suffix)) === $suffix;
    }

    private function fn_floor(array $args)
    {
        $this->validate('floor', $args, [['number']]);
        return floor($args[0]);
    }

    private function fn_not_null(array $args)
    {
        if (!$args) {
            throw new \RuntimeException(
                "not_null() expects 1 or more arguments, 0 were provided"
            );
        }

        return array_reduce($args, function ($carry, $item) {
            return $carry !== null ? $carry : $item;
        });
    }

    private function fn_join(array $args)
    {
        $this->validate('join', $args, [['string'], ['array']]);
        $fn = function ($a, $b, $i) use ($args) {
            return $i ? ($a . $args[0] . $b) : $b;
        };
        return $this->reduce('join:0', $args[1], ['string'], $fn);
    }

    private function fn_keys(array $args)
    {
        $this->validate('keys', $args, [['object']]);
        return array_keys((array) $args[0]);
    }

    private function fn_length(array $args)
    {
        $this->validate('length', $args, [['string', 'array', 'object']]);
        return is_string($args[0]) ? strlen($args[0]) : count((array) $args[0]);
    }

    private function fn_max(array $args)
    {
        $this->validate('max', $args, [['array']]);
        $fn = function ($a, $b) { return $a >= $b ? $a : $b; };
        return $this->reduce('max:0', $args[0], ['number', 'string'], $fn);
    }

    private function fn_max_by(array $args)
    {
        $this->validate('max_by', $args, [['array'], ['expression']]);
        $expr = $this->wrapExpression('max_by:1', $args[1], ['number', 'string']);
        $i = -1;
        return array_reduce($args[0], function ($carry, $item) use ($expr, &$i) {
            return ++$i
                ? ($expr($carry) >= $expr($item) ? $carry : $item)
                : $item;
        });
    }

    private function fn_min(array $args)
    {
        $this->validate('min', $args, [['array']]);
        $fn = function ($a, $b, $i) { return $i && $a <= $b ? $a : $b; };
        return $this->reduce('min:0', $args[0], ['number', 'string'], $fn);
    }

    private function fn_min_by(array $args)
    {
        $this->validate('min_by', $args, [['array'], ['expression']]);
        $expr = $this->wrapExpression('min_by:1', $args[1], ['number', 'string']);
        $i = -1;
        $fn = function ($a, $b) use ($expr, &$i) {
            return ++$i ? ($expr($a) <= $expr($b) ? $a : $b) : $b;
        };
        return $this->reduce('min_by:1', $args[0], ['any'], $fn);
    }

    private function fn_reverse(array $args)
    {
        $this->validate('reverse', $args, [['array', 'string']]);
        if (is_array($args[0])) {
            return array_reverse($args[0]);
        } elseif (is_string($args[0])) {
            return strrev($args[0]);
        } else {
            throw new \RuntimeException('Cannot reverse provided argument');
        }
    }

    private function fn_sum(array $args)
    {
        $this->validate('sum', $args, [['array']]);
        $fn = function ($a, $b) { return $a + $b; };
        return $this->reduce('sum:0', $args[0], ['number'], $fn);
    }

    private function fn_sort(array $args)
    {
        $this->validate('sort', $args, [['array']]);
        $valid = ['string', 'number'];
        return self::stableSort($args[0], function ($a, $b) use ($valid) {
            $this->validateSeq('sort:0', $valid, $a, $b);
            return strnatcmp($a, $b);
        });
    }

    private function fn_sort_by(array $args)
    {
        $this->validate('sort_by', $args, [['array'], ['expression']]);
        $expr = $args[1];
        $valid = ['string', 'number'];
        return self::stableSort(
            $args[0],
            function ($a, $b) use ($expr, $valid) {
                $va = $expr($a);
                $vb = $expr($b);
                $this->validateSeq('sort_by:0', $valid, $va, $vb);
                return strnatcmp($va, $vb);
            }
        );
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
    private function stableSort(array $data, callable $sortFn)
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

    private function fn_starts_with(array $args)
    {
        $this->validate('starts_with', $args, [['string'], ['string']]);
        list($search, $prefix) = $args;
        return $prefix === '' || strpos($search, $prefix) === 0;
    }

    private function fn_type(array $args)
    {
        $this->validateArity('type', count($args), 1);
        return self::type($args[0]);
    }

    private function fn_to_string(array $args)
    {
        $this->validateArity('to_string', count($args), 1);
        return is_string($args[0]) ? $args[0] : json_encode($args[0]);
    }

    private function fn_to_number(array $args)
    {
        $this->validateArity('to_number', count($args), 1);
        $value = $args[0];
        $type = self::type($value);
        if ($type == 'number') {
            return $value;
        } elseif ($type == 'string' && is_numeric($value)) {
            return strpos($value, '.') ? (float) $value : (int) $value;
        } else {
            return null;
        }
    }

    private function fn_values(array $args)
    {
        $this->validate('values', $args, [['array', 'object']]);
        return array_values((array) $args[0]);
    }

    private function fn_slice(array $args)
    {
        try {
            $this->validate('slice', $args, [
                ['array', 'string'],
                ['number', 'null'],
                ['number', 'null'],
                ['number', 'null']
            ]);
        } catch (\Exception $e) {
            return null;
        }

        return $this->sliceIndices($args[0], $args[1], $args[2], $args[3]);
    }

    private function adjustEndpoint($length, $endpoint, $step)
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

    private function adjustSlice($length, $start, $stop, $step)
    {
        if ($step === null) {
            $step = 1;
        } elseif ($step === 0) {
            throw new \RuntimeException('step cannot be 0');
        }

        if ($start === null) {
            $start = $step < 0 ? $length - 1 : 0;
        } else {
            $start = $this->adjustEndpoint($length, $start, $step);
        }

        if ($stop === null) {
            $stop = $step < 0 ? -1 : $length;
        } else {
            $stop = $this->adjustEndpoint($length, $stop, $step);
        }

        return [$start, $stop, $step];
    }

    private function sliceIndices($subject, $start, $stop, $step)
    {
        $type = gettype($subject);
        $len = $type == 'string' ? strlen($subject) : count($subject);
        list($start, $stop, $step) = $this->adjustSlice($len, $start, $stop, $step);

        $result = [];
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

    private function typeError($from, $msg)
    {
        if (strpos($from, ':')) {
            list($fn, $pos) = explode(':', $from);
            throw new \RuntimeException(
                sprintf('Argument %d of %s %s', $pos, $fn, $msg)
            );
        } else {
            throw new \RuntimeException(
                sprintf('Type error: %s %s', $from, $msg)
            );
        }
    }

    private function validateArity($from, $given, $expected)
    {
        if ($given != $expected) {
            $err = "%s() expects {$expected} arguments, {$given} were provided";
            throw new \RuntimeException(sprintf($err, $from));
        }
    }

    private function validate($from, $args, $types = [])
    {
        $this->validateArity($from, count($args), count($types));
        foreach ($args as $index => $value) {
            if (!isset($types[$index]) || !$types[$index]) {
                continue;
            }
            $this->validateType("{$from}:{$index}", $value, $types[$index]);
        }
        return true;
    }

    private function validateType($from, $value, array $types)
    {
        if ($types[0] == 'any'
            || in_array(self::type($value), $types)
            || ($value === [] && in_array('object', $types))
        ) {
            return;
        }
        $msg = 'must be one of the following types: ' . implode(', ', $types)
            . '. ' . self::type($value) . ' found';
        $this->typeError($from, $msg);
    }

    /**
     * Validates value A and B, ensures they both are correctly typed, and of
     * the same type.
     *
     * @param string   $from   String of function:argument_position
     * @param array    $types  Array of valid value types.
     * @param mixed    $a      Value A
     * @param mixed    $b      Value B
     */
    private function validateSeq($from, array $types, $a, $b)
    {
        $ta = self::type($a);
        $tb = self::type($b);

        if ($ta != $tb) {
            $msg = "encountered a type mismatch in sequence: {$ta}, {$tb}";
            $this->typeError($from, $msg);
        }

        $typeMatch = ($types && $types[0] == 'any')
            || in_array($ta, $types)
            || in_array($tb, $types);

        if (!$typeMatch) {
            $msg = 'encountered a type error in sequence. The argument must be '
                . 'an array of ' . implode('|', $types) . ' types. '
                . "Found {$ta}, {$tb}.";
            $this->typeError($from, $msg);
        }
    }

    /**
     * Reduces and validates an array of values to a single value using a fn.
     *
     * @param string   $from   String of function:argument_position
     * @param array    $values Values to reduce.
     * @param array    $types  Array of valid value types.
     * @param callable $reduce Reduce function that accepts ($carry, $item).
     *
     * @return mixed
     */
    private function reduce($from, array $values, array $types, callable $reduce)
    {
        $i = -1;
        return array_reduce(
            $values,
            function ($carry, $item) use ($from, $types, $reduce, &$i) {
                if (++$i > 0) {
                    $this->validateSeq($from, $types, $carry, $item);
                }
                return $reduce($carry, $item, $i);
            }
        );
    }

    /**
     * Validates the return values of expressions as they are applied.
     *
     * @param string   $from  Function name : position
     * @param callable $expr  Expression function to validate.
     * @param array    $types Array of acceptable return type values.
     *
     * @return callable Returns a wrapped function
     */
    private function wrapExpression($from, callable $expr, array $types)
    {
        list($fn, $pos) = explode(':', $from);
        $from = "The expression return value of argument {$pos} of {$fn}";
        return function ($value) use ($from, $expr, $types) {
            $value = $expr($value);
            $this->validateType($from, $value, $types);
            return $value;
        };
    }

    /** @internal Pass function name validation off to runtime */
    public function __call($name, $args)
    {
        $name = str_replace('fn_', '', $name);
        throw new \RuntimeException("Call to undefined function {$name}");
    }
}