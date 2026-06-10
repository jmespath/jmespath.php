<?php
namespace JmesPath\Tests;

use JmesPath\Env;
use JmesPath\CompilerRuntime;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    public function testSearchesInput(): void
    {
        $data = ['foo' => 123];
        $this->assertEquals(123, Env::search('foo', $data));
        $this->assertEquals(123, Env::search('foo', $data));
    }

    public function testSearchesWithFunction(): void
    {
        $data = ['foo' => 123];
        $this->assertEquals(123, \JmesPath\search('foo', $data));
    }

    public function testCleansCompileDir(): void
    {
        $dir = sys_get_temp_dir();
        $runtime = new CompilerRuntime($dir);
        $runtime('@ | @ | @[0][0][0]', []);
        $this->assertNotEmpty(glob($dir . '/jmespath_*.php'));
        $this->assertGreaterThan(0, Env::cleanCompileDir());
        $this->assertEmpty(glob($dir . '/jmespath_*.php'));
    }

    public function testCleansCompileDirWhenCompileEnvIsOn(): void
    {
        $serverExists = array_key_exists(Env::COMPILE_DIR, $_SERVER);
        $serverValue = $serverExists ? $_SERVER[Env::COMPILE_DIR] : null;
        $_SERVER[Env::COMPILE_DIR] = 'on';

        $expr = 'env' . bin2hex(random_bytes(12));
        $filename = sys_get_temp_dir() . '/' . CompilerRuntime::functionName($expr) . '.php';
        $runtime = new CompilerRuntime(sys_get_temp_dir());

        try {
            $this->assertSame(1, $runtime($expr, [$expr => 1]));
            $this->assertFileExists($filename);
            $this->assertGreaterThan(0, Env::cleanCompileDir());
            $this->assertFileNotExists($filename);
        } finally {
            @unlink($filename);
            if ($serverExists) {
                $_SERVER[Env::COMPILE_DIR] = $serverValue;
            } else {
                unset($_SERVER[Env::COMPILE_DIR]);
            }
        }
    }
}
