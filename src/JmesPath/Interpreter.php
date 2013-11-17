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
                        continue 2;
                    }
                    break;

                case 'jump_if_false':
                    $tos = end($stack);
                    if ($tos === null || $tos === array()) {
                        $iter->seek($arg);
                        continue 2;
                    }
                    break;

                case 'goto':
                    $iter->seek($arg);
                    continue 2;

                case 'store_key':
                    $tos = array_pop($stack);
                    $tos1 = array_pop($stack);

                    if (!is_array($tos1)) {
                        $stack[] = array();
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

                case 'push_root':
                    $stack[] =& $data;
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
