<?php
namespace JmesPath\Runtime;

use JmesPath\Parser;
use JmesPath\Tree\TreeInterpreter;
use JmesPath\Tree\TreeVisitorInterface;

/**
 * Default JMESPath runtime environment that uses an external tree visitor to
 * interpret an AST.
 */
class AstRuntime extends AbstractRuntime
{
    /** @var TreeVisitorInterface */
    private $interpreter;
    private $cache = [];
    private $cachedCount = 0;

    public function __construct()
    {
        $this->parser = new Parser();
        $this->interpreter = new TreeInterpreter($this);
    }

    public function search($expression, $data)
    {
        if (!isset($this->cache[$expression])) {
            // Clear the AST cache when it hits 1024 entries
            if (++$this->cachedCount > 1024) {
                $this->clearCache();
            }
            $this->cache[$expression] = $this->parser->parse($expression);
        }

        return $this->interpreter->visit($this->cache[$expression], $data);
    }

    public function debug($expression, $data, $out = STDOUT)
    {
        fprintf($out, "Expression\n==========\n\n%s\n\n", $expression);
        list($_, $lexTime) = $this->printDebugTokens($out, $expression);
        list($ast, $parseTime) = $this->printDebugAst($out, $expression);
        fprintf($out, "\nData\n====\n\n%s\n\n", json_encode($data, JSON_PRETTY_PRINT));

        $t = microtime(true);
        $result = $this->interpreter->visit($ast, $data);
        $interpretTime = (microtime(true) - $t) * 1000;

        fprintf($out, "\nResult\n======\n\n%s\n\n", json_encode($result, JSON_PRETTY_PRINT));
        fwrite($out, "Time\n====\n\n");
        fprintf($out, "Lexer time:     %f ms\n", $lexTime);
        fprintf($out, "Parse time:     %f ms\n", $parseTime);
        fprintf($out, "Interpret time: %f ms\n", $interpretTime);
        fprintf($out, "Total time:     %f ms\n\n", $parseTime + $interpretTime);

        return $result;
    }

    public function clearCache()
    {
        $this->cache = [];
        $this->cachedCount = 0;
    }
}
