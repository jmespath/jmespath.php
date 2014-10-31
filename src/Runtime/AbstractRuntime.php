<?php
namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Lexer;
use JmesPath\Tree\ExprNode;

abstract class AbstractRuntime implements RuntimeInterface
{
    /** @var Parser */
    protected $parser;

    /** @var array Map of custom function names to callables */
    private $fnMap = [];

    public function registerFunction($name, callable $fn)
    {
        $this->fnMap[$name] = $fn;
    }

    public function callFunction($name, $args)
    {
        if (!isset($this->fnMap[$name])) {
            if (!is_callable([$this, 'fn_' . $name])) {
                throw new \RuntimeException("Call to undefined function $name");
            }
            $this->fnMap[$name] = [$this, 'fn_' . $name];
        }

        return $this->fnMap[$name]($args);
    }

    protected function printDebugTokens($out, $expression)
    {
        $lexer = new Lexer();
        fwrite($out, "Tokens\n======\n\n");
        $t = microtime(true);
        $tokens = $lexer->tokenize($expression);
        $lexTime = (microtime(true) - $t) * 1000;

        foreach ($tokens as $t) {
            fprintf(
                $out,
                "%3d  %-13s  %s\n", $t['pos'], $t['type'],
                json_encode($t['value'])
            );
        }

        fwrite($out, "\n");

        return [$tokens, $lexTime];
    }

    protected function printDebugAst($out, $expression)
    {
        $t = microtime(true);
        $ast = $this->parser->parse($expression);
        $parseTime = (microtime(true) - $t) * 1000;
        fwrite($out, "AST\n========\n\n");
        fwrite($out, json_encode($ast, JSON_PRETTY_PRINT) . "\n");

        return [$ast, $parseTime];
    }

    private function fn_abs(array $args)
    {
        $this->validate($args, [['number']]);

        return abs($args[0]);
    }

    private function fn_avg(array $args)
    {
        $this->validate($args, [['array']]);

        $sum = $total = 0;
        foreach ($args[0] as $v) {
            $type = $this->gettype($v);
            if ($type != 'number') {
                $this->typeError('avg', 0, 'an array of numbers');
            }
            $total++;
            $sum += $v;
        }

        return $total ? $sum / $total : null;
    }

    private function fn_ceil(array $args)
    {
        $this->validate($args, [['number']]);
        return ceil($args[0]);
    }

    private function fn_contains(array $args)
    {
        $this->validate($args, [['string', 'array'], ['any']]);

        if (is_array($args[0])) {
            return in_array($args[1], $args[0]);
        } elseif (is_string($args[1])) {
            return strpos($args[0], $args[1]) !== false;
        }

        return null;
    }

    private function fn_floor(array $args)
    {
        $this->validate($args, [['number']]);
        return floor($args[0]);
    }

    private function fn_not_null(array $args)
    {
        if (!$args) {
            throw new \RuntimeException(
                "not_null() expects 1 or more arguments, 0 were provided"
            );
        }

        foreach ($args as $arg) {
            if ($arg !== null) {
                return $arg;
            }
        }

        return null;
    }

    private function fn_join(array $args)
    {
        $this->validate($args, [['string'], ['array']]);

        $result = '';
        foreach ($args[1] as $ele) {
            if (!is_string($ele)) {
                $this->typeError('join', 1, 'must be an array of strings');
            }
            $result .= $ele . $args[0];
        }

        return rtrim($result, $args[0]);
    }

    private function fn_keys(array $args)
    {
        $this->validate($args, [['object']]);
        return array_keys((array) $args[0]);
    }

    private function fn_length(array $args)
    {
        $this->validate($args, [['string', 'array', 'object']]);

        return is_string($args[0])
            ? strlen($args[0])
            : count((array) $args[0]);
    }

    private function fn_max(array $args)
    {
        $this->validate($args, [['array']]);

        $currentMax = null;
        foreach ($args[0] as $element) {
            $type = $this->gettype($element);
            if ($type !== 'number') {
                $this->typeError('max', 0, 'must be an array of numbers');
            } elseif ($currentMax === null || $element > $currentMax) {
                $currentMax = $element;
            }
        }

        return $currentMax;
    }

    private function fn_min(array $args)
    {
        $this->validate($args, [['array']]);

        $currentMin = null;
        foreach ($args[0] as $element) {
            $type = $this->gettype($element);
            if ($type !== 'number') {
                $this->typeError('min', 0, 'must be an array of numbers');
            } elseif ($currentMin === null || $element < $currentMin) {
                $currentMin = $element;
            }
        }

        return $currentMin;
    }

    private function fn_sum(array $args)
    {
        $this->validate($args, [['array']]);

        $sum = 0;
        foreach ($args[0] as $element) {
            $type = $this->gettype($element);
            if ($type != 'number') {
                $this->typeError('sum', 0, 'must be an array of numbers');
            }
            $sum += $element;
        }

        return $sum;
    }

    private function fn_sort(array $args)
    {
        $this->validate($args, [['array']]);

        return self::stableSort($args[0], function ($a, $b) {
            $at = $this->gettype($a);
            $bt = $this->gettype($b);
            if ($at != $bt || ($at != 'number' && $at != 'string')) {
                $this->typeError('sort', 0, 'must be an array of string or numbers');
            }
            return strnatcmp($a, $b);
        });
    }

    private function fn_sort_by(array $args)
    {
        $this->validate($args, [['array'], ['expression']]);
        $i = $args[1]->interpreter;
        $expr = $args[1]->node;

        return self::stableSort(
            $args[0],
            function ($a, $b) use ($i, $expr) {
                $va = $i->visit($expr, $a);
                $vb = $i->visit($expr, $b);
                $ta = $this->gettype($va);
                $tb = $this->gettype($vb);
                if ($ta != $tb || ($ta != 'number' && $ta != 'string')) {
                    $this->typeError(
                        'sort_by', 1, 'must be strings or numbers of the same type'
                    );
                }
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

    private function fn_min_by(array $args)
    {
        return $this->numberCmpBy($args, '<');
    }

    private function fn_max_by(array $args)
    {
        return $this->numberCmpBy($args, '>');
    }

    private function numberCmpBy(array $args, $cmp)
    {
        $this->validate($args, [['array'], ['expression']]);
        $i = $args[1]->interpreter;
        $expr = $args[1]->node;

        $cur = $curValue = null;
        foreach ($args[0] as $value) {
            $result = $i->visit($expr, $value);
            if ($this->gettype($result) != 'number') {
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

    private function fn_type(array $args)
    {
        $this->validateArity(count($args), 1);
        return $this->gettype($args[0]);
    }

    private function fn_to_string(array $args)
    {
        $this->validateArity(count($args), 1);
        return is_string($args[0]) ? $args[0] : json_encode($args[0]);
    }

    private function fn_to_number(array $args)
    {
        $this->validateArity(count($args), 1);

        if (!is_numeric($args[0])) {
            return null;
        }

        switch ($this->gettype($args[0])) {
            case 'number':
                return $args[0];
            case 'string':
                return strpos($args[0], '.')
                    ? (float) $args[0] : (int) $args[0];
            default:
                return null;
        }
    }

    private function fn_values(array $args)
    {
        $this->validate($args, [['array', 'object']]);
        return array_values((array) $args[0]);
    }

    private function fn_slice(array $args)
    {
        try {
            $this->validate($args, [
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
        list($start, $stop, $step) = $this->adjustSlice(
            $type == 'string' ? strlen($subject) : count($subject),
            $start,
            $stop,
            $step
        );

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

    /**
     * Converts a PHP type to a JSON data type
     *
     * @param mixed $arg PHP variable
     * @return string Returns the JSON data type
     */
    private function gettype($arg)
    {
        static $map = [
            'boolean' => 'boolean',
            'string'  => 'string',
            'NULL'    => 'null',
            'double'  => 'number',
            'integer' => 'number',
            'object'  => 'object'
        ];

        if ($arg instanceof ExprNode) {
            return 'expression';
        }

        $type = gettype($arg);

        if (isset($map[$type])) {
            return $map[gettype($arg)];
        } else {
            $keys = array_keys($arg);
            if (!$keys) {
                return 'array';
            } elseif ($keys[0] === 0) {
                return 'array';
            } else {
                return 'object';
            }
        }
    }

    private function typeError($fn, $i, $msg)
    {
        throw new \RuntimeException(sprintf('Argument %d of %s %s', $i, $fn, $msg));
    }

    private function validateArity($given, $expected)
    {
        if ($given != $expected) {
            $err = "%s() expects {$expected} arguments, {$given} were provided";
            $callers = debug_backtrace();
            $fn = $callers[2]['function'];
            throw new \RuntimeException(sprintf($err, $fn));
        }
    }

    private function validate($args, $types = [])
    {
        $this->validateArity(count($args), count($types));

        foreach ($args as $index => $value) {

            if (!isset($types[$index])) {
                continue;
            }

            if (in_array($this->gettype($value), $types[$index])
                // 'any' is a special match for any type.
                || $types[$index] === ['any']
                // Allow empty arrays to satisfy objects or arrays.
                || in_array('object', $types[$index]) && $value === []
            ) {
                continue;
            }

            $this->typeError(
                debug_backtrace()[1]['function'],
                $index,
                'must be one of the following types: '
                . implode(', ', $types[$index])
                . ', ' . $this->gettype($value) . ' given'
            );
        }

        return true;
    }
}
