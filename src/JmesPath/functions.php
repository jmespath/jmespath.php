<?php

namespace JmesPath;

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
        $_SERVER[JMESPATH_SERVER_KEY] = Runtime::createRuntime();
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
        $_SERVER[JMESPATH_SERVER_KEY] = Runtime::createRuntime();
    }

    return $_SERVER[JMESPATH_SERVER_KEY]->debug($expression, $data, $out);
}
