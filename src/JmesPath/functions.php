<?php

namespace JmesPath;

/**
 * Returns data from the input array that matches a given JMESPath expression.
 *
 * This method maintains a cache of 1024 compiled JMESPath expressions. When the
 * cache exceeds 1024, the cache is cleared.
 *
 * @param string $expression JMESPath expression to evaluate
 * @param array  $data       Data to search
 *
 * @return mixed|null Returns the matching data or null
 */
function search($expression, array $data)
{
    static $interpreter, $parser, $cache = [], $cacheSize = 0;

    if (!isset($cache[$expression])) {
        if (!$parser) {
            $parser = new Parser(new Lexer());
        }
        // Reset the cache when it exceeds 1024 entries
        if (++$cacheSize > 1024) {
            $cache = [];
            $cacheSize = 0;
        }
        $cache[$expression] = $parser->compile($expression);
    }

    if (!$interpreter) {
        $interpreter = new Interpreter();
    }

    return $interpreter->execute($cache[$expression], $data);
}
