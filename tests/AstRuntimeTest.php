<?php
namespace JmesPath\Tests;

use JmesPath\AstRuntime;
use JmesPath\SyntaxErrorException;
use PHPUnit\Framework\TestCase;

class AstRuntimeTest extends TestCase
{
    public function testAstCacheNeverExceedsCap(): void
    {
        $runtime = new AstRuntime();
        $cache = new \ReflectionProperty(AstRuntime::class, 'cache');
        if (PHP_VERSION_ID < 80100) {
            $cache->setAccessible(true);
        }
        $data = ['x' => 1];

        for ($i = 1; $i <= 2100; $i++) {
            $runtime('k' . $i, $data);
            $this->assertLessThanOrEqual(
                1024,
                count($cache->getValue($runtime)),
                "AST cache exceeded 1024 entries after expression #{$i}"
            );
        }
    }

    public function testReturnsIdenticalResultsAcrossCacheWipe(): void
    {
        $runtime = new AstRuntime();
        $data = ['foo' => ['bar' => 42]];
        $before = $runtime('foo.bar', $data);

        for ($i = 1; $i <= 1100; $i++) {
            $runtime('k' . $i, ['x' => 1]);
        }

        $this->assertSame(42, $before);
        $this->assertSame($before, $runtime('foo.bar', $data));
    }

    public function testSyntaxErrorsDoNotEvictCachedEntries(): void
    {
        $runtime = new AstRuntime();
        $cache = new \ReflectionProperty(AstRuntime::class, 'cache');
        if (PHP_VERSION_ID < 80100) {
            $cache->setAccessible(true);
        }

        $this->assertSame(1, $runtime('a', ['a' => 1]));
        for ($i = 1; $i <= 1100; $i++) {
            try {
                $runtime('!!bad' . $i . '!!', []);
            } catch (SyntaxErrorException $e) {
            }
        }

        $this->assertArrayHasKey('a', $cache->getValue($runtime));
    }
}
