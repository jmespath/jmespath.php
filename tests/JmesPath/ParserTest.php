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
        $this->assertSame(array (
            0 => array('field', 'foo'),
            1 => array('is_empty'),
            2 => array('jump_if_true', 13),
            3 => array('mark_current'),
            4 => array('pop'),
            5 => array('push', array()),
            6 => array('push_current'),
            7 => array('index', 1),
            8 => array('store_key', null),
            9 => array('push_current'),
            10 => array('index', 2,),
            11 => array('store_key', 1 => null),
            12 =>array('pop_current'),
            13 => array('stop'),
        ), $result);
    }

    public function testCreatesWildcardLoop()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo.*[1, 2]');

        $this->assertEquals(array(
            0  => array('field', 'foo'),
            1  => array('each', 17, 'Object'),
            2  => array('mark_current'),
            3  => array('is_empty'),
            4  => array('jump_if_true', 15),
            5  => array('mark_current'),
            6  => array('pop'),
            7  => array('push', array()),
            8  => array('push_current'),
            9  => array('index', 1),
            10 => array('store_key', null),
            11 => array('push_current'),
            12 => array('index', 2),
            13 => array('store_key', null),
            14 => array('pop_current'),
            15 => array('pop_current'),
            16 => array('jump', 1),
            17 => array('stop')
        ), $result);
    }

    public function testHandlesEmptyExpressions()
    {
        $p = new Parser(new Lexer());
        $this->assertEquals(array(), $p->compile(''));
    }
}
