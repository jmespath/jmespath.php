<?php

namespace JmesPath;

use JmesPath\Tree\TreeInterpreter;
use JmesPath\Runtime\RuntimeInterface;
use JmesPath\Runtime\DefaultRuntime;
use JmesPath\Runtime\CompilerRuntime;

define('JMESPATH_SERVER_KEY', 'jmespath');

/**
 * Returns data from the input array that matches a given JMESPath expression.
 *
 * @param string $expression JMESPath expression to evaluate
 * @param array  $data       Data to search
 *
 * @return mixed|null Returns the matching data or null
 */
function search($expression, array $data)
{
    if (!isset($_SERVER[JMESPATH_SERVER_KEY])) {
        $_SERVER[JMESPATH_SERVER_KEY] = createRuntime();
    }

    return $_SERVER[JMESPATH_SERVER_KEY]->search($expression, $data);
}

/**
 * Executes a JMESPath expression while emitting debug information to a resource
 *
 * @param string   $expression JMESPath expression to evaluate
 * @param mixed    $data       JSON like data to search
 * @param resource $out        Resource as returned from fopen to write to
 *
 * @return mixed Returns the expression result
 */
function debugSearch($expression, $data, $out = STDOUT)
{
    if (!isset($_SERVER[JMESPATH_SERVER_KEY])) {
        $_SERVER[JMESPATH_SERVER_KEY] = createRuntime();
    }

    return $_SERVER[JMESPATH_SERVER_KEY]->debug($expression, $data, $out);
}

/**
 * Function used to easily create a customized JMESPath runtime environment.
 *
 * @param array $options Options used to create the runtime
 *  'parser'      => Parser used to parse expressions into an AST
 *  'interpreter' => Tree interpreter used to interpret the AST
 *  'compile'     => If specified, the parsed AST will be compiled
 *                   to PHP code. If set to `true` the compiled PHP code will
 *                   be saved to PHP's temp directory. You can specify the
 *                   directory used to store the cached PHP code by passing
 *                   a string. Note: If this value is set, then any provided
 *                   'interpreter' value will be ignored.
 * @return RuntimeInterface
 * @throws \InvalidArgumentException if the provided compile option is invalid
 */
function createRuntime(array $options = array())
{
    $parser = isset($options['parser'])
        ? $options['parser'] : new Parser(new Lexer());

    if (isset($options['compile']) && $options['compile'] !== false) {
        if ($options['compile'] === true) {
            $options['compile'] = sys_get_temp_dir();
        } elseif (!is_string($options['compile'])) {
            throw new \InvalidArgumentException('compile must be a string or boolean');
        }

        return new CompilerRuntime($parser, $options['compile']);
    }

    return new DefaultRuntime(
        $parser,
        isset($options['interpreter']) ? $options['interpreter'] : new TreeInterpreter()
    );
}
