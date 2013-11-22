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
            array("is_empty"),
            array("jump_if_true", 15),
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
            1  => array('each', 17),
            2  => array("is_empty"),
            3  => array('jump_if_true', 16),
            4  => array('dup_top'),
            5  => array('push', array()),
            6  => array('rot_two'),
            7  => array('index', 1),
            8  => array('store_key', null),
            9  => array('rot_two'),
            10  => array('dup_top'),
            11 => array('rot_three'),
            12 => array('index', 2),
            13 => array('store_key', null),
            14 => array('rot_two'),
            15 => array('pop'),
            16 => array('goto', 1),
            17 => array('stop')
        ), $result);
    }
}
