<?php
namespace JmesPath\Tests\Tree;

use JmesPath\AstRuntime;
use JmesPath\TreeInterpreter;
use PHPUnit\Framework\TestCase;

/**
 * @covers JmesPath\Tree\TreeInterpreter
 */
class TreeInterpreterTest extends TestCase
{
    public function testReturnsNullWhenMergingNonArray(): void
    {
        $t = new TreeInterpreter();
        $this->assertNull($t->visit([
            'type' => 'flatten',
            'children' => [
                ['type' => 'literal', 'value' => 1],
                ['type' => 'literal', 'value' => 1]
            ]
        ], [], [
            'runtime' => new AstRuntime()
        ]));
    }

    public function testFromlessProjectionReturnsNullForNonArraysWithoutDiagnostics(): void
    {
        $t = new TreeInterpreter();

        $this->assertNull($t->visit($this->fromlessProjection(), 1));
    }

    public function testFromlessProjectionTraversesArrays(): void
    {
        $t = new TreeInterpreter();

        $this->assertSame([1, 2, 3], $t->visit($this->fromlessProjection(), [1, 2, 3]));
    }

    public function testWorksWithArrayObjectAsObject(): void
    {
        $runtime = new AstRuntime();
        $this->assertEquals('baz', $runtime('foo.bar', new \ArrayObject([
            'foo' => new \ArrayObject(['bar' => 'baz'])
        ])));
    }

    public function testWorksWithArrayObjectAsArray(): void
    {
        $runtime = new AstRuntime();
        $this->assertEquals('baz', $runtime('foo[0].bar', new \ArrayObject([
            'foo' => new \ArrayObject([new \ArrayObject(['bar' => 'baz'])])
        ])));
    }

    public function testWorksWithArrayProjections(): void
    {
        $runtime = new AstRuntime();
        $this->assertEquals(
            ['baz'],
            $runtime('foo[*].bar', new \ArrayObject([
                'foo' => new \ArrayObject([
                    new \ArrayObject([
                        'bar' => 'baz'
                    ])
                ])
            ]))
        );
    }

    public function testWorksWithObjectProjections(): void
    {
        $runtime = new AstRuntime();
        $this->assertEquals(
            ['baz'],
            $runtime('foo.*.bar', new \ArrayObject([
                'foo' => new \ArrayObject([
                    'abc' => new \ArrayObject([
                        'bar' => 'baz'
                    ])
                ])
            ]))
        );
    }

    private function fromlessProjection(): array
    {
        return [
            'type' => 'projection',
            'children' => [
                ['type' => 'current'],
                ['type' => 'current'],
            ],
        ];
    }
}
