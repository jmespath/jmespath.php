<?php

namespace JmesPath\Fn;

/**
 * Abstract class for built-in JmesPath functions
 */
abstract class AbstractFn
{
    /**
     * Associative array of rules to override in subclasses. Valid keys are:
     *
     * arity: The number of require function arguments
     * args: Positional array of hashes of validation rules for each argument:
     *     type: The PHP primitive type that the argument must be passed in as
     *     failure: How a failed argument is handled. One of "null" or "throw".
     *         Set to "null" to return null, or "throw" (default) to throw.
     *
     * @var array
     */
    protected $rules = array();

    /**
     * Invokes the function
     *
     * @param array $args Array of function arguments
     *
     * @return mixed Returns the function value to add to the TOS
     * @throws \RuntimeException if the arguments are invalid
     */
    public function __invoke(array $args)
    {
        return $this->validate($args) ? $this->execute($args) : null;
    }

    /**
     * This method invokes the function after the arguments have been validated
     *
     * @param array $args Validate function arguments
     *
     * @return mixed Returns the function value to add to the TOS
     */
    abstract protected function execute(array $args);

    /**
     * Validates the function's array of arguments against the validation rules
     * of the function.
     *
     * @param array $args Arguments to verify
     *
     * @return bool Returns true on success or false on failure
     * @throws \RuntimeException If the arguments are not valid
     */
    private function validate(array $args)
    {
        $name = get_class($this);

        // Validate the function arity (number of arguments)
        if (isset($this->rules['arity'])) {
            $this->validateArity($name, $this->rules['arity'][0], $this->rules['arity'][1], $args);
        }

        // Validate arguments
        if (isset($this->rules['args'])) {
            $default = isset($this->rules['args']['default']) ? $this->rules['args']['default'] : null;
            foreach ($args as $k => $arg) {
                $rule = isset($this->rules['args'][$k]) ? $this->rules['args'][$k] : $default;
                if ($rule) {
                    if (false === $this->validateArg($name, $k, $rule, $arg, $this->rules['arity'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function validateArity($fn, $min, $max, array $args)
    {
        $ct = count($args);
        if ($ct < $min || ($ct > $max && $max != -1)) {
            if ($min == $max) {
                throw new \RuntimeException("{$fn} expects {$min} arguments, {$ct} were provided");
            } elseif ($max == -1) {
                throw new \RuntimeException("{$fn} expects from {$min} to {$max} arguments, {$ct} were provided");
            } else {
                throw new \RuntimeException("{$fn} expects at least {$min} arguments, {$ct} were provided");
            }
        }
    }

    /**
     * Returns false if the provided argument does not satisfy the rules and the
     * rules' failure attribute is "null".
     *
     * @throws \RuntimeException if the provided argument is invalid
     */
    private function validateArg($fn, $position, array $rule, $arg)
    {
        if (!isset($rule['type']) || in_array(gettype($arg), $rule['type'])) {
            return null;
        }

        if (!isset($rule['failure']) || $rule['failure'] == 'throw') {
            // Handle failure when it should throw an exception
            throw new \RuntimeException(
                sprintf(
                    'Argument %d of %s must be of type %s. Got %s.',
                    $position + 1,
                    $fn,
                    json_encode($rule['type']),
                    gettype($arg)
                )
            );
        } elseif ($rule['failure'] == 'null') {
            return false;
        } else {
            throw new \InvalidArgumentException('Failure can only be "throw" or "null"');
        }
    }
}
