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

    /** @var array Array of functions */
    private $fn = array();

    /**
     * @param bool|resource $debug Set to a resource as returned by fopen to
     *                             output debug information. Set to true to
     *                             write to STDOUT.
     */
    public function __construct($debug = false)
    {
        static $defaultFunctions = array(
            'count' => 'JmesPath\Fn\FnCount',
            'matches' => 'JmesPath\Fn\FnMatches',
            'length' => 'JmesPath\Fn\FnLength',
            'substring' => 'JmesPath\Fn\FnSubstring',
        );

        $this->debug = $debug === true ? STDOUT : $debug;
        $this->methods = array_fill_keys(get_class_methods($this), true);

        // Register default functions
        foreach ($defaultFunctions as $name => $className) {
            $this->fn[$name] = new $className;
        }
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
        $iter = new \ArrayIterator($opcodes);
        $stack = $currentStack = array(&$data);
        $eaches = array();

        if ($this->debug) {
            $this->debugInit($opcodes, $data);
        }

        while ($iter->valid()) {

            $opArray = $iter->current();
            $op = $opArray[0];
            $arg = isset($opArray[1]) ? $opArray[1] : null;
            $arg2 = isset($opArray[2]) ? $opArray[2] : null;

            if ($this->debug) {
                $this->debugLine($iter->key(), $stack, $currentStack, $opArray);
            }

            switch ($op) {

                case 'push':
                    // Pushes the given operand onto TOS
                    $stack[] = $arg;
                    break;

                case 'pop':
                    // Pops and discards TOS
                    array_pop($stack);
                    break;

                case 'field':
                    // Descends into a specific field at the given operand into
                    // the TOS then pushes the result onto the stack. If the
                    // field does not exist, null is pushed onto TOS.
                    $tos = array_pop($stack);
                    $stack[] = is_array($tos) && isset($tos[$arg]) ? $tos[$arg] : null;
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

                case 'is_empty':
                    // Pushes TRUE or FALSE on to TOS if TOS is null or an empty
                    // array.
                    $tos = end($stack);
                    $stack[] = $tos === null || $tos === array();
                    break;

                case 'is_falsey':
                    // Pushes TRUE or FALSE on to TOS if TOS is null or false
                    $tos = end($stack);
                    $stack[] = $tos === null || $tos === false;
                    break;

                case 'jump_if_true':
                    // Pops TOS and jumps to the given bytecode index if true.
                    if (array_pop($stack) === true) {
                        $iter->seek($arg);
                        continue 2;
                    }
                    break;

                case 'jump_if_false':
                    // Pops TOS and jumps to the given bytecode index if false.
                    if (array_pop($stack) === false) {
                        $iter->seek($arg);
                        continue 2;
                    }
                    break;

                case 'goto':
                    // Jumps to the bytecode index using the given operand
                    $iter->seek($arg);
                    continue 2;

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

                case 'merge':
                    // Pops TOS. If TOS is an array that contains nested arrays,
                    // the nested arrays are merged into TOS. Anything that is
                    // not a nested array (i.e., hash or scalar) is appended to
                    // the end of TOS. The resulting array is added to the TOS.
                    $tos = array_pop($stack);
                    $result = array();
                    if ($tos && is_array($tos)) {
                        foreach ($tos as $values) {
                            // Only merge up arrays lists and not hashes
                            if (is_array($values) && isset($values[0])) {
                                $result = array_merge($result, $values);
                            } else {
                                $result[] = $values;
                            }
                        }
                    }
                    $stack[] = $result;
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
                    $index = $iter->key();
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
                            $iter->seek($eaches[$index]['jmp']);
                            unset($eaches[$index]);
                            continue 2;
                        }

                    } elseif (!is_array($tos)) {
                        // The TOS cannot be iterated so break from the loop
                        $stack[] = null;
                        $iter->seek($arg);
                        continue 2;
                    } elseif (!$tos) {
                        // The array is empty so push an empty list and skip iteration
                        $stack[] = array();
                        $iter->seek($arg);
                        continue 2;
                    } else {
                        // It can be iterated so track the iteration at the current position
                        $eaches[$index] = array(
                            'iter'   => new \ArrayIterator($tos),
                            'jmp'    => $arg,
                            'result' => array()
                        );
                        $stack[] = reset($tos);
                    }
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

                case 'stop':
                    // Halts execution
                    break;

                case 'mark_current':
                    // Pushes the TOS onto the current node stack so that any
                    // usage of the @ token will use value at TOS
                    $currentStack[] = &$stack[count($stack) - 1];
                    break;

                case 'push_current':
                    // Pushes the top of the current node stack onto the top
                    // of the operand stack.
                    $stack[] = &$currentStack[count($currentStack) - 1];
                    break;

                case 'pop_current':
                    // Pops the top of the current node stack
                    array_pop($currentStack);
                    break;

                case 'call':
                    // Invokes a function. First pops arguments off of the stack
                    // then pushes the result of the function onto TOS. This
                    // opcode requires two operands:
                    // 1: Function name as a string
                    // 2: Number of arguments to pop off of the stack
                    if (!isset($this->fn[$arg])) {
                        throw new \RuntimeException("Unknown function: {$arg}");
                    }

                    // Pop function arguments
                    $funcArgs = array();
                    for ($i = 0; $i < $arg2; $i++) {
                        array_unshift($funcArgs, array_pop($stack));
                    }
                    $stack[] = call_user_func($this->fn[$arg], $funcArgs);

                    break;

                default:
                    throw new \RuntimeException("Unknown opcode {$op}");
                    break;
            }

            $iter->next();
        }

        if ($this->debug) {
            $this->debugFinal($stack, $currentStack);
        }

        return array_pop($stack);
    }

    private function debugInit(array $opcodes, array $data)
    {
        if (!is_resource($this->debug)) {
            throw new \InvalidArgumentException('debug must be a resource');
        }

        ob_start();
        echo "Bytecode\n=========\n\n";
        foreach ($opcodes as $id => $code) {
            echo str_pad($id, 3, ' ', STR_PAD_LEFT) . ': ';
            echo str_pad($code[0], 17, ' ') . '  ';
            echo str_pad(isset($code[1]) ? json_encode($code[1]) : '', 12, ' ');
            echo (isset($code[2]) ? json_encode($code[2]) : '') . "\n";
        }
        echo "\nData\n====\n\n" . $this->prettyJson($data) . "\n\n";
        echo "Execution stack\n===============\n\n";
        fwrite($this->debug, ob_get_clean());
    }

    /**
     * Prints debug information for a single line, including the opcodes & stack
     *
     * @param $key
     * @param $stack
     * @param $currentStack
     * @param $op
     */
    private function debugLine($key, $stack, $currentStack, $op)
    {
        ob_start();
        $arg = 'op_' . $op[0];
        $opLine = '> ' .    str_pad($key, 3, ' ', STR_PAD_RIGHT) . ' ';
        $opLine .= str_pad($arg, 17, ' ') . '   ';
        $opLine .= str_pad((isset($op[1]) ? json_encode($op[1]) : null), 14, ' ');
        $opLine .= str_pad((isset($op[2]) ? json_encode($op[2]) : null), 14, ' ');
        echo $opLine . "\n";
        echo '      Frames: ';
        echo implode(' | ', array_map(function ($frame) { return substr(json_encode($frame), 0, 100); }, array_reverse($currentStack)));
        echo "\n" . str_repeat('-', strlen($opLine)) . "\n\n";
        $this->dumpStack($stack);
        fwrite($this->debug, ob_get_clean());
    }

    /**
     * Prints debug out for the stack and current node scopes IF they are not
     * in the ideal state, indicating extra stuff on the stack or unpopped
     * scopes.
     *
     * @param array $stack
     * @param array $currentStack
     */
    private function debugFinal(array $stack, array $currentStack)
    {
        if (count($stack) > 2 || count($currentStack) > 1) {
            ob_start();
            echo "Final state\n===========\n\nStack: ";
            $this->dumpStack($stack);
            echo 'Current stack: ' . json_encode($currentStack) . "\n\n";
            fwrite($this->debug, ob_get_clean());
        }
    }

    /**
     * Dumps the stack using a modified JSON output
     *
     * @param array $stack
     */
    private function dumpStack(array $stack)
    {
        foreach (array_reverse($stack) as $index => $stack) {
            $json = json_encode($stack);
            echo '    ' . str_pad($index, 3, ' ', STR_PAD_LEFT) . ': ';
            echo substr(json_encode($stack), 0, 500);
            if (strlen($json) > 500) {
                echo ' [...]';
            }
            echo "\n";
        }
        echo "\n\n";
    }

    private function prettyJson($json)
    {
        return defined('JSON_PRETTY_PRINT') ? json_encode($json, JSON_PRETTY_PRINT) : json_encode($json);
    }
}
