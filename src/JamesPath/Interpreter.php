<?php

namespace JamesPath;

/**
 * Executes JamesPath opcodes:
 *
 * push <value>: Pushes a value onto the operand stack
 * field: Descends into map data using a key
 * index: Descends into array data using an index
 * star: Diverges on a node and collects matching subexpressions
 * or: Returns the current non-null state or the evaluation of further opcodes
 */
class Interpreter
{
    /** @var array Initial input data */
    private $initialData;

    /** @var array Opcode to execute */
    private $opcode;

    /** @var array Array of known opcodes */
    private $methods;

    /** @var array Operand stack holding temporary values */
    private $operandStack;

    /** @var \ArrayIterator */
    private $iterator;

    /** @var string Process opcodes until this code */
    private $breakpoint;

    /**
     * @param array $opcode Array of JamesPath opcodes
     */
    public function __construct(array $opcode)
    {
        $this->opcode = $opcode;
        $this->iterator = new \ArrayIterator($this->opcode);
        $this->methods = array_flip(get_class_methods($this));
    }

    /**
     * Get the opcode execution result from an array of input data
     *
     * @param array $data Input array to process
     *
     * @return mixed
     */
    public function execute($data)
    {
        $this->iterator->rewind();
        $this->initialData = $data;
        $this->breakpoint = null;
        $this->operandStack = [];

        return $this->descend($data);
    }

    private function descend($state)
    {
        while ($this->iterator->valid()) {

            $op = $this->iterator->current();
            $arg = 'op_' . $op[0];

            if (!isset($this->methods[$arg])) {
                throw new \RuntimeException('Unknown opcode: ' . var_export($op, true));
            }

            // Break if a breakpoint has been set
            if ($op[0] === $this->breakpoint) {
                $this->iterator->seek($this->iterator->key() - 1);
                return $state;
            }

            $state = $this->{$arg}($state, isset($op[1]) ? $op[1] : null);
            $this->iterator->next();
        }

        return $state;
    }

    public function op_push($state, $arg = null)
    {
        $this->operandStack[] = $arg;

        return $state;
    }

    private function op_field($state, $arg = null)
    {
        $arg = array_pop($this->operandStack);

        if (!is_array($state)) {
            return null;
        } elseif (isset($state[$arg])) {
            return $state[$arg];
        } else {
            return null;
        }
    }

    private function op_index($state, $arg = null)
    {
        $arg = array_pop($this->operandStack);

        if (!is_array($state)) {
            return null;
        }

        $arg = $arg < 0 ? count($state) + $arg : $arg;

        if (isset($state[$arg])) {
            return $state[$arg];
        } else {
            return null;
        }
    }

    private function op_star($state, $arg = null)
    {
        if (!is_array($state)) {
            return null;
        }

        $key = $this->iterator->key() + 1;

        // If the star is last in an expression, then it's irrelevant
        if ($key == count($this->iterator)
            || ($this->iterator[$key][0] == 'op' && $this->iterator[$key][1] == 'or')
        ) {
            return array_values($state);
        }

        // Collect the result of each possibility until an OR opcode is hit
        $collected = [];
        foreach ($state as $value) {
            $this->iterator->seek($key);
            $this->breakpoint = 'or';
            if (null !== ($result = $this->descend($value))) {
                $collected[] = $result;
            }
        }
        $this->breakpoint = null;

        return $collected ? $collected : null;
    }

    private function op_or($state, $arg = null)
    {
        // Recursively descend into the or processing from the original data if left EXP is null
        if ($state === null) {
            $this->iterator->next();
            return $this->descend($this->initialData);
        }

        // The left data is valid, so return it and consume any remaining opcode
        $this->iterator->seek($this->iterator->count() - 1);
        $this->iterator->next();

        return $state;
    }
}
