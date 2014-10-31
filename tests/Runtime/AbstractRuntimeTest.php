<?php
namespace JmesPath\Tests\Runtime;

class AbstractRuntimeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Call to undefined function foo
     */
    public function testThrowsWhenNoFunctionMatchesName()
    {
        $r = $this->getMockBuilder('JmesPath\Runtime\AbstractRuntime')
            ->getMockForAbstractClass();
        $r->callFunction('foo', array('bar'));
    }

    public function testCanCallFunctions()
    {
        $r = $this->getMockBuilder('JmesPath\Runtime\AbstractRuntime')
            ->getMockForAbstractClass();
        $r->registerFunction('foo', function () { return 'abc'; });
        $this->assertEquals('abc', $r->callFunction('foo', array()));
        $this->assertEquals('1', $r->callFunction('abs', array(-1)));
    }
}
