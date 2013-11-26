<?php

namespace JmesPath\Fn;

/**
 * Abstract class for built-in JmesPath functions
 */
abstract class AbstractFn
{
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
        return $this->execute($this->validate($args));
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
        return $args;
    }
}
