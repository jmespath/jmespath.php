<?php

namespace JmesPath\Tests;

use JmesPath\Lexer;
use JmesPath\SyntaxErrorException;

/**
 * @covers JmesPath\Lexer
 */
class LexerTest extends \PHPUnit_Framework_TestCase
{
    public function testHasInput()
    {
        $l = new Lexer();
        $l->setInput('foo');
        $this->assertSame('foo', $l->getInput());
    }

    public function testHasIterator()
    {
        $l = new Lexer();
        $l->setInput('foo');
        $i = $l->getIterator();
        $this->assertInstanceOf('ArrayIterator', $i);
        $this->assertEquals(array(
            array('type' => 'T_IDENTIFIER', 'value' => 'foo', 'pos' => 0),
            array ('type' => 'T_EOF', 'value' => null, 'pos' => 3),
        ), iterator_to_array($i));
    }

    public function testValidatesClosedQuotes()
    {
        $l = new Lexer();
        $l->setInput('"foo"."baz');
        try {
            $l->getIterator();
            $this->fail('Did not throw');
        } catch (SyntaxErrorException $e) {
            $expected = <<<EOT
Syntax error at character 6
"foo"."baz
      ^
Unclosed quote character
EOT;
            $this->assertContains($expected, $e->getMessage());
        }
    }

    public function testValidatesLiteralValues()
    {
        $l = new Lexer();
        $l->setInput('_');
        try {
            $l->getIterator();
            $this->fail('Did not throw');
        } catch (SyntaxErrorException $e) {
            $expected = <<<EOT
Syntax error at character 0
_
^
Literal token with no value
EOT;
            $this->assertContains($expected, $e->getMessage());
        }
    }

    public function inputProvider()
    {
        return array(
            array('0', Lexer::T_NUMBER),
            array('1', Lexer::T_NUMBER),
            array('2', Lexer::T_NUMBER),
            array('3', Lexer::T_NUMBER),
            array('4', Lexer::T_NUMBER),
            array('5', Lexer::T_NUMBER),
            array('6', Lexer::T_NUMBER),
            array('7', Lexer::T_NUMBER),
            array('8', Lexer::T_NUMBER),
            array('9', Lexer::T_NUMBER),
            array('-1', Lexer::T_NUMBER),
            array('-1.5', Lexer::T_NUMBER),
            array('109.5', Lexer::T_NUMBER),
            array('.', Lexer::T_DOT),
            array('{', Lexer::T_LBRACE),
            array('}', Lexer::T_RBRACE),
            array('[', Lexer::T_LBRACKET),
            array(']', Lexer::T_RBRACKET),
            array(':', Lexer::T_COLON),
            array(',', Lexer::T_COMMA),
            array('||', Lexer::T_OR),
            array('*', Lexer::T_STAR),
            array('foo', Lexer::T_IDENTIFIER),
            array('"foo"', Lexer::T_IDENTIFIER),
            array('_true', Lexer::T_LITERAL),
            array('_false', Lexer::T_LITERAL),
            array('_null', Lexer::T_LITERAL),
            array('_"true"', Lexer::T_LITERAL)
        );
    }

    /**
     * @dataProvider inputProvider
     */
    public function testTokenizesInput($input, $type)
    {
        $l = new Lexer();
        $l->setInput($input);
        $tokens = iterator_to_array($l->getIterator());
        $this->assertEquals($tokens[0]['type'], $type);
    }

    public function testTokenizesJavasriptLiterals()
    {
        $l = new Lexer();
        $l->setInput('_null, _false, _true, _"abc", _"ab\\"c"');
        $tokens =  $l->getTokens();
        $this->assertNull($tokens[0]['value']);
        $this->assertFalse($tokens[2]['value']);
        $this->assertTrue($tokens[4]['value']);
        $this->assertEquals('abc', $tokens[6]['value']);
        $this->assertEquals('ab"c', $tokens[8]['value']);
    }
}
