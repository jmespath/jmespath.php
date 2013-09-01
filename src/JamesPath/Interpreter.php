<?php

namespace JamesPath;

/**
 * Executes JamesPath opcodes
 */
class Interpreter
{
    /** @var array Initial input data */
    private $data;

    /** @var array Opcode to execute */
    private $opcode;

    /** @var array Array of known opcodes */
    private $methods;

    /** @var array Buffer of opcode arguments */
    private $buffer;

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
        $this->data = $data;
        $this->breakpoint = null;
        $this->buffer = [];

        return $this->descend($data);
    }

    private function descend($state)
    {
        while ($this->iterator->valid()) {
            $op = $this->iterator->current();
            switch ($op[0]) {
                case 'push':
                    $this->buffer[] = $op[1];
                    break;
                case 'op':
                    // Break if a breakpoint has been set
                    if ($this->breakpoint && $op[1] == $this->breakpoint) {
                        $this->iterator->seek($this->iterator->key() - 1);
                        return $state;
                    }
                    $arg = 'op_' . $op[1];
                    if (!isset($this->methods[$arg])) {
                        throw new \RuntimeException('Unknown method: ' . $arg);
                    }
                    $state = $this->{$arg}($state);
                    break;
                default:
                    throw new \RuntimeException('Unknown opcode: ' . $op[0]);
            }
            $this->iterator->next();
        }

        return $state;
    }

    private function op_field($state)
    {
        $arg = array_pop($this->buffer);

        if (!is_array($state)) {
            return null;
        } elseif (isset($state[$arg])) {
            return $state[$arg];
        } else {
            return null;
        }
    }

    private function op_index($state)
    {
        $arg = array_pop($this->buffer);

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

    private function op_star($state)
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

    private function op_or($state)
    {
        // Recursively descend into the or processing from the original data if left EXP is null
        if ($state === null) {
            $this->iterator->next();
            return $this->descend($this->data);
        }

        // The left data is valid, so return it and consume any remaining opcode
        $this->iterator->seek($this->iterator->count() - 1);
        $this->iterator->next();

        return $state;
    }
}
