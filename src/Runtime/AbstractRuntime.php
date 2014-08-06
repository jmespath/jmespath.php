<?php
namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Lexer;
use JmesPath\Tree\ExprNode;
use JmesPath\Tree\TreeInterpreter;

abstract class AbstractRuntime implements RuntimeInterface
{
    /** @var Parser */
    protected $parser;

    /** @var array Map of custom function names to callables */
    private $fnMap = [];

    public function registerFunction($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        }

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

    /**
     * Returns a pretty-printed JSON document when using PHP 5.4+
     *
     * @param mixed    $json JSON data to format
     *
     * @return string
     */
    protected function prettyJson($json)
    {
        return defined('JSON_PRETTY_PRINT')
            ? json_encode($json, JSON_PRETTY_PRINT)
            : json_encode($json);
    }

    protected function printDebugTokens($out, $expression)
    {
        $lexer = new Lexer();
        fwrite($out, "Tokens\n======\n\n");
        $t = microtime(true);
        $tokens = $lexer->tokenize($expression);
        $lexTime = (microtime(true) - $t) * 1000;

        foreach ($tokens as $t) {
            fprintf($out, "%3d  %-13s  %s\n", $t['pos'], $t['type'],
                json_encode($t['value']));
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
        fwrite($out, $this->prettyJson($ast) . "\n");

        return [$ast, $parseTime];
    }

    private function fn_abs(array $args)
    {
        $this->validate($args, array(array('number')));

        return abs($args[0]);
    }

    private function fn_avg(array $args)
    {
        $this->validate($args, array(array('array')));

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
        $this->validate($args, array(array('number')));

        return ceil($args[0]);
    }

    private function fn_contains(array $args)
    {
        $this->validate($args, array(
            0 => array('string', 'array')
        ), 2, 2);

        if (is_array($args[0])) {
            return in_array($args[1], $args[0]);
        } elseif (is_string($args[1])) {
            return strpos($args[0], $args[1]) !== false;
        }

        return null;
    }

    private function fn_floor(array $args)
    {
        $this->validate($args, array(array('number')));

        return floor($args[0]);
    }

    private function fn_not_null(array $args)
    {
        $this->validate($args, array(), 1, -1);

        foreach ($args as $arg) {
            if ($arg !== null) {
                return $arg;
            }
        }

        return null;
    }

    private function fn_join(array $args)
    {
        $this->validate($args, array(
            0     => array('string'),
            '...' => array('array')
        ), 2, -1);

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
        $this->validateArity(1, 1, $args);

        if (!TreeInterpreter::isObject($args[0])) {
            $this->typeError('keys', 0, 'must be an object');
        }

        return array_keys((array) $args[0]);
    }

    private function fn_length(array $args)
    {
        $this->validate($args, array(array('string', 'array', 'object')));

        if (is_string($args[0])) {
            return strlen($args[0]);
        } else {
            return count((array) $args[0]);
        }
    }

    private function fn_max(array $args)
    {
        $this->validate($args, array(array('array')), 1, 1);

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
        $this->validate($args, array(array('array')), 1, 1);

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
        $this->validate($args, array(array('array')), 1, 1);

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
        $this->validate($args, [['array']], 1, 1);

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
        $this->validate($args, [['array'], ['expression']], 2, 2);
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
        $this->validate($args, [['array'], ['expression']], 2, 2);
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
        $this->validateArity(1, 1, $args);
        return $this->gettype($args[0]);
    }

    private function fn_to_string(array $args)
    {
        $this->validateArity(1, 1, $args);
        return is_string($args[0]) ? $args[0] : json_encode($args[0]);
    }

    private function fn_to_number(array $args)
    {
        $this->validateArity(1, 1, $args);

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
        $this->validate($args, array(array('array', 'object')), 1, 1);

        return array_values((array) $args[0]);
    }

    private function fn_slice(array $args)
    {
        try {
            $this->validate($args, array(
                0 => array('array', 'string'),
                1 => array('number', 'null'),
                2 => array('number', 'null'),
                3 => array('number', 'null')
            ), 4, 4);
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

        return array($start, $stop, $step);
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
    private function gettype($arg)
    {
        static $map = array(
            'boolean' => 'boolean',
            'string'  => 'string',
            'NULL'    => 'null',
            'double'  => 'number',
            'integer' => 'number',
            'object'  => 'object'
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
    private function validateArity($min, $max, array $args)
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

    private function typeError($fn, $index, $failure)
    {
        throw new \RuntimeException(sprintf(
            'Argument %d of %s %s',
            $index,
            $fn,
            $failure
        ));
    }

    private function validate(
        $args,
        $types,
        $minArity = 1,
        $maxArity = 1
    ) {
        $this->validateArity($minArity, $maxArity, $args);
        $default = isset($types['...']) ? $types['...'] : null;

        foreach ($args as $index => $value) {
            $tl = isset($types[$index]) ? $types[$index] : $default;
            if (!$tl) {
                continue;
            }
            if (!in_array($this->gettype($value), $tl)) {
                $callers = debug_backtrace();
                $this->typeError(
                    $callers[1]['function'],
                    $index,
                    'must be one of the following types: ' . implode(', ', $tl)
                    . ', ' . $this->gettype($value) . ' given'
                );
            }
        }

        return true;
    }
}
