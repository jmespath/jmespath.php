<?php
namespace JmesPath\Tests;

use JmesPath\CompilerRuntime;
use JmesPath\DebugRuntime;
use PHPUnit\Framework\TestCase;

class DebugRuntimeTest extends TestCase
{
    public function testCompiledDebugOutputUsesCustomCacheDirAndPreservesPercents(): void
    {
        $dir = $this->createTempDir();
        $key = 'x%sy%%z' . bin2hex(random_bytes(12));
        $expr = json_encode($key);
        $out = fopen('php://memory', 'w+');

        try {
            $debug = new DebugRuntime(new CompilerRuntime($dir), $out);
            $this->assertSame('value', $debug($expr, [$key => 'value']));

            $filename = $dir . '/' . CompilerRuntime::functionName($expr) . '.php';
            rewind($out);
            $output = stream_get_contents($out);

            $this->assertStringContainsString("File: {$filename}", $output);
            $this->assertStringContainsString(file_get_contents($filename), $output);
            $this->assertStringContainsString('%s', $output);
            $this->assertStringContainsString('%%', $output);
        } finally {
            if (is_resource($out)) {
                fclose($out);
            }
            $this->removeTempDir($dir);
        }
    }

    private function createTempDir()
    {
        $dir = sys_get_temp_dir() . '/jmespath-debug-' . bin2hex(random_bytes(12));
        mkdir($dir);

        return realpath($dir);
    }

    private function removeTempDir($dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
