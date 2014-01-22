<?php

namespace JmesPath;

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
        'array_slice' => 'JmesPath\Fn\FnArraySlice'
    );

    /** @var array Map of function names to instantiated function objects */
    private $fn = array();

    public function registerFunction($name, $fn)
    {
        if (!is_callable($fn)) {
            throw new \InvalidArgumentException('Function must be callable');
        } elseif (isset($this->fnMap)) {
            throw new \InvalidArgumentException(
                "Cannot override the built-in function, {$name}");
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

    public function debug($expression, $data, $out = STDOUT)
    {
        $lexer = new Lexer();
        $printJson = function ($json) {
            return defined('JSON_PRETTY_PRINT')
                ? json_encode($json, JSON_PRETTY_PRINT)
                : json_encode($json);
        };

        fprintf($out, "Expression\n==========\n\n%s\n\n", $expression);
        fwrite($out, "Tokens\n======\n\n");
        $tokens = $lexer->tokenize($expression);
        $tokens->next();
        do {
            $t = $tokens->token;
            fprintf($out, "%3d  %-13s  %s\n", $t['pos'], $t['type'], json_encode($t['value']));
            $tokens->next();
        } while ($tokens->token['type'] != Lexer::T_EOF);
        fwrite($out, "\n");

        $t = microtime(true);
        $ast = $this->parser->parse($expression);
        $parseTime = (microtime(true) - $t) * 1000;
        fwrite($out, "AST\n========\n\n");
        fwrite($out, (defined('JSON_PRETTY_PRINT')
                ? json_encode($ast, JSON_PRETTY_PRINT)
                : json_encode($ast)) . "\n");
        fprintf($out, "\nData\n====\n\n%s\n\n", $printJson($data));

        $t = microtime(true);
        $result = $this->debugInterpret($expression, $ast, $data, $out);
        $interpretTime = (microtime(true) - $t) * 1000;
        fprintf($out, "\nResult\n======\n\n%s\n\n", $printJson($result));

        fwrite($out, "Time\n====\n\n");
        fprintf($out, "Parse time:     %f ms\n", $parseTime);
        fprintf($out, "Interpret time: %f ms\n", $interpretTime);
        fprintf($out, "Total time:     %f ms\n\n", $parseTime + $interpretTime);

        return $result;
    }

    /**
     * Interprets the AST and returns the result
     *
     * @param string   $expression Expression being run
     * @param array    $ast        AST to interpret
     * @param mixed    $data       Data to evaluate
     * @param resource $out        Where debug output is written
     *
     * @return mixed Returns the result
     */
    abstract protected function debugInterpret($expression, array $ast, $data, $out);
}
