<?php
namespace JmesPath\Tests;

use JmesPath\fnDispatcher;
use PHPUnit\Framework\TestCase;

class fnDispatcherTest extends TestCase
{
    public function testConvertsToString(): void
    {
        $fn = new FnDispatcher();
        $this->assertEquals('foo', $fn('to_string', ['foo']));
        $this->assertEquals('1', $fn('to_string', [1]));
        $this->assertEquals('["foo"]', $fn('to_string', [['foo']]));
        $std = new \stdClass();
        $std->foo = 'bar';
        $this->assertEquals('{"foo":"bar"}', $fn('to_string', [$std]));
        $this->assertEquals('foo', $fn('to_string', [new _TestStringClass()]));
        $this->assertEquals('"foo"', $fn('to_string', [new _TestJsonStringClass()]));
    }

    public function testMapRequiresArraySecondArgument(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Argument 1 of map must be one of the following types: array. null found'
        );

        $fn = new FnDispatcher();
        $fn('map', [function ($v) { return $v; }, null]);
    }
}

class _TestStringClass
{
    public function __toString(): string
    {
        return 'foo';
    }
}

class _TestJsonStringClass implements \JsonSerializable
{
    public function __toString(): string
    {
        return 'no!';
    }

    public function jsonSerialize(): string
    {
        return 'foo';
    }
}
