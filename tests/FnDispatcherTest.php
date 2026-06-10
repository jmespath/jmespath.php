<?php
namespace JmesPath\Tests;

use JmesPath\Env;
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

    public function testSumOfEmptyArrayIsZero(): void
    {
        $fn = new FnDispatcher();

        $this->assertSame(0, $fn('sum', [[]]));
        $this->assertSame(3, $fn('sum', [[1, 2]]));
    }

    public function testJoinOfEmptyArrayIsEmptyString(): void
    {
        $fn = new FnDispatcher();

        $this->assertSame('', $fn('join', ['|', []]));
        $this->assertSame('a|b', $fn('join', ['|', ['a', 'b']]));
    }

    public function testMaxAndMinCompareStringsByCodePoint(): void
    {
        $fn = new FnDispatcher();

        $this->assertSame('9', $fn('max', [['10', '9']]));
        $this->assertSame('1', $fn('max', [['01', '1']]));
        $this->assertSame('50', $fn('max', [['1e2', '50']]));
        $this->assertSame('10', $fn('min', [['10', '9']]));
        $this->assertSame('01', $fn('min', [['01', '1']]));
        $this->assertSame('1e2', $fn('min', [['1e2', '50']]));
    }

    public function testMaxByAndMinByCompareStringKeysByCodePoint(): void
    {
        $fn = new FnDispatcher();
        $expr = function ($item) { return $item['k']; };

        $this->assertSame(['k' => '9'], $fn('max_by', [[['k' => '10'], ['k' => '9']], $expr]));
        $this->assertSame(['k' => '10'], $fn('min_by', [[['k' => '10'], ['k' => '9']], $expr]));
    }

    public function testMaxHandlesFalsyFirstElements(): void
    {
        $fn = new FnDispatcher();

        $this->assertSame(1, $fn('max', [[0, 1]]));
        $this->assertSame(0, $fn('max', [[0]]));
        $this->assertSame('', $fn('max', [['']]));
        $this->assertSame('1', $fn('max', [['0', '1']]));
        $this->assertSame(10, $fn('max', [[2.5, 10, 2]]));
    }

    /**
     * @dataProvider mixedKeyFunctionProvider
     */
    public function testMaxByAndMinByRejectMixedKeyTypes(string $fnName): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Argument 0 of {$fnName} encountered a type mismatch in sequence: number, string"
        );

        $fn = new FnDispatcher();
        $fn($fnName, [[['k' => 1], ['k' => 'a']], function ($item) { return $item['k']; }]);
    }

    public static function mixedKeyFunctionProvider(): array
    {
        return [['max_by'], ['min_by']];
    }

    public function testMaxMixedTypesKeepsTheExactMasterMessage(): void
    {
        try {
            (new FnDispatcher())('max', [[1, 'a']]);
            $this->fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Argument 0 of max encountered a type mismatch in sequence: number, string',
                $e->getMessage()
            );
        }
    }

    public function testMaxByAndMinByReturnFirstElementWhenKeysAreEqual(): void
    {
        $fn = new FnDispatcher();
        $expr = function ($item) { return $item['k']; };
        $first = ['k' => 1, 'name' => 'first'];
        $second = ['k' => 1, 'name' => 'second'];

        $this->assertSame($first, $fn('max_by', [[$first, $second], $expr]));
        $this->assertSame($first, $fn('min_by', [[$first, $second], $expr]));
    }

    public function testMaxByComparesSurvivingCarryKeyAgain(): void
    {
        $fn = new FnDispatcher();
        $expr = function ($item) { return $item['k']; };

        $this->assertSame(['k' => 3], $fn('max_by', [[['k' => 3], ['k' => 1], ['k' => 2]], $expr]));
    }

    public function testMaxByEvaluatesKeyExpressionOncePerElement(): void
    {
        $fn = new FnDispatcher();
        $calls = 0;
        $expr = function ($item) use (&$calls) { $calls++; return $item['k']; };

        $fn('max_by', [[['k' => 1], ['k' => 3], ['k' => 2]], $expr]);

        $this->assertSame(3, $calls);
    }

    public function testMinByEvaluatesKeyExpressionOncePerElement(): void
    {
        $fn = new FnDispatcher();
        $calls = 0;
        $expr = function ($item) use (&$calls) { $calls++; return $item['k']; };

        $fn('min_by', [[['k' => 3], ['k' => 1], ['k' => 2]], $expr]);

        $this->assertSame(3, $calls);
    }

    public function testSearchUsesCodePointOrderingForMaxFunctions(): void
    {
        $data = [['k' => '10'], ['k' => '9']];

        $this->assertSame('9', Env::search('max(@)', ['10', '9']));
        $this->assertSame(['k' => '9'], Env::search('max_by(@, &k)', $data));
    }

    public function testMaxComparesStringableObjectsByCodePoint(): void
    {
        $fn = new FnDispatcher();
        $low = new _TestComparableString('10');
        $high = new _TestComparableString('9');

        $this->assertSame($high, $fn('max', [[$low, $high]]));
        $this->assertSame($low, $fn('min', [[$low, $high]]));
    }

    public function testSortComparesNumbersNumerically(): void
    {
        $fn = FnDispatcher::getInstance();

        $this->assertSame([0.20155, 0.6058, 0.63554], $fn('sort', [[0.6058, 0.20155, 0.63554]]));
        $this->assertSame([-2, -1, 3], $fn('sort', [[-1, -2, 3]]));
        $this->assertSame([5, 1.0E+20], $fn('sort', [[1.0E+20, 5]]));
        $this->assertSame([2, 2.5, 10], $fn('sort', [[2.5, 10, 2]]));
    }

    public function testSortComparesStringsByCodePoint(): void
    {
        $fn = FnDispatcher::getInstance();

        $this->assertSame(['x1', 'x10', 'x2'], $fn('sort', [['x10', 'x2', 'x1']]));
        $this->assertSame(['10', '2', '9'], $fn('sort', [['10', '9', '2']]));
        $this->assertSame(['001', '01', '1'], $fn('sort', [['1', '01', '001']]));
    }

    public function testSortByOrdersFloatKeysNumerically(): void
    {
        $data = json_decode(
            '{"values":[{"i32_lv2":"A","f_weight":0.63554},{"i32_lv2":"B","f_weight":0.20155},{"i32_lv2":"C","f_weight":0.6058}]}',
            true
        );

        $this->assertSame(['B', 'C', 'A'], Env::search('sort_by(values, &to_number(f_weight))[].i32_lv2', $data));
        $this->assertSame(['B', 'C', 'A'], Env::search('sort_by(values, &f_weight)[].i32_lv2', $data));
    }

    public function testSortByIsStableForEqualKeys(): void
    {
        $data = [
            ['k' => 1, 'n' => 'third'],
            ['k' => 0, 'n' => 'first'],
            ['k' => 0, 'n' => 'second'],
        ];

        $this->assertSame(['first', 'second', 'third'], Env::search('sort_by(@, &k)[].n', $data));
    }

    public function testSortMixedTypesStillThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('type mismatch in sequence: number, string');

        FnDispatcher::getInstance()('sort', [[1, 'a']]);
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

class _TestComparableString
{
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
