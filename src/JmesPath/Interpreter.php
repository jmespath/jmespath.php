<?php

namespace JmesPath;

/**
 * JMESPath "bytecode" interpreter
 */
class Interpreter
{
    /** @var array Array of known opcodes */
    private $methods;

    /** @var array */
    private $stack;

    /** @var \ArrayIterator */
    private $i;

    /** @var array */
    private $eaches;

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
        $this->i = new \ArrayIterator($opcodes);
        $this->stack = [&$data, $data];
        $this->eaches = [];

        while ($this->i->valid()) {
            $op = $this->i->current();
            $arg = 'op_' . $op[0];

            if ($this->debug) {
                echo $arg . ': ' . (isset($op[1]) ? json_encode($op[1]) : null) . "\n";
                foreach ($this->stack as $index => $stack) {
                    echo $index . ': ' . json_encode($stack) . "\n";
                }
                echo "\n\n===========\n\n";
            }

            if (!isset($this->methods[$arg])) {
                throw new \RuntimeException('Unknown opcode {$op}');
            }
            $this->{$arg}(isset($op[1]) ? $op[1] : null);
            $this->i->next();
        }

        return array_pop($this->stack);
    }

    /**
     * Push a variable into the VM state operand stack
     *
     * @param string $arg Value to push
     */
    public function op_push($arg = null)
    {
        $this->stack[] = $arg;
    }

    /**
     * Descends into a specific index of the input data
     *
     * @param mixed $arg
     */
    private function op_index($arg = null)
    {
        $tos = array_pop($this->stack);
        if (!is_array($tos)) {
            $this->stack[] = null;
        } else {
            $arg = $arg < 0 ? count($tos) + $arg : $arg;
            $this->stack[] = isset($tos[$arg]) ? $tos[$arg] : null;
        }
    }

    /**
     * Descends into a specific key
     *
     * @param mixed $arg
     */
    private function op_field($arg = null)
    {
        $tos = array_pop($this->stack);
        $this->stack[] = is_array($tos) && isset($tos[$arg]) ? $tos[$arg] : null;
    }

    /**
     * Duplicates the top item on the stack by reference
     */
    private function op_dup_top()
    {
        $tos = array_pop($this->stack);
        $this->stack[] = $tos;
        $this->stack[] =& $tos;
    }

    /**
     * Pops the top item off of the stack
     */
    private function op_pop()
    {
        array_pop($this->stack);
    }

    /**
     * Swaps the top two items on the stack
     */
    private function op_rot_two()
    {
        $tos = array_pop($this->stack);
        $tos1 = array_pop($this->stack);
        $this->stack[] = $tos;
        $this->stack[] = $tos1;
    }

    /**
     * Lifts second and third stack item one position up, moves top down to
     * position three.
     */
    private function op_rot_three()
    {
        $tos = array_pop($this->stack);
        $tos1 = array_pop($this->stack);
        $tos2 = array_pop($this->stack);
        $this->stack[] = $tos;
        $this->stack[] = $tos2;
        $this->stack[] = $tos1;
    }

    /**
     * Jump to the given bytecode index if TOS is true. Leaves TOS on the stack.
     *
     * @param int $arg Bytecode index to jump to
     */
    private function op_jump_if_true($arg)
    {
        if (end($this->stack)) {
            $this->i->seek($arg);
        } else {
            array_pop($this->stack);
        }
    }

    /**
     * Jumps to the given bytecode index
     *
     * @param int $arg Bytecode index to jump to
     */
    private function op_goto($arg)
    {
        $this->i->seek($arg - 1);
    }

    /**
     * Halts execution
     */
    private function op_stop() {}

    /**
     * Pops two items off of the stack, TOS and TOS1. Then pushes TOS1 back on
     * the stack after setting TOS1[$arg] = TOS. If no operand, $arg, is
     * provided, the TOS is appended to TOS1.
     *
     * @param string $arg Index to set
     */
    private function op_store_key($arg = null)
    {
        $tos = array_pop($this->stack);
        $tos1 = array_pop($this->stack);

        if (!is_array($tos1)) {
            $this->stack[] = [];
        } else {
            if ($arg === null) {
                $tos1[] = $tos;
            } else {
                $tos1[$arg] = $tos;
            }
            $this->stack[] = $tos1;
        }
    }

    /**
     * Iterates over each element in the TOS.
     *
     * VM maintains an indexed array of eaches where each array contains an
     * iterator, position to jump to when complete, and an array of results.
     *
     * 1. Check if an eaches hash exists at the given bytecode index:
     * 1.a. If the array exists, append TOS to the array result if not empty.
     *      Increment the iterator for the eaches array.
     * 1.b. If the array does not exist and TOS is not an array, jump to the
     *      given $arg in the bytecode.
     * 1.c. If the array does not exist and TOS is an array, the VM creates a
     *      new eaches entry, pops the TOS, and pushes the first key of the
     *      array on TOS.
     * 2.a. If the iterator is valid, push the current iterator element onto TOS
     * 2.b. If the iterator is invalid, pop TOS, push the result onto TOS, and
     *      jump to the jump position of the eaches hash.
     *
     * @param int $arg Jump to the given index when finished
     */
    private function op_each($arg)
    {
        $index = $this->i->key();
        $tos = array_pop($this->stack);

        if (isset($this->eaches[$index])) {

            list($iter, $jmp,) = $this->eaches[$index];
            // Add to the results if not empty
            if (!empty($tos)) {
                $this->eaches[$index][2][] = $tos;
            }

            $iter->next();
            if ($iter->valid()) {
                $this->stack[] = $iter->current();
            } else {
                // Push the result onto the stack
                $this->stack[] = $this->eaches[$index][2];
                unset($this->eaches[$index]);
                $this->i->seek($jmp - 1);
            }

        } elseif (is_array($tos)) {
            // It can be iterated so track the iteration at the current position
            $iter = new \ArrayIterator($tos);
            $this->eaches[$index] = [$iter, $arg, []];
            $this->stack[] = $tos;
            $this->stack[] = $iter->current();
        } else {
            // If it can't be iterated, jump right away
            $this->stack[] = $tos;
            $this->i->seek($arg - 1);
        }
    }
}
