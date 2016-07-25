<?php
namespace JmesPath\Tests;

use JmesPath\FnDispatcher;

class FnDispatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testAbs()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(1, $fn('abs', [1]));
    }

    public function testAvg()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(2, $fn('avg', [[1, 2, 3]]));
        $this->assertEquals(2, $fn('avg', [new ArrayLike([1, 2, 3])]));
    }

    public function testCeil()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(1.0, $fn('ceil', [0.5]));
    }

    public function testContains()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(true, $fn('contains', ['foo', 'foo']));
        $this->assertEquals(false, $fn('contains', ['foo', 'bar']));
        $this->assertEquals(true, $fn('contains', [[1, 2, 3], 2]));
        $this->assertEquals(false, $fn('contains', [[1, 2, 3], 4]));
        $this->assertEquals(true, $fn('contains', [new ArrayLike([1, 2, 3]), 2]));
        $this->assertEquals(false, $fn('contains', [new ArrayLike([1, 2, 3]), 4]));
    }
    
    public function testEndsWith()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(true, $fn('ends_with', ['foobar', 'bar']));
        $this->assertEquals(false, $fn('ends_with', ['foobar', 'foo']));
    }

    public function testFloor()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(0, $fn('floor', [0.5]));
    }

    public function testNotNull()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(1, $fn('not_null', [1, null, 3]));
    }

    public function testJoin()
    {
        $fn = new FnDispatcher();
        $this->assertEquals('a,b', $fn('join', [',', ['a', 'b']]));
        $this->assertEquals('a,b', $fn('join', [',', new ArrayLike(['a', 'b'])]));
    }

    public function testKeys()
    {
        $fn = new FnDispatcher();
        $obj = new \stdClass;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(['foo', 'bar'], $fn('keys', [$obj]));
        $obj = new StdClassLike;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(['foo', 'bar'], $fn('keys', [$obj]));
    }

    public function testLength()
    {
        $fn = new FnDispatcher();
        $obj = new \stdClass;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(2, $fn('length', [$obj]));
        $this->assertEquals(3, $fn('length', ['foo']));
        $this->assertEquals(3, $fn('length', [[1, 2, 3]]));
        $this->assertEquals(3, $fn('length', [new ArrayLike([1, 2, 3])]));
        $obj = new StdClassLike;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(2, $fn('length', [$obj]));
    }

    public function testMax()
    {
        $fn = new FnDispatcher();
        $this->assertEquals('foo', $fn('max', [['foo', 'a']]));
        $this->assertEquals(3, $fn('max', [[1, 2, 3]]));
        $this->assertEquals('foo', $fn('max', [new ArrayLike(['foo', 'a'])]));
        $this->assertEquals(3, $fn('max', [new ArrayLike([1, 2, 3])]));
    }

    public function testMaxBy()
    {
        $fn = new FnDispatcher();
        $person1 = new \stdClass;
        $person1->age = 40;
        $person1->ageStr = '40';
        $person2 = new \stdClass;
        $person2->age = 50;
        $person2->ageStr = '50';
        $this->assertEquals($person2, $fn('max_by', [[$person1, $person2], function ($person) {
            return $person->age;
        }]));
        $this->assertEquals($person2, $fn('max_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->age;
        }]));
        $this->assertEquals('foo', $fn('max_by', [['foo', 'a'], function ($item) {
            return strlen($item);
        }]));
        $this->assertEquals(3, $fn('max_by', [[1, 2, 3], function ($item) {
            return $item;
        }]));
        $this->assertEquals('foo', $fn('max_by', [new ArrayLike(['foo', 'a']), function ($item) {
            return strlen($item);
        }]));
        $this->assertEquals(3, $fn('max_by', [new ArrayLike([1, 2, 3]), function ($item) {
            return $item;
        }]));
        $person1 = new StdClassLike;
        $person1->age = 40;
        $person1->ageStr = '40';
        $person2 = new StdClassLike;
        $person2->age = 50;
        $person2->ageStr = '50';
        $this->assertEquals($person2, $fn('max_by', [[$person1, $person2], function ($person) {
            return $person->age;
        }]));
        $this->assertEquals($person2, $fn('max_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->age;
        }]));
    }

    public function testMin()
    {
        $fn = new FnDispatcher();
        $this->assertEquals('a', $fn('min', [['foo', 'a']]));
        $this->assertEquals(1, $fn('min', [[1, 2, 3]]));
        $this->assertEquals('a', $fn('min', [new ArrayLike(['foo', 'a'])]));
        $this->assertEquals(1, $fn('min', [new ArrayLike([1, 2, 3])]));
    }

    public function testMinBy()
    {
        $fn = new FnDispatcher();
        $person1 = new \stdClass;
        $person1->age = 40;
        $person1->ageStr = '40';
        $person2 = new \stdClass;
        $person2->age = 50;
        $person2->ageStr = '50';
        $this->assertEquals($person1, $fn('min_by', [[$person1, $person2], function ($person) {
            return $person->age;
        }]));
        $this->assertEquals($person1, $fn('min_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->age;
        }]));
        $this->assertEquals('a', $fn('min_by', [['foo', 'a'], function ($item) {
            return strlen($item);
        }]));
        $this->assertEquals(1, $fn('min_by', [[1, 2, 3], function ($item) {
            return $item;
        }]));
        $this->assertEquals('a', $fn('min_by', [new ArrayLike(['foo', 'a']), function ($item) {
            return strlen($item);
        }]));
        $this->assertEquals(1, $fn('min_by', [new ArrayLike([1, 2, 3]), function ($item) {
            return $item;
        }]));
        $person1 = new StdClassLike;
        $person1->age = 40;
        $person1->ageStr = '40';
        $person2 = new StdClassLike;
        $person2->age = 50;
        $person2->ageStr = '50';
        $this->assertEquals($person1, $fn('min_by', [[$person1, $person2], function ($person) {
            return $person->age;
        }]));
        $this->assertEquals($person1, $fn('min_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->age;
        }]));
    }

    public function testReverse()
    {
        $fn = new FnDispatcher();
        $this->assertEquals('oof', $fn('reverse', ['foo']));
        $this->assertEquals([3, 2, 1], $fn('reverse', [[1, 2, 3]]));
        $this->assertEquals([3, 2, 1], $fn('reverse', [new ArrayLike([1, 2, 3])]));
    }

    public function testSum()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(6, $fn('sum', [[1, 2, 3]]));
        $this->assertEquals(6, $fn('sum', [new ArrayLike([1, 2, 3])]));
    }

    public function testSort()
    {
        $fn = new FnDispatcher();
        $this->assertEquals([1, 2, 3], $fn('sort', [[3, 1, 2]]));
        $this->assertEquals(['a', 'b', 'c'], $fn('sort', [['c', 'a', 'b']]));
        $this->assertEquals([1, 2, 3], $fn('sort', [new ArrayLike([3, 1, 2])]));
        $this->assertEquals(['a', 'b', 'c'], $fn('sort', [new ArrayLike(['c', 'a', 'b'])]));
    }

    public function testSortBy()
    {
        $fn = new FnDispatcher();
        $person1 = new \stdClass;
        $person1->age = 50;
        $person1->name = 'foo';
        $person2 = new \stdClass;
        $person2->age = 40;
        $person2->name = 'bar';
        $this->assertEquals([$person2, $person1], $fn('sort_by', [[$person1, $person2], function ($person) {
            return $person->age;
        }]));
        $this->assertEquals([$person2, $person1], $fn('sort_by', [[$person1, $person2], function ($person) {
            return $person->name;
        }]));
        $this->assertEquals([$person2, $person1], $fn('sort_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->age;
        }]));
        $this->assertEquals([$person2, $person1], $fn('sort_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->name;
        }]));
        $this->assertEquals([1, 2, 3], $fn('sort_by', [[3, 1, 2], function ($item) {
            return $item;
        }]));
        $this->assertEquals(['a', 'b', 'c'], $fn('sort_by', [['c', 'a', 'b'], function ($item) {
            return $item;
        }]));
        $this->assertEquals([1, 2, 3], $fn('sort_by', [new ArrayLike([3, 1, 2]), function ($item) {
            return $item;
        }]));
        $this->assertEquals(['a', 'b', 'c'], $fn('sort_by', [new ArrayLike(['c', 'a', 'b']), function ($item) {
            return $item;
        }]));
        $person1 = new StdClassLike;
        $person1->age = 50;
        $person1->name = 'foo';
        $person2 = new StdClassLike;
        $person2->age = 40;
        $person2->name = 'bar';
        $this->assertEquals([$person2, $person1], $fn('sort_by', [[$person1, $person2], function ($person) {
            return $person->age;
        }]));
        $this->assertEquals([$person2, $person1], $fn('sort_by', [[$person1, $person2], function ($person) {
            return $person->name;
        }]));
        $this->assertEquals([$person2, $person1], $fn('sort_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->age;
        }]));
        $this->assertEquals([$person2, $person1], $fn('sort_by', [new ArrayLike([$person1, $person2]), function ($person) {
            return $person->name;
        }]));
    }

    public function testStartsWith()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(true, $fn('starts_with', ['foobar', 'foo']));
        $this->assertEquals(false, $fn('starts_with', ['barfoo', 'foo']));
    }

    public function testType()
    {
        $fn = new FnDispatcher();
        $this->assertEquals('boolean', $fn('type', [true]));
        $this->assertEquals('string', $fn('type', ['foo']));
        $this->assertEquals('null', $fn('type', [null]));
        $this->assertEquals('number', $fn('type', [(double)3]));
        $this->assertEquals('number', $fn('type', [(integer)3]));
        $this->assertEquals('number', $fn('type', [(float)3]));
        $this->assertEquals('array', $fn('type', [[]]));
        $this->assertEquals('object', $fn('type', [new \stdClass]));
        $this->assertEquals('array', $fn('type', [new ArrayLike([])]));
        $this->assertEquals('object', $fn('type', [new StdClassLike]));
    }

    public function testToString()
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
        $this->assertEquals('["foo"]', $fn('to_string', [new ArrayLike(['foo'])]));
        $std = new StdClassLike();
        $std->foo = 'bar';
        $this->assertEquals('{"foo":"bar"}', $fn('to_string', [$std]));
    }

    public function testToNumber()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(1, $fn('to_number', [1]));
        $this->assertEquals(1, $fn('to_number', ['1']));
        $this->assertEquals(1.23, $fn('to_number', [1.23]));
        $this->assertEquals(1.23, $fn('to_number', ['1.23']));
        $this->assertEquals(null, $fn('to_number', ['foo']));
        $this->assertEquals(null, $fn('to_number', ['1.23foo']));
    }

    public function testValues()
    {
        $fn = new FnDispatcher();
        $obj = new \stdClass;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(['foo', 'bar'], $fn('values', [$obj]));
        $this->assertEquals(['foo', 'bar'], $fn('values', [['foo', 'bar']]));
        $this->assertEquals(['foo', 'bar'], $fn('values', [new ArrayLike(['foo', 'bar'])]));
        $obj = new StdClassLike;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals(['foo', 'bar'], $fn('values', [$obj]));
    }

    public function testMerge()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(['foo', 'bar'], $fn('merge', [['1', 'bar'], ['foo']]));
        $this->assertEquals(['foo', 'bar'], $fn('merge', [new ArrayLike(['foo', 'bar']), new ArrayLike(['foo'])]));
        $this->assertEquals(['foo', 'bar'], $fn('merge', [['1', 'bar'], new ArrayLike(['foo'])]));
    }

    public function testToArray()
    {
        $fn = new FnDispatcher();
        $obj = new \stdClass;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals([$obj], $fn('to_array', [$obj]));
        $this->assertEquals(['foo', 'bar'], $fn('to_array', [['foo', 'bar']]));
        $this->assertEquals(['foo', 'bar'], $fn('to_array', [new ArrayLike(['foo', 'bar'])]));
        $obj = new StdClassLike;
        $obj->foo = 'foo';
        $obj->bar = 'bar';
        $this->assertEquals([$obj], $fn('to_array', [$obj]));
    }

    public function testMap()
    {
        $fn = new FnDispatcher();
        $this->assertEquals(['foo', 'bar'], $fn('map', [function ($item) {
            return $item;
        }, ['foo', 'bar']]));
    }

}

class _TestStringClass
{
    public function __toString()
    {
        return 'foo';
    }
}

class _TestJsonStringClass implements \JsonSerializable
{
    public function __toString()
    {
        return 'no!';
    }

    public function jsonSerialize()
    {
        return 'foo';
    }
}
