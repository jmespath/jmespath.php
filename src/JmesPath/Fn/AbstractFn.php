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
        if (isset($this->rules['arity'])) {
            $this->validateArity($name, $this->rules['arity'][0],$this->rules['arity'][1], $args);
        }

        $defaultRule = isset($this->rules['args']['default']) ? $this->rules['args']['default'] : null;

        // Validate arguments
        if (isset($this->rules['args'])) {
            foreach ($args as $position => $arg) {
                $rule = isset($this->rules['args'][$position]) ? $this->rules['args'][$position] : $defaultRule;
                if ($rule) {
                    if (false === $this->validateArg($name, $position, $rule, $arg, $this->rules['arity'])) {
                        return false;
                    }
                }
            }
        }

        return $args;
    }

    /**
     * Asserts that the number of arguments provided satisfies the arity of the
     * function.
     *
     * @throws \InvalidArgumentException
     */
    private function validateArity($fnName, $min, $max, array $args)
    {
        $t = count($args);
        $eqMessage = "{$fnName} expects {$min} arguments, {$t} were provided";
        $rnMessage = "{$fnName} expects from {$min} to {$max} arguments, {$t} were provided";
        $s = false;

        if ($t < $min) {
            if ($max == -1) {
                $s = "{$fnName} expects at least {$min} arguments, {$t} were provided";
            } elseif ($max == $min) {
                $s = $eqMessage;
            } else {
                $s = $rnMessage;
            }
        } elseif ($t > $max && $max != -1) {
            if ($max == $min) {
                $s = $eqMessage;
            } else {
                $s = $rnMessage;
            }
        }

        if ($s) {
            throw new \InvalidArgumentException($s);
        }
    }

    /**
     * Returns false if the provided argument does not satisfy the rules and the
     * rules' failure attribute is "null".
     *
     * @throws \InvalidArgumentException if the provided argument does not
     *   satisfy the rules and the rules' failure attribute is "throw".
     */
    private function validateArg($fnName, $position, array $rule, $arg, array $arity)
    {
        if (!isset($rule['failure'])) {
            $rule['failure'] = 'throw';
        }

        if (isset($rule['type'])) {

            $matches = is_array($rule['type'])
                ? in_array(gettype($arg), $rule['type'])
                : gettype($arg) == $rule['type'];

            if (!$matches) {
                if ($rule['failure'] == 'throw') {
                    // Handle failure when it should throw an exception
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Argument %d of %s must be of type %s. Got %s.',
                            $position + 1,
                            $fnName,
                            $rule['type'],
                            gettype($arg)
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
