<?php

namespace JamesPath;

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
    static $cache, $parser, $interpreter, $cacheSize;

    if (!$cache) {
        $cache = [];
        $cacheSize = 0;
    }

    if (!$interpreter) {
        $interpreter = new Interpreter();
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

    return $interpreter->execute($cache[$expression], $data);
}
