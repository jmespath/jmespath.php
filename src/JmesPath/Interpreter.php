<?php

namespace JmesPath;

/**
 * JMESPath "bytecode" interpreter
 */
class Interpreter
{
    /** @var array Array of known opcodes */
    private $methods;

    /** @var bool */
    private $debug;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
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
        $stack = [&$data, &$data];
        $eaches = [];

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
                    $stack[] = $arg;
                    break;

                case 'pop':
                    array_pop($stack);
                    break;

                case 'dup_top':
                    $tos = array_pop($stack);
                    $stack[] = $tos;
                    $stack[] = $tos;
                    break;

                case 'field':
                    $tos = array_pop($stack);
                    $stack[] = is_array($tos) && isset($tos[$arg]) ? $tos[$arg] : null;
                    break;

                case 'index':
                    $tos = array_pop($stack);
                    if (!is_array($tos)) {
                        $stack[] = null;
                    } else {
                        $arg = $arg < 0 ? count($tos) + $arg : $arg;
                        $stack[] = isset($tos[$arg]) ? $tos[$arg] : null;
                    }
                    break;

                case 'rot_two':
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $stack[] = $tos;
                    $stack[] = $tos1;
                    break;

                case 'rot_three':
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);
                    $tos2 = array_pop($stack);
                    $stack[] = $tos;
                    $stack[] = $tos2;
                    $stack[] = $tos1;
                    break;

                case 'jump_if_true':
                    if (end($stack)) {
                        $iter->seek($arg);
                    }
                    break;

                case 'jump_if_false':
                    $tos = end($stack);
                    if ($tos === null || $tos === []) {
                        $iter->seek($arg);
                    }
                    break;

                case 'goto':
                    $iter->seek($arg);
                    continue 2;

                case 'store_key':
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);

                    if (!is_array($tos1)) {
                        $stack[] = [];
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
                    $tos = array_pop($stack);
                    $result = [];
                    if (is_array($tos)) {
                        foreach ($tos as $values) {
                            if (is_array($values) && array_keys($values)[0] === 0) {
                                $result = array_merge($result, $values);
                            } else {
                                $result[] = $values;
                            }
                        }
                    }
                    $stack[] = $result;
                    break;

                case 'each':
                    $index = $iter->key();
                    $tos = array_pop($stack);

                    if (isset($eaches[$index])) {

                        list($eachIter, $jmp,) = $eaches[$index];
                        // Add to the results if not empty
                        if (!empty($tos)) {
                            $eaches[$index][2][] = $tos;
                        }

                        $eachIter->next();
                        if ($eachIter->valid()) {
                            $stack[] = $eachIter->current();
                        } else {
                            // Push the result onto the stack (or null if no results)
                            $stack[] = $eaches[$index][2] ?: null;
                            unset($eaches[$index]);
                            $iter->seek($jmp - 1);
                        }

                    } elseif (is_array($tos)) {
                        // It can be iterated so track the iteration at the current position
                        $eachIter = new \ArrayIterator($tos);
                        $eaches[$index] = [$eachIter, $arg, []];
                        $stack[] = $eachIter->current();
                    } else {
                        // If it can't be iterated, jump right away
                        $stack[] = $tos;
                        $iter->seek($arg - 1);
                    }
                    break;

                case 'stop': break;

                default:
                    throw new \RuntimeException('Unknown opcode {$op}');
                    break;
            }

            $iter->next();
        }

        return array_pop($stack);
    }

    private function debugInit(array $opcodes, array $data)
    {
        echo "Bytecode\n=========\n\n";
        foreach ($opcodes as $id => $code) {
            echo str_pad($id, 3, ' ', STR_PAD_LEFT) . ': ';
            echo str_pad($code[0], 17, ' ') . '  ';
            echo ((isset($code[1])) ? json_encode($code[1]) : '') . "\n";
        }
        echo "\nData\n====\n\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        echo "Execution stack\n===============\n\n";
    }

    private function debugLine($key, $stack, $op)
    {
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
    }
}
