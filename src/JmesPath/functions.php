<?php

namespace JmesPath;

/**
 * Returns data from the input data that matches a given JmesPath expression
 *
 * @param string $expression JmesPath expression to evaluate
 * @param array  $data       Data to search
 *
 * @return mixed|null Returns the matching data or null
 */
function search($expression, array $data)
{
    static $interpreter;

    if (!$interpreter) {
        $interpreter = new Interpreter();
    }

    return $interpreter->execute(compile($expression), $data);
}

/**
 * Compile a JMESPath expression into opcodes
 *
 * @param string $expression Expression to compile
 *
 * @return array Returns an array of opcodes
 */
function compile($expression)
{
    static $cache, $cacheSize, $parser;

    if (!$cache) {
        $cache = [];
        $cacheSize = 0;
    }

    if (!isset($cache[$expression])) {

        if (!$parser) {
            $parser = new Parser(new Lexer());
        }

        // Reset the cache when it exceeds 1000 entries
        if (++$cacheSize > 1000) {
            $cache = [];
        }

        $cache[$expression] = $parser->compile($expression);
    }

    return $cache[$expression];
}
