<?php
namespace JmesPath\Tests;

use JmesPath\AstRuntime;
use JmesPath\CompilerRuntime;
use JmesPath\SyntaxErrorException;
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
                'jmespath:' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ':5:foo.bar'
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

    public function testRuntimesTreatFloatZeroAsTruthy(): void
    {
        $dir = $this->createTempDir();
        $data = ['orders' => [['price' => 0.0], ['price' => 1.0], ['price' => 2.0]]];

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertSame([0.0, 1.0, 2.0], $runtime('orders[?price].price', $data));
                $this->assertSame(0.0, $runtime('a || b', ['a' => 0.0, 'b' => 'fallback']));
                $this->assertSame('x', $runtime('a && b', ['a' => 0.0, 'b' => 'x']));
                $this->assertFalse($runtime('!a', ['a' => 0.0]));
            }
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testRuntimesApplyJmesPathTruthinessInFilters(): void
    {
        $dir = $this->createTempDir();
        $data = ['orders' => [
            ['price' => 0],
            ['price' => 0.0],
            ['price' => '0'],
            ['price' => '0.0'],
            ['price' => ''],
            ['price' => null],
            ['price' => false],
            ['price' => []],
            ['price' => new \stdClass()],
        ]];

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertSame([0, 0.0, '0', '0.0'], $runtime('orders[?price].price', $data));
            }
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testRuntimesUseJsonSemanticEqualityForNumbers(): void
    {
        $dir = $this->createTempDir();

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertTrue($runtime('`1` == `1.0`', null));
            }
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testRuntimesStopProjectionsAtMultiSelectHashes(): void
    {
        $dir = $this->createTempDir();
        $data = [
            'people' => [
                ['age' => 20, 'name' => 'Bob'],
                ['age' => 25, 'name' => 'Fred'],
                ['age' => 30, 'name' => 'George'],
            ],
        ];

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertSame(
                    [['name' => 'Fred'], ['name' => 'George']],
                    $runtime('people[?age >= `25`].{name: name}', $data)
                );
                $this->assertSame(
                    ['name' => 'Fred'],
                    $runtime('people[?age >= `25`].{name: name}[0]', $data)
                );
                $this->assertSame(
                    'Fred',
                    $runtime('people[?age >= `25`].{name: name}[0].name', $data)
                );
                $this->assertNull($runtime('people[?age >= `25`].{name: name}.name', $data));

                // Multi-select lists already ended projections; pin the symmetry.
                $this->assertSame(['Bob'], $runtime('people[*].[name][0]', $data));
            }
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testCompiledRuntimeSortsNumerically(): void
    {
        $dir = $this->createTempDir();

        try {
            $runtime = new CompilerRuntime($dir);
            $this->assertSame([-2, -1, 3], $runtime('sort(@)', [-1, -2, 3]));
            $data = json_decode(
                '{"values":[{"v":"A","w":0.63554},{"v":"B","w":0.20155},{"v":"C","w":0.6058}]}',
                true
            );
            $this->assertSame(['B', 'C', 'A'], $runtime('sort_by(values, &w)[].v', $data));
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testRejectsLiteralFunctionName(): void
    {
        $dir = $this->createTempDir();

        try {
            $runtime = new CompilerRuntime($dir);
            $runtime('`"not_a_function"`(@)', []);
            $this->fail('Expected SyntaxErrorException');
        } catch (SyntaxErrorException $e) {
            $this->assertSame([], glob($dir . '/*') ?: []);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testStillCompilesValidFunctions(): void
    {
        $dir = $this->createTempDir();

        try {
            $runtime = new CompilerRuntime($dir);

            $this->assertSame(3, $runtime('length(@)', [1, 2, 3]));
            $this->assertTrue($runtime("contains(@, 'b')", ['a', 'b', 'c']));
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
