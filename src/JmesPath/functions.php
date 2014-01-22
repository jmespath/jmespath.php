<?php

namespace JmesPath;

use JmesPath\Tree\TreeInterpreter;
use JmesPath\Runtime\RuntimeInterface;
use JmesPath\Runtime\DefaultRuntime;
use JmesPath\Runtime\CompilingRuntime;

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
 *  'parser'            => Parser used to parse expressions into an AST
 *  'interpreter'       => Tree interpreter used to interpret the AST
 *  'cache_dir'         => If specified, the parsed AST will be compiled
 *                         to PHP code and saved to the given directory.
 *                         Specifying this option will meant that a provided
 *                         'interpreter' will be ignored.
 * @return RuntimeInterface
 */
function createRuntime(array $options = array())
{
    $parser = isset($options['parser'])
        ? $options['parser'] : new Parser(new Lexer());

    if (isset($options['cache_dir'])) {
        return new CompilingRuntime($parser, $options['cache_dir']);
    }

    return new DefaultRuntime(
        $parser,
        isset($options['interpreter']) ? $options['interpreter'] : new TreeInterpreter()
    );
}
