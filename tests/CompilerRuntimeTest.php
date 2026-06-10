<?php
namespace JmesPath\Tests;

use JmesPath\AstRuntime;
use JmesPath\CompilerRuntime;
use PHPUnit\Framework\TestCase;

class CompilerRuntimeTest extends TestCase
{
    public function testCompiledFileUsesVersionedName(): void
    {
        $dir = $this->createTempDir();
        $expr = 'field' . bin2hex(random_bytes(12));
        $runtime = new CompilerRuntime($dir);

        try {
            $this->assertSame(123, $runtime($expr, [$expr => 123]));

            $filename = $dir . '/' . CompilerRuntime::functionName($expr) . '.php';
            $this->assertFileExists($filename);
            $this->assertEmpty(glob($dir . '/*.tmp') ?: []);
            $this->assertSame(456, $runtime($expr, [$expr => 456]));
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testFunctionNameUsesVersionedSalt(): void
    {
        $this->assertSame(
            'jmespath_' . md5(
                'jmespath:' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ':3:foo.bar'
            ),
            CompilerRuntime::functionName('foo.bar')
        );
    }

    public function testRuntimesUseJmesPathTruthinessForEmptyObjects(): void
    {
        $dir = $this->createTempDir();

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertSame('x', $runtime('empty_hash || fallback', [
                    'empty_hash' => new \stdClass(),
                    'fallback' => 'x',
                ]));

                $this->assertEquals(new \stdClass(), $runtime('empty_hash && fallback', [
                    'empty_hash' => new \stdClass(),
                    'fallback' => 'x',
                ]));
            }
        } finally {
            $this->removeTempDir($dir);
        }
    }

    private function createTempDir()
    {
        $dir = sys_get_temp_dir() . '/jmespath-compiler-' . bin2hex(random_bytes(12));
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
