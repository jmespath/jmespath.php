<?php

namespace JmesPath\Runtime;

use JmesPath\Tree\TreeCompiler;
use JmesPath\Parser;

/**
 * JMESPath runtime environment that compiles JMESPath expressions to PHP
 * source code
 */
class CompilerRuntime extends AbstractRuntime
{
    /** @var TreeCompiler */
    private $compiler;

    /** @var string */
    private $cacheDir;

    /**
     * @param Parser $parser   Parser used to parse expressions
     * @param string $cacheDir Directory used to store compiled PHP code
     * @throws \RuntimeException if the cache directory cannot be created
     */
    public function __construct(Parser $parser, $cacheDir)
    {
        $this->parser = $parser;
        $this->cacheDir = $cacheDir;
        $this->compiler = new TreeCompiler();

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0600, true)) {
            throw new \RuntimeException("Unable to create cache directory: {$cacheDir}");
        }
    }

    public function search($expression, $data)
    {
        $hash = md5($expression);
        $functionName = "jmespath_{$hash}";

        if (!function_exists($functionName)) {
            $filename = "{$this->cacheDir}/{$functionName}.php";
            if (!file_exists($filename)) {
                $code = $this->compiler->visit(
                    $this->parser->parse($expression),
                    $data,
                    array('function_name' => $functionName)
                );
                if (!file_put_contents($filename, $code)) {
                    throw new \RuntimeException(sprintf(
                        'Unable to write the compiled PHP code to: %s (%s)',
                        $filename,
                        var_export(error_get_last(), true)
                    ));
                }
            }
            require $filename;
        }

        return $functionName($this, $data);
    }

    public function clearCache()
    {
        $files = glob($this->cacheDir . '/jmespath_*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function debug($expression, $data, $out = STDOUT)
    {
        fprintf($out, "Expression\n==========\n\n%s\n\n", $expression);
        list($tokens, $lexTime) = $this->printDebugTokens($out, $expression);
        list($ast, $parseTime) = $this->printDebugAst($out, $expression);

        $t = microtime(true);
        $result = $this->search($expression, $data);
        $interpretTime = (microtime(true) - $t) * 1000;

        fprintf($out, "\nSource\n======\n\n%s", file_get_contents(
            $this->cacheDir . '/jmespath_' . md5($expression) . '.php'));
        fprintf($out, "\nData\n====\n\n%s\n\n", $this->prettyJson($data));
        fprintf($out, "\nResult\n======\n\n%s\n\n", $this->prettyJson($result));
        fwrite($out, "Time\n====\n\n");
        fprintf($out, "Lexer time:     %f ms\n", $lexTime);
        fprintf($out, "Parse time:     %f ms\n", $parseTime);
        fwrite($out, "-----------\n");
        fprintf($out, "Actual time:    %f ms\n\n", $interpretTime);

        return $result;
    }
}
