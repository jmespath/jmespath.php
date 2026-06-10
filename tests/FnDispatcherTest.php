<?php
namespace JmesPath\Tests;

use JmesPath\FnDispatcher;
use PHPUnit\Framework\TestCase;

class FnDispatcherTest extends TestCase
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

    public function testReversesUtf8Strings(): void
    {
        $fn = new FnDispatcher();

        $this->assertSame('aé', $fn('reverse', ['éa']));
        $this->assertSame('☃ba', $fn('reverse', ['ab☃']));
    }

    public function testContainsUsesJsonSemantics(): void
    {
        $fn = new FnDispatcher();

        $this->assertFalse($fn('contains', [[1, 2, 3], '2']));
        $this->assertFalse($fn('contains', [['1', 8], 1]));
        $this->assertTrue($fn('contains', [[1, 2], 2.0]));
        $this->assertTrue($fn('contains', [[['a' => 1]], (object) ['a' => 1]]));
        $this->assertFalse($fn('contains', ['foobar', 123]));
    }

    /**
     * @dataProvider toNumberProvider
     */
    public function testToNumberParsesJsonNumberStrings($input, $expected): void
    {
        $fn = new FnDispatcher();

        $this->assertSame($expected, $fn('to_number', [$input]));
    }

    public static function toNumberProvider(): array
    {
        return [
            ['1e2', 100.0],
            ['1E+2', 100.0],
            ['1E-2', 0.01],
            ['1.5', 1.5],
            ['4', 4],
            ['-0', 0],
            [(string) PHP_INT_MAX, PHP_INT_MAX],
            [(string) PHP_INT_MIN, PHP_INT_MIN],
            ['9223372036854775808', 9.223372036854776E+18],
            ['+1', null],
            ['01', null],
            [' 1', null],
            ['1 ', null],
            ["1\n", null],
            ['.5', null],
            ['1.', null],
            ['NaN', null],
            ['INF', null],
            ['0x1A', null],
            ['1e10000', null],
        ];
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
