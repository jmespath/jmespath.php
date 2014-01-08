<?php

namespace JmesPath;

/**
 * JMESPath "bytecode" interpreter
 */
class Interpreter
{
    /** @var array Array of known opcodes */
    private $methods;

    /** @var resource */
    private $debug;

    /** @var array Map of function names to class names */
    private $fnMap = array();

    /** @var array Map of function names to instantiated function objects */
    private $fn = array();

    /**
     * @param bool|resource $debug Set to a resource as returned by fopen to
     *                             output debug information. Set to true to
     *                             write to STDOUT.
     */
    public function __construct($debug = false)
    {
        $this->fnMap = array(
            'abs' => 'JmesPath\Fn\FnAbs',
            'avg' => 'JmesPath\Fn\FnAvg',
            'ceil' => 'JmesPath\Fn\FnCeil',
            'concat' => 'JmesPath\Fn\FnConcat',
            'contains' => 'JmesPath\Fn\FnContains',
            'floor' => 'JmesPath\Fn\FnFloor',
            'get' => 'JmesPath\Fn\FnGet',
            'join' => 'JmesPath\Fn\FnJoin',
            'keys' => 'JmesPath\Fn\FnKeys',
            'matches' => 'JmesPath\Fn\FnMatches',
            'max' => 'JmesPath\Fn\FnMax',
            'min' => 'JmesPath\Fn\FnMin',
            'length' => 'JmesPath\Fn\FnLength',
            'lowercase' => 'JmesPath\Fn\FnLowercase',
            'reverse' => 'JmesPath\Fn\FnReverse',
            'sort' => 'JmesPath\Fn\FnSort',
            'sort_by' => 'JmesPath\Fn\FnSortBy',
            'substring' => 'JmesPath\Fn\FnSubstring',
            'type' => 'JmesPath\Fn\FnType',
            'union' => 'JmesPath\Fn\FnUnion',
            'uppercase' => 'JmesPath\Fn\FnUppercase',
            'values' => 'JmesPath\Fn\FnValues',
            '_array_slice' => 'JmesPath\Fn\FnArraySlice'
        );

        $this->debug = $debug === true ? STDOUT : $debug;
        $this->methods = array_fill_keys(get_class_methods($this), true);
    }

    /**
     * Register a custom function with the interpreter.
     *
     * A function must be callable, receives an array of arguments, and returns
     * a function return value.
     *
     * @param string   $name Name of the function
     * @param callable $fn   Function
     *
     * @throws \InvalidArgumentException
     */
    public function registerFunction($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        }

        $this->fn[$name] = $fn;
    }

    /**
     * Get the opcode execution result from an array of input data
     *
     * @param array $opcodes Array of opcodes
     * @param array $data    Input array to process
     *
     * @return mixed|array|null
     * @throws \RuntimeException if an opcode instruction cannot be found
     */
    public function execute(array $opcodes, array $data)
    {
        $opPos = 0;
        $opTotal = count($opcodes);
        $stack = $eaches = array();
        $frames = array(&$data);

        while ($opPos < $opTotal) {

            $this->debug && $this->debugLine($opPos, $stack, $frames, $opcodes[$opPos]);
            $arg = isset($opcodes[$opPos][1]) ? $opcodes[$opPos][1] : null;
            $arg2 = isset($opcodes[$opPos][2]) ? $opcodes[$opPos][2] : null;

            switch ($opcodes[$opPos][0]) {

                case 'field':
                    // Descends into a specific field at the given operand into
                    // the TOS then pushes the result onto the stack. If the
                    // field does not exist, null is pushed onto TOS.
                    $tos = array_pop($stack);
                    $stack[] = is_array($tos) && isset($tos[$arg]) ? $tos[$arg] : null;
                    break;

                case 'stop':
                    // Halts execution
                    break 2;

                case 'push':
                    // Pushes the given operand onto TOS
                    $stack[] = $arg;
                    break;

                case 'pop':
                    // Pops and discards TOS
                    array_pop($stack);
                    break;

                case 'index':
                    // Descends into a specific index at the given operand into
                    // the TOS then pushes the result onto the stack. If the
                    // index does not exist, null is pushed onto TOS. This
                    // opcode also resolves negative indices.
                    $tos = array_pop($stack);
                    if (!is_array($tos)) {
                        $stack[] = null;
                    } else {
                        $arg = $arg < 0 ? count($tos) + $arg : $arg;
                        $stack[] = isset($tos[$arg]) ? $tos[$arg] : null;
                    }
                    break;

                case 'jump':
                    // Jumps to the bytecode index using the given operand
                    $opPos = $arg;
                    continue 2;

                case 'jump_if_true':
                    // Pops TOS and jumps to the given bytecode index if true.
                    if (array_pop($stack) === true) {
                        $opPos = $arg;
                        continue 2;
                    }
                    break;

                case 'jump_if_false':
                    // Pops TOS and jumps to the given bytecode index if false.
                    if (array_pop($stack) === false) {
                        $opPos = $arg;
                        continue 2;
                    }
                    break;

                case 'mark_current':
                    // Pushes the TOS onto the current node stack so that any
                    // usage of the @ token will use value at TOS
                    $frames[] = $stack ? $stack[count($stack) - 1] : null;
                    break;

                case 'push_current':
                    // Pushes the top of the current node stack onto the top
                    // of the operand stack.
                    $stack[] = $frames[count($frames) - 1];
                    break;

                case 'pop_current':
                    // Pops the top of the current node stack
                    array_pop($frames);
                    break;

                case 'each':
                    // Pops the TOS and iterates over each element. If TOS is
                    // not an array, null is pushed onto TOS. Is TOS is an
                    // empty array, an empty array is pushed onto TOS.
                    //
                    // If TOS is an iterable array, then the following occurs:
                    // 1. Check if an eaches hash exists at the given index
                    // 1.a. If it exists, append TOS to the array result if not
                    //      empty. Increment the iterator for the eaches array.
                    // 1.b. If it does not exist and TOS is not an array, jump
                    //      to the given operand index the bytecode.
                    // 1.c. If the array does not exist and TOS is an array, the
                    //      VM creates a new eaches entry, pops the TOS, and
                    //      pushes the first key of the array on TOS.
                    // 2.a. If the iterator is valid, push the current iterator
                    //      element onto TOS
                    // 2.b. If the iterator is invalid, pop TOS, push the result
                    //      onto TOS, and jump to the jump position ('jmp').
                    $index = $opPos;
                    $tos = array_pop($stack);

                    if (isset($eaches[$index])) {

                        if ($tos !== null) {
                            $eaches[$index]['result'][] = $tos;
                        }

                        $eaches[$index]['iter']->next();
                        if ($eaches[$index]['iter']->valid()) {
                            $stack[] = $eaches[$index]['iter']->current();
                        } else {
                            // We're done iterating, so collect the results and push on TOS
                            $stack[] = $eaches[$index]['result'];
                            $opPos = $eaches[$index]['jmp'];
                            unset($eaches[$index]);
                            continue 2;
                        }

                    } elseif (!is_array($tos)) {
                        // The TOS cannot be iterated so break from the loop
                        $stack[] = null;
                        $opPos = $arg;
                        continue 2;
                    } elseif (!$tos) {
                        // The array or hash is empty so push an empty list and
                        // skip iteration
                        $stack[] = array();
                        $opPos = $arg;
                        continue 2;
                    } else {
                        $keys = array_keys($tos);
                        if (($keys[0] === 0 && $arg2 == 'object') ||
                            ($keys[0] !== 0 && $arg2 == 'array')
                        ) {
                            $stack[] = null;
                            $opPos = $arg;
                            continue 2;
                        }
                        // It can be iterated so track the iteration at the current position
                        $eaches[$index] = array(
                            'iter'   => new \ArrayIterator($tos),
                            'jmp'    => $arg,
                            'result' => array()
                        );
                        $stack[] = reset($tos);
                    }
                    break;

                case 'merge':
                    // Pops TOS. If TOS is an array that contains nested arrays,
                    // the nested arrays are merged into TOS. Anything that is
                    // not a nested array (i.e., hash or scalar) is appended to
                    // the end of TOS. The resulting array is added to the TOS.
                    static $skipElement = array();
                    $tos = array_pop($stack);
                    $result = array();
                    if ($tos && is_array($tos)) {
                        foreach ($tos as $values) {
                            // Only merge up arrays lists and not hashes
                            if (is_array($values) && isset($values[0])) {
                                $result = array_merge($result, $values);
                            } elseif ($values != $skipElement) {
                                $result[] = $values;
                            }
                        }
                    }
                    $stack[] = $result;
                    break;

                case 'store_key':
                    // Pops two items off of the stack, TOS and TOS1. Then
                    // pushes TOS1 back onto the stack after setting
                    // TOS1[$arg] = TOS. If no operand, $arg, is provided, the
                    // TOS is appended to TOS1 using an incremental index.
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);

                    if (!is_array($tos1)) {
                        throw new \RuntimeException('Invalid stack for store_key');
                    } else {
                        if ($arg === null) {
                            $tos1[] = $tos;
                        } else {
                            $tos1[$arg] = $tos;
                        }
                        $stack[] = $tos1;
                    }
                    break;

                case 'is_array':
                    // Pushes TRUE or FALSE on to TOS if TOS is an array
                    $stack[] = is_array(end($stack));
                    break;

                case 'is_null':
                    // Pushes TRUE or FALSE on to TOS if TOS is null
                    $stack[] = end($stack) === null;
                    break;

                case 'is_falsey':
                    // Pushes TRUE or FALSE on to TOS if TOS is null or false
                    $tos = end($stack);
                    $stack[] = $tos === null || $tos === false;
                    break;

                case 'eq':
                    // Pops TOS and TOS1 and pushed TOS1 == TOS onto the stack
                    $stack[] = array_pop($stack) === array_pop($stack);
                    break;

                case 'not':
                    // Pops TOS and TOS1 and pushed TOS != TOS1 onto the stack
                    $stack[] = array_pop($stack) != array_pop($stack);
                    break;

                case 'gt':
                    // Pops TOS and TOS1 and pushed TOS > TOS1 onto the stack
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $stack[] = is_numeric($tos) && is_numeric($tos1) && $tos1 > $tos;
                    break;

                case 'gte':
                    // Pops TOS and TOS1 and pushed TOS >= TOS1 onto the stack
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $stack[] = is_numeric($tos) && is_numeric($tos1) && $tos1 >= $tos;
                    break;

                case 'lt':
                    // Pops TOS and TOS1 and pushed TOS < TOS1 onto the stack
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $stack[] = is_numeric($tos) && is_numeric($tos1) && $tos1 < $tos;
                    break;

                case 'lte':
                    // Pops TOS and TOS1 and pushed TOS <= TOS1 onto the stack
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $stack[] = is_numeric($tos) && is_numeric($tos1) && $tos1 <= $tos;
                    break;

                case 'call':
                    // Invokes a function. First pops arguments off of the stack
                    // then pushes the result of the function onto TOS. This
                    // opcode requires two operands:
                    // 1: Function name as a string
                    // 2: Number of arguments to pop off of the stack
                    $funcArgs = array();
                    for ($i = 0; $i < $arg2; $i++) {
                        array_unshift($funcArgs, array_pop($stack));
                    }
                    $stack[] = $this->callFunction($arg, $funcArgs);
                    break;

                case 'slice':
                    // Returns a slice of an array
                    $stack[] = $this->callFunction('_array_slice', array(
                        array_pop($stack),
                        $arg,
                        $arg2,
                        $opcodes[$opPos][3]
                    ));
                    break;

                default:
                    throw new \RuntimeException("Unknown opcode {$opcodes[$opPos][0]}");
                    break;
            }

            $opPos++;
        }

        return array_pop($stack);
    }

    /**
     * Invokes a named function. If the function has not already been
     * instantiated, the function object is created and cached.
     *
     * @param string $name Name of the function to invoke
     * @param array  $args Function arguments
     * @return mixed Returns the function invocation result
     * @throws \RuntimeException If the function is undefined
     */
    private function callFunction($name, $args)
    {
        if (!isset($this->fn[$name])) {
            if (!isset($this->fnMap[$name])) {
                throw new \RuntimeException("Call to undefined function: {$name}");
            } else {
                $this->fn[$name] = new $this->fnMap[$name];
            }
        }

        return call_user_func($this->fn[$name], $args);
    }

    /**
     * Prints debug information for a single line, including the opcodes & stack
     */
    private function debugLine($key, $stack, $frames, $op)
    {
        if (!is_resource($this->debug)) {
            throw new \InvalidArgumentException('debug must be a resource');
        }

        $line = sprintf('> %-3s %-13s   %14s %14s', $key, $op[0],
            isset($op[1]) ? json_encode($op[1]) : '',
            isset($op[2]) ? json_encode($op[2]) : '');
        fwrite($this->debug, "{$line}\n      Frames: ");
        fwrite($this->debug, implode(' | ', array_map(function ($frame) {
            return substr(json_encode($frame), 0, 100);
        }, array_reverse($frames))));
        fprintf($this->debug, "\n%s\n\n", str_repeat('-', strlen($line)));
        foreach (array_reverse($stack) as $k => $v) {
            fprintf($this->debug, "    %03d  %.500s\n", $k, json_encode($v));
        }
        fprintf($this->debug, "\n\n");
    }
}
