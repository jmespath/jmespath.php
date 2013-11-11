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
            ["index", "1"],
            ["store_key", null],
            ["rot_two"],
            ["dup_top"],
            ["rot_three"],
            ["index", "2"],
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
}
