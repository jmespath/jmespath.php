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
    static $interpreter, $parser, $cache = array(), $cacheSize = 0;

    if (!isset($cache[$expression])) {
        if (!$parser) {
            $parser = new Parser(new Lexer());
        }
        // Reset the cache when it exceeds 1024 entries
        if (++$cacheSize > 1024) {
            $cache = array();
            $cacheSize = 0;
        }
        $cache[$expression] = $parser->compile($expression);
    }

    if (!$interpreter) {
        $interpreter = new Interpreter();
    }

    return $interpreter->execute($cache[$expression], $data);
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
    $lexer = new Lexer();
    $parser = new Parser($lexer);
    $interpreter = new Interpreter($out);
    $printJson = function ($json) {
        return defined('JSON_PRETTY_PRINT') ? json_encode($json, JSON_PRETTY_PRINT) : json_encode($json);
    };

    fprintf($out, "Expression\n==========\n\n%s\n\n", $expression);
    fwrite($out, "Tokens\n======\n\n");
    foreach ($lexer->tokenize($expression) as $t) {
        fprintf($out, "%3d  %-13s  %s\n", $t['pos'], $t['type'], json_encode($t['value']));
    }
    fwrite($out, "\n");

    $t = microtime(true);
    $opcodes = $parser->compile($expression);
    $parseTime = (microtime(true) - $t) * 1000;

    fwrite($out, "Bytecode\n========\n\n");
    foreach ($opcodes as $id => $code) {
        fprintf($out, "%3d  %-13s  %-12s %s\n", $id, $code[0],
            isset($code[1]) ? json_encode($code[1]) : '',
            isset($code[2]) ? json_encode($code[2]) : '');
    }
    fprintf($out, "\nData\n====\n\n%s\n\n", $printJson($data));
    fwrite($out, "Execution stack\n===============\n\n");

    $t = microtime(true);
    $result = $interpreter->execute($opcodes, $data);
    $interpretTime = (microtime(true) - $t) * 1000;

    fprintf($out, "Result\n======\n\n%s\n\n", $printJson($result));
    fwrite($out, "Time\n====\n\n");
    fprintf($out, "Parse time:     %f ms\n", $parseTime);
    fprintf($out, "Interpret time: %f ms\n", $interpretTime);
    fprintf($out, "Total time:     %f ms\n\n", $parseTime + $interpretTime);

    return $result;
}
