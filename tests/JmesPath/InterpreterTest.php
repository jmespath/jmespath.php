<?php

namespace JmesPath\Tests;

use JmesPath\Interpreter;

/**
 * @covers JmesPath\Interpreter
 */
class InterpreterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid stack for store_key
     */
    public function testPushesArrayWhenStoreKeyIsCalledWithNoArray()
    {
        $i = new Interpreter();
        $result = $i->execute(array(
            array('push', null),
            array('push', null),
            array('store_key', 'abc')
        ), array());
        $this->assertEquals(array(), $result);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unknown opcode foo
     */
    public function testThrowsExceptionWhenAnInvalidOpcodeIsFound()
    {
        $i = new Interpreter();
        $i->execute(array(array('foo')), array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage debug must be a resource
     */
    public function testEnsuresDebugIsResource()
    {
        $i = new Interpreter('foo');
        $i->execute(array(), array());
    }

    public function testOutputsDebugInformation()
    {
        $r = fopen('php://temp', 'r+');
        $i = new Interpreter($r);
        $i->execute(array(
            array('push', 'foo')
        ), array());
        rewind($r);
        $contents = stream_get_contents($r);
        $this->assertNotEmpty($contents);
        fclose($r);
        $this->assertContains('push', $contents);
        $this->assertContains('foo', $contents);
    }

    public function testShowsIfFinalStateIsBorked()
    {
        $r = fopen('php://temp', 'r+');
        $i = new Interpreter($r);
        $ref = new \ReflectionMethod($i, 'debugFinal');
        $ref->setAccessible(true);
        $ref->invoke($i, array('abc', '123'), array('def', '456'));
        rewind($r);
        $this->assertContains('abc', stream_get_contents($r));
        rewind($r);
        $this->assertContains('456', stream_get_contents($r));
        fclose($r);
    }

    public function testPushesAndPops()
    {
        $i = new Interpreter();
        $this->assertEquals('foo', $i->execute(array(array('push', 'foo')), array()));
        $this->assertEquals(
            'bar',
            $i->execute(array(
                array('push', 'bar'),
                array('push', 'foo'),
                array('pop'),
            ), array())
        );
    }

    public function testDescendsIntoIndex()
    {
        $i = new Interpreter();
        $this->assertEquals(
            'bar',
            $i->execute(array(array('index', 1)), array('baz', 'bar'))
        );
        $this->assertNull(
            $i->execute(array(array('index', 2)), array('baz', 'bar'))
        );
        $this->assertNull(
            $i->execute(array(
                array('field', 'foo'),
                array('index', 0)
            ), array('foo' => 'baz'))
        );
    }

    public function testDescendsIntoField()
    {
        $i = new Interpreter();
        $this->assertEquals(
            'bar',
            $i->execute(array(array('field', 'foo')), array('foo' => 'bar'))
        );
        $this->assertNull(
            $i->execute(array(array('field', 'notfoo')), array('foo' => 'bar'))
        );
        $this->assertNull(
            $i->execute(array(
                array('field', 'foo'),
                array('field', 'foo')
            ), array('foo' => 'baz'))
        );
    }

    public function emptyOrFalseProvider()
    {
        return array(
            array(0, false, false),
            array(' ', false, false),
            array('', false, false),
            array(false, false, true),
            array(null, false, true),
            array(array(), true, false),
        );
    }

    /**
     * @dataProvider emptyOrFalseProvider
     */
    public function testChecksIfEmptyOrFalse($check, $resultArray, $resultFalse)
    {
        $i = new Interpreter();
        $codes = array(array('index', 0), array('is_array'));
        $this->assertEquals($resultArray, $i->execute($codes, array($check)));
        $codes = array(array('index', 0), array('is_falsey'));
        $this->assertEquals($resultFalse, $i->execute($codes, array($check)));
    }

    public function testMergesTos()
    {
        $i = new Interpreter();
        $this->assertEquals(
            array(1, 2, 3),
            $i->execute(array(array('merge')), array(
                array(1),
                array(2),
                array(3),
            ))
        );

        $this->assertEquals(
            array('foo'),
            $i->execute(array(array('merge')), array('foo'))
        );

        $this->assertEquals(array(), $i->execute(array(array('merge')), array()));
        $orig = array(array('foo' => 'bar'), array('foo' => 'baz'));
        $this->assertEquals($orig, $i->execute(array(array('merge')), $orig));
    }

    public function testCanRegisterCustomFunctions()
    {
        $i = new Interpreter();
        $args = null;
        $i->registerFunction('foo', function ($a) use (&$args) {
            $args = $a;
        });
        $i->execute(array(
            array('push', 1),
            array('push', 2),
            array('call', 'foo', 2)
        ), array());
        $this->assertEquals(array(1, 2), $args);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesFunctionsAreCallable()
    {
        $i = new Interpreter();
        $i->registerFunction('foo', 'baz');
    }

    /**
     * @expectedExceptionMessage Call to undefined function: foo
     * @expectedException \RuntimeException
     */
    public function testValidatesFunctionsExist()
    {
        $i = new Interpreter();
        $i->execute(array(array('call', 'foo', 0)), array());
    }
}
