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

    public function testHandlesEmptyExpressions()
    {
        $p = new Parser(new Lexer());
        $this->assertEquals(array(), $p->compile(''));
    }
}
