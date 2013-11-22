<?php

namespace JmesPath\Tests;

use JmesPath\Lexer;
use JmesPath\Parser;

/**
 * @covers JmesPath\Parser
 */
class ParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \JmesPath\SyntaxErrorException
     * @expectedExceptionMessage Syntax error at character 0
     */
    public function testMatchesFirstTokens()
    {
        $p = new Parser(new Lexer());
        $p->compile('.bar');
    }

    /**
     * @expectedException \JmesPath\SyntaxErrorException
     * @expectedExceptionMessage Syntax error at character 1
     */
    public function testThrowsSyntaxErrorForInvalidSequence()
    {
        $p = new Parser(new Lexer());
        $p->compile('a,');
    }

    /**
     * @expectedException \JmesPath\SyntaxErrorException
     * @expectedExceptionMessage Syntax error at character 2
     */
    public function testMatchesAfterFirstToken()
    {
        $p = new Parser(new Lexer());
        $p->compile('a.,');
    }

    public function testEmitsIndexOpcodes()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo[1]');
        $this->assertEquals(array('field', 'foo'), $result[0]);
        $this->assertEquals(array('index', '1'), $result[1]);
        $this->assertEquals(array('stop'), $result[2]);
    }

    public function testEmitsIndexOpcodesForNestedExpressions()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo[1, 2]');
        $this->assertSame(array(
            array("field", "foo"),
            array("jump_if_false", 14),
            array("dup_top"),
            array("push", array()),
            array("rot_two"),
            array("index", 1),
            array("store_key", null),
            array("rot_two"),
            array("dup_top"),
            array("rot_three"),
            array("index", 2),
            array("store_key", null),
            array("rot_two"),
            array("pop"),
            array("stop")
        ), $result);
    }

    public function testParsesLbraceWithSimpleExtraction()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo.{bar}');
        $this->assertSame(array(
            array("field", "foo"),
            array("field", "bar"),
            array("stop")
        ), $result);
    }

    public function testCreatesWildcardLoop()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo.*[1, 2]');
        $this->assertEquals(array(
            0  => array('field', 'foo'),
            1  => array('each', 16),
            2  => array('jump_if_false', 15),
            3  => array('dup_top'),
            4  => array('push', array()),
            5  => array('rot_two'),
            6  => array('index', 1),
            7  => array('store_key', null),
            8  => array('rot_two'),
            9  => array('dup_top'),
            10 => array('rot_three'),
            11 => array('index', 2),
            12 => array('store_key', null),
            13 => array('rot_two'),
            14 => array('pop'),
            15 => array('goto', 1),
            16 => array('stop')
        ), $result);
    }
}
