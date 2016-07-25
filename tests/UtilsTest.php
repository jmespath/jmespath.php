<?php
namespace JmesPath\Tests;

use JmesPath\Utils;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    public function typeProvider()
    {
        return [
            ['a', 'string'],
            [10, 'number'],
            [1.0, 'number'],
            [true, 'boolean'],
            [false, 'boolean'],
            [[], 'array'],
            [[1, 2], 'array'],
            [['a' => 1], 'object'],
            [new \stdClass(), 'object'],
            [function () {}, 'expression'],
            [new \ArrayObject(), 'array'],
            [new \ArrayObject([1, 2]), 'array'],
            [new \ArrayObject(['foo' => 'bar']), 'object'],
            [new _TestStr(), 'string'],
            [new ArrayLike(), 'array'],
            [new StdClassLike(), 'object']
        ];
    }

    /**
     * @dataProvider typeProvider
     */
    public function testGetsTypes($given, $type)
    {
        $this->assertEquals($type, Utils::type($given));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsForInvalidArg()
    {
        Utils::type(new _TestClass());
    }

    public function isArrayProvider()
    {
        return [
            [[], true],
            [[1, 2], true],
            [['a' => 1], false],
            [new _TestClass(), false],
            [new \ArrayObject(['a' => 'b']), false],
            [new \ArrayObject([1]), true],
            [new \stdClass(), false],
            [new ArrayLike(['a' => 'b']), false],
            [new ArrayLike([1]), true],
            [new StdClassLike(), false]
        ];
    }

    /**
     * @dataProvider isArrayProvider
     */
    public function testChecksIfArray($given, $result)
    {
        $this->assertSame($result, Utils::isArray($given));
    }

    public function isObjectProvider()
    {
        return [
            [[], true],
            [[1, 2], false],
            [['a' => 1], true],
            [new _TestClass(), false],
            [new \ArrayObject(['a' => 'b']), true],
            [new \ArrayObject([1]), false],
            [new \stdClass(), true],
            [new ArrayLike(['a' => 'b']), true],
            [new ArrayLike([1]), false],
            [new StdClassLike(), true]
        ];
    }

    /**
     * @dataProvider isObjectProvider
     */
    public function testChecksIfObject($given, $result)
    {
        $this->assertSame($result, Utils::isObject($given));
    }

    public function testHasStableSort()
    {
        $data = [new _TestStr(), new _TestStr(), 0, 10, 2];
        $result = Utils::stableSort($data, function ($a, $b) {
            $a = (int) (string) $a;
            $b = (int) (string) $b;
            return $a > $b ? -1 : ($a == $b ? 0 : 1);
        });
        $this->assertSame($data[0], $result[0]);
        $this->assertSame($data[1], $result[1]);
        $this->assertEquals(10, $result[2]);
        $this->assertEquals(2, $result[3]);
        $this->assertEquals(0, $result[4]);
    }

    public function testSlicesArrays()
    {
        $this->assertEquals([3, 2, 1], Utils::slice([1, 2, 3], null, null, -1));
        $this->assertEquals([1, 3], Utils::slice([1, 2, 3], null, null, 2));
        $this->assertEquals([2, 3], Utils::slice([1, 2, 3], 1));
        $this->assertEquals([3, 2, 1], Utils::slice(new ArrayLike([1, 2, 3]), null, null, -1));
        $this->assertEquals([1, 3], Utils::slice(new ArrayLike([1, 2, 3]), null, null, 2));
        $this->assertEquals([2, 3], Utils::slice(new ArrayLike([1, 2, 3]), 1));
    }

    public function testSlicesStrings()
    {
        $this->assertEquals('cba', Utils::slice('abc', null, null, -1));
        $this->assertEquals('ac', Utils::slice('abc', null, null, 2));
        $this->assertEquals('bc', Utils::slice('abc', 1));
    }

    public function testChecksIfTruthy()
    {
        $this->assertEquals(true, Utils::isTruthy(true));
        $this->assertEquals(false, Utils::isTruthy(false));
        $this->assertEquals(true, Utils::isTruthy(0));
        $this->assertEquals(true, Utils::isTruthy('0'));
        $obj = new \stdClass;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(true, Utils::isTruthy($obj));
        $obj = new \stdClass;
        $this->assertEquals(false, Utils::isTruthy($obj));
        $this->assertEquals(false, Utils::isTruthy([]));
        $this->assertEquals(true, Utils::isTruthy(['foo']));
        $this->assertEquals(false, Utils::isTruthy(new ArrayLike([])));
        $this->assertEquals(true, Utils::isTruthy(new ArrayLike(['foo'])));
        $obj = new StdClassLike;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(true, Utils::isTruthy($obj));
        $obj = new StdClassLike;
        $this->assertEquals(false, Utils::isTruthy($obj));
    }

    public function testChecksIsEqual()
    {
        $this->assertEquals(true, Utils::isEqual(true, true));
        $this->assertEquals(true, Utils::isEqual(false, false));
        $this->assertEquals(true, Utils::isEqual(1, 1));
        $this->assertEquals(true, Utils::isEqual('foo', 'foo'));
        $a = new \stdClass;
        $a->foo = 'foo';
        $b = new \stdClass;
        $b->foo = 'foo';
        $this->assertEquals(true, Utils::isEqual($a, $b));
        $a = new \stdClass;
        $a->foo = 'foo';
        $b = new \stdClass;
        $b->foo = 'bar';
        $this->assertEquals(false, Utils::isEqual($a, $b));
        $this->assertEquals(true, Utils::isEqual([], []));
        $this->assertEquals(true, Utils::isEqual(['foo'], ['foo']));
        $this->assertEquals(false, Utils::isEqual(['foo'], ['bar']));
        $this->assertEquals(true, Utils::isEqual(new ArrayLike([]), new ArrayLike([])));
        $this->assertEquals(true, Utils::isEqual(new ArrayLike(['foo']), new ArrayLike(['foo'])));
        $this->assertEquals(false, Utils::isEqual(new ArrayLike(['foo']), new ArrayLike(['bar'])));
        $a = new StdClassLike;
        $a->foo = 'foo';
        $b = new StdClassLike;
        $b->foo = 'foo';
        $this->assertEquals(true, Utils::isEqual($a, $b));
        $a = new StdClassLike;
        $a->foo = 'foo';
        $b = new StdClassLike;
        $b->foo = 'bar';
        $this->assertEquals(false, Utils::isEqual($a, $b));
    }
}

class _TestClass implements \ArrayAccess
{
    public function offsetExists($offset) {}
    public function offsetGet($offset) {}
    public function offsetSet($offset, $value) {}
    public function offsetUnset($offset) {}
}

class _TestStr
{
    public function __toString()
    {
        return '100';
    }
}
