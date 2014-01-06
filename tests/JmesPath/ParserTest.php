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
        $this->assertEquals(array('push_current'), $result[0]);
        $this->assertEquals(array('field', 'foo'), $result[1]);
        $this->assertEquals(array('index', '1'), $result[2]);
        $this->assertEquals(array('stop'), $result[3]);
    }

    public function testEmitsIndexOpcodesForNestedExpressions()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo[[1], [2]]');
        $this->assertSame(array (
            0 => array('push_current'),
            1 => array('field', 'foo'),
            2 => array('is_array'),
            3 => array('jump_if_false', 14),
            4 => array('mark_current'),
            5 => array('pop'),
            6 => array('push', array()),
            7 => array('push_current'),
            8 => array('index', 1),
            9 => array('store_key', null),
            10 => array('push_current'),
            11 => array('index', 2,),
            12 => array('store_key', 1 => null),
            13 =>array('pop_current'),
            14 => array('stop'),
        ), $result);
    }

    public function testCreatesWildcardLoop()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo.*[[1], [2]]');

        $this->assertEquals(array(
            0 => array('push_current'),
            1  => array('field', 'foo'),
            2  => array('each', 18, 'object'),
            3  => array('mark_current'),
            4  => array('is_array'),
            5  => array('jump_if_false', 16),
            6  => array('mark_current'),
            7  => array('pop'),
            8  => array('push', array()),
            9  => array('push_current'),
            10  => array('index', 1),
            11 => array('store_key', null),
            12 => array('push_current'),
            13 => array('index', 2),
            14 => array('store_key', null),
            15 => array('pop_current'),
            16 => array('pop_current'),
            17 => array('jump', 2),
            18 => array('stop')
        ), $result);
    }

    public function testHandlesEmptyExpressions()
    {
        $p = new Parser(new Lexer());
        $this->assertEquals(array(array('stop')), $p->compile(''));
    }
}
