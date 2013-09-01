<?php

namespace JmesPath;

/**
 * Executes JmesPath opcodes:
 *
 * - push <value>: Pushes a value onto the operand stack
 * - field: Descends into map data using a key
 * - index: Descends into array data using an index
 * - star: Diverges on a node and collects matching subexpressions
 * - or: Returns the current non-null state or the evaluation of further opcodes
 *
 * Each function is passed a state context array that contains:
 * - 'initial_data' => Initial data that was passed in
 * - 'data'         => Current parsed data
 * - 'stack'        => Operand stack
 * - 'iterator'     => Opcode iterator
 * - 'breakpoint'   => Stop recursively executing when this opcode is encountered
 */
class Interpreter
{
    /** @var array Array of known opcodes */
    private $methods;

    public function __construct()
    {
        $this->methods = array_flip(get_class_methods($this));
    }

    /**
     * Get the opcode execution result from an array of input data
     *
     * @param array $opcodes Array of opcodes
     * @param array $data    Input array to process
     *
     * @return mixed
     */
    public function execute(array $opcodes, array $data)
    {
        return $this->descend([
            'initial_data' => $data,
            'data'         => $data,
            'stack'        => [],
            'iterator'     => new \ArrayIterator($opcodes),
            'breakpoint'   => null
        ])['data'];
    }

    /**
     * Descend into the data using remaining opcodes
     *
     * @param array $state VM state
     *
     * @return array
     * @throws \RuntimeException If an invalid opcode is encountered
     */
    private function descend(array $state)
    {
        while ($state['iterator']->valid()) {

            $op = $state['iterator']->current();
            $arg = 'op_' . $op[0];

            if (!isset($this->methods[$arg])) {
                throw new \RuntimeException('Unknown opcode: ' . var_export($op, true));
            }

            // Break if a breakpoint has been set
            if ($op[0] === $state['breakpoint']) {
                $state['iterator']->seek($state['iterator']->key() - 1);
                return $state;
            }

            $state = $this->{$arg}($state, isset($op[1]) ? $op[1] : null);
            $state['iterator']->next();
        }

        return $state;
    }

    /**
     * Push a variable into the VM state operand stack
     *
     * @param array  $state VM state
     * @param string $arg   Value to push
     *
     * @return array
     */
    public function op_push(array $state, $arg = null)
    {
        $state['stack'][] = $arg;

        return $state;
    }

    /**
     * Descends into a specific key of the input data
     *
     * @param array $state VM state
     * @param mixed $arg
     *
     * @return array
     */
    private function op_field(array $state, $arg = null)
    {
        $arg = array_pop($state['stack']);

        if (is_array($state['data']) && isset($state['data'][$arg])) {
            $state['data'] = $state['data'][$arg];
        } else {
            $state['data'] = null;
        }

        return $state;
    }

    /**
     * Descends into a specific index of the input data
     *
     * @param array $state VM state
     * @param mixed $arg
     *
     * @return array
     */
    private function op_index(array $state, $arg = null)
    {
        $arg = array_pop($state['stack']);

        if (!is_array($state['data'])) {
            $state['data'] = null;
        } else {
            $arg = $arg < 0 ? count($state['data']) + $arg : $arg;
            if (isset($state['data'][$arg])) {
                $state['data'] = $state['data'][$arg];
            } else {
                $state['data'] = null;
            }
        }

        return $state;
    }

    /**
     * Descends into each key/index of the input data using the remaining opcodes
     *
     * @param array $state VM state
     * @param mixed $arg
     *
     * @return array
     */
    private function op_star(array $state, $arg = null)
    {
        if (!is_array($state['data'])) {
            $state['data'] = null;
            return $state;
        }

        $key = $state['iterator']->key() + 1;

        // If the star is last in an expression or the next opcode is an or, then it's irrelevant
        if ($key == count($state['iterator']) || $state['iterator'][$key][0] == 'or') {
            $state['data'] = array_values($state['data']);
            return $state;
        }

        // Collect the result of each possibility until an OR opcode is hit
        $collected = [];
        $state['breakpoint'] = 'or';
        foreach ($state['data'] as $value) {
            $state['data'] = $value;
            $state['iterator']->seek($key);
            $result = $this->descend($state);
            if ($result['data'] !== null) {
                $collected[] = $result['data'];
            }
        }

        $state['breakpoint'] = null;
        $state['data'] = $collected ? $collected : null;

        return $state;
    }

    /**
     * Parse an "or" opcode. If the current parsed data is not null, then that
     * is the result. Otherwise, this opcode will attempt to parse the
     * initial_data using the remaining opcodes up to the next or statement.
     *
     * @param array $state
     * @param mixed $arg
     *
     * @return array
     */
    private function op_or(array $state, $arg = null)
    {
        // Recursively descend into the or processing from the original data if left EXP is null
        if ($state === null) {
            $state['iterator']->next();
            $state['data'] = $state['initial_data'];
            return $this->descend($state);
        }

        // The left data is valid, so return it and consume any remaining opcode
        $state['iterator']->seek($state['iterator']->count() - 1);
        $state['iterator']->next();

        return $state;
    }
}
