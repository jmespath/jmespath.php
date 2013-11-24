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

    /**
     * @param bool|resource $debug Set to a resource as returned by fopen to
     *                             output debug information. Set to true to
     *                             write to STDOUT.
     */
    public function __construct($debug = false)
    {
        $this->debug = $debug === true ? STDOUT : $debug;
        $this->methods = array_fill_keys(get_class_methods($this), true);
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
        $stack = array(&$data, &$data);
        $eaches = array();

        if ($this->debug) {
            $this->debugInit($opcodes, $data);
        }

        while ($iter->valid()) {

            $opArray = $iter->current();
            $op = $opArray[0];
            $arg = isset($opArray[1]) ? $opArray[1] : null;

            if ($this->debug) {
                $this->debugLine($iter->key(), $stack, $opArray);
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

                case 'dup_top':
                    // Duplicates the TOS and pushes the duplicate to the TOS
                    $stack[] = end($stack);
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

                case 'rot_two':
                    // Swaps the top two items on the stack
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $stack[] = $tos;
                    $stack[] = $tos1;
                    break;

                case 'rot_three':
                    // Lifts second and third stack item one position up, moves
                    // TOS down to position three.
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $tos2 = array_pop($stack);
                    $stack[] = $tos;
                    $stack[] = $tos2;
                    $stack[] = $tos1;
                    break;

                case 'is_empty':
                    // Pushes TRUE or FALSE on to TOS if TOS is null or an empty
                    // array.
                    $tos = end($stack);
                    $stack[] = $tos === null || $tos === array();
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
                    $stack[] = array_pop($stack) == array_pop($stack);
                    break;

                case 'not':
                    // Pops TOS and TOS1 and pushed TOS != TOS1 onto the stack
                    $stack[] = array_pop($stack) != array_pop($stack);
                    break;

                case 'gt':
                    // Pops TOS and TOS1 and pushed TOS > TOS1 onto the stack
                    $stack[] = array_pop($stack) < array_pop($stack);
                    break;

                case 'gte':
                    // Pops TOS and TOS1 and pushed TOS >= TOS1 onto the stack
                    $stack[] = array_pop($stack) <= array_pop($stack);
                    break;

                case 'lt':
                    // Pops TOS and TOS1 and pushed TOS < TOS1 onto the stack
                    $stack[] = array_pop($stack) > array_pop($stack);
                    break;

                case 'lte':
                    // Pops TOS and TOS1 and pushed TOS <= TOS1 onto the stack
                    $stack[] = array_pop($stack) >= array_pop($stack);
                    break;

                case 'stop':
                    // Halts execution
                    break;

                default:
                    throw new \RuntimeException("Unknown opcode {$op}");
                    break;
            }

            $iter->next();
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
            echo ((isset($code[1])) ? json_encode($code[1]) : '') . "\n";
        }
        echo "\nData\n====\n\n" . $this->prettyJson($data) . "\n\n";
        echo "Execution stack\n===============\n\n";
        fwrite($this->debug, ob_get_clean());
    }

    private function debugLine($key, $stack, $op)
    {
        ob_start();
        $arg = 'op_' . $op[0];
        $opLine = '> ' .    str_pad($key, 3, ' ', STR_PAD_RIGHT) . ' ';
        $opLine .= str_pad($arg, 17, ' ') . '   ';
        $opLine .= str_pad((isset($op[1]) ? json_encode($op[1]) : null), 12, ' ');
        echo $opLine . "\n";
        echo str_repeat('-', strlen($opLine)) . "\n\n";

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
        fwrite($this->debug, ob_get_clean());
    }

    private function prettyJson($json)
    {
        if (defined('JSON_PRETTY_PRINT')) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }

        return json_encode($json);
    }
}
