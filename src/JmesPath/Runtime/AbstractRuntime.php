<?php

namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Lexer;

abstract class AbstractRuntime implements RuntimeInterface
{
    /** @var Parser */
    protected $parser;

    /** @var array Map of function names to callables */
    private $fnMap = array(
        'abs'      => array('JmesPath\DefaultFunctions', 'abs'),
        'avg'      => array('JmesPath\DefaultFunctions', 'avg'),
        'ceil'     => array('JmesPath\DefaultFunctions', 'ceil'),
        'concat'   => array('JmesPath\DefaultFunctions', 'concat'),
        'contains' => array('JmesPath\DefaultFunctions', 'contains'),
        'floor'    => array('JmesPath\DefaultFunctions', 'floor'),
        'get'      => array('JmesPath\DefaultFunctions', 'get'),
        'join'     => array('JmesPath\DefaultFunctions', 'join'),
        'keys'     => array('JmesPath\DefaultFunctions', 'keys'),
        'max'      => array('JmesPath\DefaultFunctions', 'max'),
        'min'      => array('JmesPath\DefaultFunctions', 'min'),
        'length'   => array('JmesPath\DefaultFunctions', 'length'),
        'sort'     => array('JmesPath\DefaultFunctions', 'sort'),
        'sort_by'  => array('JmesPath\DefaultFunctions', 'sort_by'),
        'type'     => array('JmesPath\DefaultFunctions', 'type'),
        'union'    => array('JmesPath\DefaultFunctions', 'union'),
        'values'   => array('JmesPath\DefaultFunctions', 'values'),
        'slice'    => array('JmesPath\DefaultFunctions', 'slice'),
    );

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

        return call_user_func($this->fnMap[$name], $args);
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
            fprintf($out, "%3d  %-13s  %s\n", $t['pos'], $t['type'], json_encode($t['value']));
            $tokens->next();
        } while ($tokens->token['type'] != Lexer::T_EOF);
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
