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
     * @throws \InvalidArgumentException if the arguments are invalid
     */
    public function __invoke(array $args)
    {
        $args = $this->validate($args);

        return $args === false ? null : $this->execute($args);
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
     * @return array Returns the validated (and perhaps coerced) arguments
     * @throws \InvalidArgumentException If the arguments are not valid
     */
    protected function validate(array $args)
    {
        $name = get_class($this);

        // Validate the function arity
        if (isset($this->rules['arity']) && count($args) != $this->rules['arity']) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s expects %d arguments but was passed %d',
                    $name,
                    $this->rules['arity'],
                    count($args)
                )
            );
        }

        // Validate arguments
        if (isset($this->rules['args'])) {
            foreach ($this->rules['args'] as $position => $rule) {
                if (false === $this->validateArg($name, $position, $rule, $args)) {
                    return false;
                }
            }
        }

        return $args;
    }

    private function validateArg($fnName, $position, array $rule, array &$args)
    {
        if (isset($rule['type'])) {
            if (gettype($args[$position]) != $rule['type']) {
                if (!isset($rule['failure']) || $rule['failure'] == 'throw') {
                    // Handle failure when it should throw an exception
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Argument %d of %s must be of type %s. Got %s.',
                            $position + 1,
                            $fnName,
                            $rule['type'],
                            gettype($args[$position])
                        )
                    );
                } elseif ($rule['failure'] == 'null') {
                    // Handle failure when set to return null
                    return false;
                }
            }
        }

        return null;
    }
}
