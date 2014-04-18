<?php

namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Lexer;

abstract class AbstractRuntime implements RuntimeInterface
{
    /** @var Parser */
    protected $parser;

    /** @var array Map of function names to callables */
    private $fnMap = [
        'abs'       => ['JmesPath\DefaultFunctions', 'abs'],
        'avg'       => ['JmesPath\DefaultFunctions', 'avg'],
        'ceil'      => ['JmesPath\DefaultFunctions', 'ceil'],
        'concat'    => ['JmesPath\DefaultFunctions', 'concat'],
        'contains'  => ['JmesPath\DefaultFunctions', 'contains'],
        'floor'     => ['JmesPath\DefaultFunctions', 'floor'],
        'get'       => ['JmesPath\DefaultFunctions', 'get'],
        'join'      => ['JmesPath\DefaultFunctions', 'join'],
        'keys'      => ['JmesPath\DefaultFunctions', 'keys'],
        'max'       => ['JmesPath\DefaultFunctions', 'max'],
        'min'       => ['JmesPath\DefaultFunctions', 'min'],
        'min_by'    => ['JmesPath\DefaultFunctions', 'min_by'],
        'max_by'    => ['JmesPath\DefaultFunctions', 'max_by'],
        'not_null'  => ['JmesPath\DefaultFunctions', 'not_null'],
        'length'    => ['JmesPath\DefaultFunctions', 'length'],
        'sort'      => ['JmesPath\DefaultFunctions', 'sort'],
        'sort_by'   => ['JmesPath\DefaultFunctions', 'sort_by'],
        'type'      => ['JmesPath\DefaultFunctions', 'type'],
        'union'     => ['JmesPath\DefaultFunctions', 'union'],
        'values'    => ['JmesPath\DefaultFunctions', 'values'],
        'slice'     => ['JmesPath\DefaultFunctions', 'slice'],
        'sum'       => ['JmesPath\DefaultFunctions', 'sum'],
        'to_number' => ['JmesPath\DefaultFunctions', 'to_number'],
        'to_string' => ['JmesPath\DefaultFunctions', 'to_string'],
    ];

    public function registerFunction($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        }

        $this->fnMap[$name] = $fn;
    }

    public function callFunction($name, $args)
    {
        if (!isset($this->fnMap[$name])) {
            throw new \RuntimeException("Call to undefined function: {$name}");
        }

        return $this->fnMap[$name]($args);
    }

    /**
     * Returns a pretty-printed JSON document when using PHP 5.4+
     *
     * @param mixed    $json JSON data to format
     *
     * @return string
     */
    protected function prettyJson($json)
    {
        return defined('JSON_PRETTY_PRINT')
            ? json_encode($json, JSON_PRETTY_PRINT)
            : json_encode($json);
    }

    protected function printDebugTokens($out, $expression)
    {
        $lexer = new Lexer();
        fwrite($out, "Tokens\n======\n\n");
        $t = microtime(true);
        $tokens = $lexer->tokenize($expression);
        $lexTime = (microtime(true) - $t) * 1000;

        $tokens->next();
        do {
            $t = $tokens->token;
            fprintf($out, "%3d  %-13s  %s\n", $t['pos'], $t['type'],
                json_encode($t['value']));
            $tokens->next();
        } while ($tokens->token['type'] != 'eof');
        fwrite($out, "\n");

        return array($tokens, $lexTime);
    }

    protected function printDebugAst($out, $expression)
    {
        $t = microtime(true);
        $ast = $this->parser->parse($expression);
        $parseTime = (microtime(true) - $t) * 1000;

        fwrite($out, "AST\n========\n\n");
        fwrite($out, $this->prettyJson($ast) . "\n");

        return array($ast, $parseTime);
    }
}
