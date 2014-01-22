<?php

namespace JmesPath;

use JmesPath\Tree\TreeCompiler;

/**
 * JMESPath runtime environment that compiles JMESPath expressions to PHP
 * source code
 */
class CompilingRuntime extends AbstractRuntime
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

        return call_user_func($functionName, $this, $data);
    }

    /**
     * Deletes any cached functions
     */
    public function clearCache()
    {
        $files = glob($this->cacheDir . '/jmespath_*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    protected function debugInterpret($expression, array $ast, $data, $out)
    {
        $result = $this->search($expression, $data);
        fprintf($out, "\nSource\n======\n\n%s", file_get_contents(
            $this->cacheDir . '/jmespath_' . md5($expression) . '.php'));

        return $result;
    }
}
