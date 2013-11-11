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
        $this->assertEquals(['field', 'foo'], $result[0]);
        $this->assertEquals(['index', '1'], $result[1]);
        $this->assertEquals(['stop'], $result[2]);
    }

    public function testEmitsIndexOpcodesForNestedExpressions()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo[1, 2]');
        $this->assertSame([
            ["field", "foo"],
            ["jump_if_false", 14],
            ["dup_top"],
            ["push", []],
            ["rot_two"],
            ["index", 1],
            ["store_key", null],
            ["rot_two"],
            ["dup_top"],
            ["rot_three"],
            ["index", 2],
            ["store_key", null],
            ["rot_two"],
            ["pop"],
            ["stop"]
        ], $result);
    }

    public function testParsesLbraceWithSimpleExtraction()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo.{bar}');
        $this->assertSame([
            ["field", "foo"],
            ["field", "bar"],
            ["stop"]
        ], $result);
    }

    public function testCreatesWildcardLoop()
    {
        $p = new Parser(new Lexer());
        $result = $p->compile('foo.*[1, 2]');
        $this->assertEquals([
            0  => ['field', 'foo'],
            1  => ['each', 16],
            2  => ['jump_if_false', 15],
            3  => ['dup_top'],
            4  => ['push', []],
            5  => ['rot_two'],
            6  => ['index', 1],
            7  => ['store_key', null],
            8  => ['rot_two'],
            9  => ['dup_top'],
            10 => ['rot_three'],
            11 => ['index', 2],
            12 => ['store_key', null],
            13 => ['rot_two'],
            14 => ['pop'],
            15 => ['goto', 1],
            16 => ['stop']
        ], $result);
    }
}
