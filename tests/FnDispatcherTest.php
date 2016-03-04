<?php
namespace JmesPath\Tests;

use JmesPath\FnDispatcher;

class FnDispatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testConvertsToString()
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

    public function testCustomFunctions()
    {
        $callable = new _TestCustomFunctionCallable();

        $fn = new FnDispatcher();
        $fn->registerCustomFn('double', [$callable, 'double']);
        $fn->registerCustomFn('testSuffix', [$callable, 'testSuffix']);
        $fn->registerCustomFn('testTypeValidation', [$callable, 'testTypeValidation'], [['number'], ['number']]);

        $this->assertEquals(4, $fn('double', [2]));
        $this->assertEquals('someStringTest', $fn('testSuffix', ['someString']));

        // check type validation
        try {
            $this->assertEquals(2, $fn('testTypeValidation', [1, '1']));
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e);
        }

        $this->assertEquals(4, $fn('testTypeValidation', [2, 2]));
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

class _TestCustomFunctionCallable
{
    public function double($args)
    {
        return $args[0] * 2;
    }

    public function testSuffix($args)
    {
        return $args[0].'Test';
    }

    public function testTypeValidation($args)
    {
        return $args[0] + $args[1];
    }
}
