<?php

namespace JamesPath;

class BytecodeInterpreter
{
    private $data;
    private $bytecode;
    private $methods;
    private $buffer;
    /** @var \ArrayIterator */
    private $iterator;

    public function __construct(array $bytecode)
    {
        $this->bytecode = $bytecode;
        $this->methods = array_flip(get_class_methods($this));
    }

    public function execute($data)
    {
        $this->data = $state = $data;
        $this->buffer = [];
        $this->iterator = new \ArrayIterator($this->bytecode);

        return $this->descend($state);
    }

    private function descend($state)
    {
        while ($this->iterator->valid()) {
            $op = $this->iterator->current();
            if ($op[0] == 'push') {
                $this->push($op[1]);
            } elseif ($op[0] == 'op') {
                $state = $this->op($op[1], $state);
            } else {
                throw new \RuntimeException('Unknown opcode: ' . $op[0]);
            }
            $this->iterator->next();
        }

        return $state;
    }

    private function push($arg)
    {
        $this->buffer[] = $arg;
    }

    private function op($arg, $state)
    {
        $arg = 'op_' . $arg;
        if (isset($this->methods[$arg])) {
            return $this->{$arg}($state);
        } else {
            throw new \RuntimeException('Unknown opcode: ' . $arg);
        }
    }

    private function op_field($state)
    {
        $arg = array_pop($this->buffer);

        if (!is_array($state)) {
            return null;
        }

        if (isset($state[$arg])) {
            $state = $state[$arg];
        } else {
            $state = null;
        }

        return $state;
    }

    private function op_index($state)
    {
        return $this->op_field($state);
    }

    private function op_star($state)
    {
        if (!is_array($state)) {
            return null;
        }

        $key = $this->iterator->key() + 1;
        if ($key == count($this->iterator)) {
            return $state;
        }

        $collected = [];
        foreach ($state as $value) {
            $this->iterator->seek($key);
            if (null !== ($result = $this->descend($value))) {
                $collected[] = $result;
            }
        }

        return $collected ? $collected : null;
    }

    private function op_star_i($state)
    {
        if (!is_array($state)) {
            return null;
        }

        $collected = [];
        $key = $this->iterator->key() + 1;
        foreach ($state as $value) {
            $this->iterator->seek($key);
            if (null !== ($result = $this->descend($value))) {
                $collected[] = $result;
            }
        }

        return $collected;
    }

    private function op_or($state)
    {
        // The OR terminates the interpreter, so consume all tokens
        if (null !== $state) {
            $this->iterator->seek($this->iterator->count());
            $this->iterator->next();
            return $state;
        }

        // Recursively descend into the or processing from the original data
        return $this->descend($this->data);
    }
}
