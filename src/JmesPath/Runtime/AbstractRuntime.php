<?php

namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Lexer;

abstract class AbstractRuntime implements RuntimeInterface
{
    /** @var Parser */
    protected $parser;

    /** @var array Map of function names to class names */
    private $fnMap = array(
        'abs'         => 'JmesPath\Fn\FnAbs',
        'avg'         => 'JmesPath\Fn\FnAvg',
        'ceil'        => 'JmesPath\Fn\FnCeil',
        'concat'      => 'JmesPath\Fn\FnConcat',
        'contains'    => 'JmesPath\Fn\FnContains',
        'floor'       => 'JmesPath\Fn\FnFloor',
        'get'         => 'JmesPath\Fn\FnGet',
        'join'        => 'JmesPath\Fn\FnJoin',
        'keys'        => 'JmesPath\Fn\FnKeys',
        'matches'     => 'JmesPath\Fn\FnMatches',
        'max'         => 'JmesPath\Fn\FnMax',
        'min'         => 'JmesPath\Fn\FnMin',
        'length'      => 'JmesPath\Fn\FnLength',
        'lowercase'   => 'JmesPath\Fn\FnLowercase',
        'reverse'     => 'JmesPath\Fn\FnReverse',
        'sort'        => 'JmesPath\Fn\FnSort',
        'sort_by'     => 'JmesPath\Fn\FnSortBy',
        'substring'   => 'JmesPath\Fn\FnSubstring',
        'type'        => 'JmesPath\Fn\FnType',
        'union'       => 'JmesPath\Fn\FnUnion',
        'uppercase'   => 'JmesPath\Fn\FnUppercase',
        'values'      => 'JmesPath\Fn\FnValues',
        'slice'       => 'JmesPath\Fn\FnSlice'
    );

    /** @var array Map of function names to instantiated function objects */
    private $fn = array();

    public function registerFunction($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        } elseif (isset($this->fnMap[$name])) {
            throw new \InvalidArgumentException(
                "Cannot override the built-in function {$name}");
        }

        $this->fn[$name] = $fn;
    }

    public function callFunction($name, $args)
    {
        if (!isset($this->fn[$name])) {
            if (!isset($this->fnMap[$name])) {
                throw new \RuntimeException("Call to undefined function: {$name}");
            } else {
                $this->fn[$name] = new $this->fnMap[$name];
            }
        }

        return call_user_func($this->fn[$name], $args);
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
