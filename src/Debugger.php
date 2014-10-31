<?php
namespace JmesPath;

/**
 * Provides CLI debugging information for the AST and Compiler runtimes.
 * @internal
 */
class Debugger
{
    public function debugFromEnv($out, $expression, $data)
    {
        if (Env::createRuntime() instanceof CompilerRuntime) {
            $this->debugCompiled($out, $expression, $data);
        } else {
            $this->debugInterpreted($out, $expression, $data);
        }
    }

    public function debugInterpreted($out, $expression, $data)
    {
        $runtime = new AstRuntime();
        $this->debugCallback(function () use ($expression, $data, $runtime) {
            return $runtime($expression, $data);
        }, $out, $expression, $data);
    }

    public function debugCompiled($out, $expression, $data)
    {
        $runtime = new CompilerRuntime();
        $this->debugCallback(function () use ($expression, $data, $runtime) {
            return $runtime($expression, $data);
        }, $out, $expression, $data);
        $this->dumpCompiledCode($out, $expression);
    }

    public function dumpTokens($out, $expression)
    {
        $lexer = new Lexer();
        fwrite($out, "Tokens\n======\n\n");
        $tokens = $lexer->tokenize($expression);

        foreach ($tokens as $t) {
            fprintf(
                $out,
                "%3d  %-13s  %s\n", $t['pos'], $t['type'],
                json_encode($t['value'])
            );
        }

        fwrite($out, "\n");
    }

    public function dumpAst($out, $expression)
    {
        $parser = new Parser();
        $ast = $parser->parse($expression);
        fwrite($out, "AST\n========\n\n");
        fwrite($out, json_encode($ast, JSON_PRETTY_PRINT) . "\n");
    }

    public function dumpCompiledCode($out, $expression)
    {
        $dir = sys_get_temp_dir();
        $hash = md5($expression);
        $functionName = "jmespath_{$hash}";
        $filename = "{$dir}/{$functionName}.php";
        fprintf($out, file_get_contents($filename));
    }

    private function debugCallback(callable $debugFn, $out, $expression, $data)
    {
        fprintf($out, "Expression\n==========\n\n%s\n\n", $expression);
        $this->dumpTokens($out, $expression);
        $this->dumpAst($out, $expression);
        fprintf($out, "\nData\n====\n\n%s\n\n", json_encode($data, JSON_PRETTY_PRINT));
        $startTime = microtime(true);
        $result = $debugFn();
        $total = microtime(true) - $startTime;
        fprintf($out, "\nResult\n======\n\n%s\n\n", json_encode($result, JSON_PRETTY_PRINT));
        fwrite($out, "Time\n====\n\n");
        fprintf($out, "Total time:     %f ms\n\n", $total);

        return $result;
    }
}
