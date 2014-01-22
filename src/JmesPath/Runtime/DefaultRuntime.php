<?php

namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Tree\TreeVisitorInterface;

/**
 * Default JMESPath runtime environment that uses an external tree visitor to
 * interpret an AST.
 */
class DefaultRuntime extends AbstractRuntime
{
    /** @var TreeVisitorInterface */
    private $interpreter;

    /** @var array */
    private $visitorOptions;

    /** @var array Internal AST cache */
    private $cache = array();

    /** @var int Number of cached entries */
    private $cachedCount = 0;

    /**
     * @param Parser               $parser      Parser used to parse expressions
     * @param TreeVisitorInterface $interpreter Tree visitor used to interpret the AST
     */
    public function __construct(
        Parser $parser,
        TreeVisitorInterface $interpreter
    ) {
        $this->parser = $parser;
        $this->interpreter = $interpreter;
        $this->visitorOptions = array('runtime' => $this);
    }

    public function search($expression, $data)
    {
        if (!isset($this->cache[$expression])) {
            // Clear the AST cache when it hits 1024 entries
            if (++$this->cachedCount > 1024) {
                $this->cache = array();
                $this->cachedCount = 0;
            }
            $this->cache[$expression] = $this->parser->parse($expression);
        }

        return $this->interpreter->visit(
            $this->cache[$expression],
            $data,
            $this->visitorOptions
        );
    }

    public function debug($expression, $data, $out = STDOUT)
    {
        fprintf($out, "Expression\n==========\n\n%s\n\n", $expression);
        list($tokens, $lexTime) = $this->printDebugTokens($out, $expression);
        list($ast, $parseTime) = $this->printDebugAst($out, $expression);
        fprintf($out, "\nData\n====\n\n%s\n\n", $this->prettyJson($data));

        $t = microtime(true);
        $result = $this->interpreter->visit($ast, $data, $this->visitorOptions);
        $interpretTime = (microtime(true) - $t) * 1000;

        fprintf($out, "\nResult\n======\n\n%s\n\n", $this->prettyJson($result));
        fwrite($out, "Time\n====\n\n");
        fprintf($out, "Lexer time:     %f ms\n", $lexTime);
        fprintf($out, "Parse time:     %f ms\n", $parseTime);
        fprintf($out, "Interpret time: %f ms\n", $interpretTime);
        fprintf($out, "Total time:     %f ms\n\n", $parseTime + $interpretTime);

        return $result;
    }
}
