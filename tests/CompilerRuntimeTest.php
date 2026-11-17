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
                'jmespath:' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ':7:foo.bar'
            ),
            CompilerRuntime::functionName('foo.bar')
        );
    }

    /**
     * @requires function posix_geteuid
     */
    public function testDefaultCacheDirIsPrivateToCurrentUser(): void
    {
        $temp = $this->createTempDir();
        $dir = $temp . '/jmespath-' . posix_geteuid();

        try {
            $this->assertSame($dir, $this->runInTempDir($temp, 'echo (new JmesPath\CompilerRuntime())->getCacheDir();'));
            $this->assertSame(posix_geteuid(), fileowner($dir));
            $this->assertSame(0700, fileperms($dir) & 0777);
        } finally {
            $this->removeTempDir($dir);
            $this->removeTempDir($temp);
        }
    }

    /**
     * @requires function posix_geteuid
     */
    public function testDefaultCacheDirIgnoresFilesInSystemTempDir(): void
    {
        $temp = $this->createTempDir();
        $name = CompilerRuntime::functionName('foo');
        file_put_contents($temp . '/' . $name . '.php', "<?php function {$name}() { return 'planted'; }");

        try {
            $this->assertSame('123', $this->runInTempDir($temp, 'echo (new JmesPath\CompilerRuntime())("foo", ["foo" => 123]);'));
        } finally {
            $this->removeTempDir($temp . '/jmespath-' . posix_geteuid());
            $this->removeTempDir($temp);
        }
    }

    /**
     * @requires function posix_geteuid
     */
    public function testRejectsDefaultCacheDirAccessibleByOthers(): void
    {
        $temp = $this->createTempDir();
        $dir = $temp . '/jmespath-' . posix_geteuid();
        mkdir($dir);
        chmod($dir, 0755);

        try {
            $this->assertSame(
                "Unable to use private cache directory: {$dir}",
                $this->runInTempDir($temp, 'new JmesPath\CompilerRuntime();')
            );
        } finally {
            $this->removeTempDir($dir);
            $this->removeTempDir($temp);
        }
    }

    /**
     * @requires function posix_geteuid
     */
    public function testRejectsSymlinkedDefaultCacheDir(): void
    {
        $temp = $this->createTempDir();
        $dir = $temp . '/jmespath-' . posix_geteuid();
        $target = $this->createTempDir();
        chmod($target, 0700);
        symlink($target, $dir);

        try {
            $this->assertSame(
                "Unable to use private cache directory: {$dir}",
                $this->runInTempDir($temp, 'new JmesPath\CompilerRuntime();')
            );
        } finally {
            unlink($dir);
            $this->removeTempDir($target);
            $this->removeTempDir($temp);
        }
    }

    /**
     * @requires function posix_geteuid
     */
    public function testChecksDefaultCacheDirAgainBeforeLoading(): void
    {
        $temp = $this->createTempDir();
        $dir = $temp . '/jmespath-' . posix_geteuid();

        try {
            $this->runInTempDir($temp, '(new JmesPath\CompilerRuntime())("foo", []);');
            $this->assertSame(
                "Unable to use private cache directory: {$dir}",
                $this->runInTempDir(
                    $temp,
                    '$runtime = new JmesPath\CompilerRuntime(); chmod($runtime->getCacheDir(), 0755); $runtime("foo", []);'
                )
            );
        } finally {
            $this->removeTempDir($dir);
            $this->removeTempDir($temp);
        }
    }

    /**
     * @requires function posix_geteuid
     */
    public function testKeepsDefaultCacheDirWhenWorkingDirectoryChanges(): void
    {
        $temp = $this->createTempDir();
        $dir = $temp . '/jmespath-' . posix_geteuid();
        $code = sprintf(
            'chdir(%s); $runtime = new JmesPath\CompilerRuntime(); chdir("/"); $runtime("foo", []); echo $runtime->getCacheDir();',
            var_export($temp, true)
        );

        try {
            $this->assertSame($dir, $this->runInTempDir('.', $code));
        } finally {
            $this->removeTempDir($dir);
            $this->removeTempDir($temp);
        }
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

    public function testRuntimesReturnNullWhenOrderingNonNumbers(): void
    {
        $dir = $this->createTempDir();
        $data = ['one' => 1, 'two' => 2, 'string' => 'a', 'list' => []];

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertNull($runtime('string < one', $data));
                $this->assertNull($runtime('one >= list', $data));
                $this->assertNull($runtime('string > string', $data));
                $this->assertNull($runtime('missing <= one', $data));
                $this->assertSame('fallback', $runtime("not_null(string < one, 'fallback')", $data));
                $this->assertSame('fallback', $runtime("(string < one).not_null(@, 'fallback')", $data));
                $this->assertFalse($runtime('two < one', $data));
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

    public function testRuntimesEvaluateSubexpressionsOfNull(): void
    {
        $dir = $this->createTempDir();
        $data = ['items' => [['a' => 1], ['a' => null], ['b' => 2]]];

        try {
            foreach ([new AstRuntime(), new CompilerRuntime($dir)] as $runtime) {
                $this->assertSame('null', $runtime('missing.type(@)', $data));
                $this->assertSame('fallback', $runtime("missing.not_null(@, 'fallback')", $data));
                $this->assertSame(['number', 'null', 'null'], $runtime('items[*].a.type(@)', $data));
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

    private function runInTempDir($temp, $code)
    {
        $command = sprintf(
            '%s -d sys_temp_dir=%s -r %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($temp),
            escapeshellarg(sprintf(
                'require %s; try { %s } catch (RuntimeException $e) { echo $e->getMessage(); }',
                var_export(__DIR__ . '/../vendor/autoload.php', true),
                $code
            ))
        );
        exec($command, $output);

        return implode("\n", $output);
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
