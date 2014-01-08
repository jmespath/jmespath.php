<?php

namespace JmesPath\Tests;

use JmesPath\Lexer;
use JmesPath\SyntaxErrorException;
use JmesPath\TokenStream;

/**
 * @covers JmesPath\Lexer
 */
class LexerTest extends \PHPUnit_Framework_TestCase
{
    public function testValidatesClosedQuotes()
    {
        $l = new Lexer();
        try {
            $l->tokenize('"foo"."baz');
            $this->fail('Did not throw');
        } catch (SyntaxErrorException $e) {
            $expected = <<<EOT
Syntax error at character 6
"foo"."baz
      ^
Unclosed quote
EOT;
            $this->assertContains($expected, $e->getMessage());
        }
    }

    public function testValidatesLiteralValuesAreSet()
    {
        $l = new Lexer();
        try {
            $l->tokenize('``');
            $this->fail('Did not throw');
        } catch (SyntaxErrorException $e) {
            $expected = <<<EOT
Syntax error at character 0
``
^
Empty JSON literal
EOT;
            $this->assertContains($expected, $e->getMessage());
        }
    }

    public function testValidatesLiteralValuesAreClosed()
    {
        $l = new Lexer();
        try {
            $l->tokenize('`{abc');
            $this->fail('Did not throw');
        } catch (SyntaxErrorException $e) {
            $expected = <<<EOT
Syntax error at character 0
`{abc
^
Unclosed JSON literal
EOT;
            $this->assertContains($expected, $e->getMessage());
        }
    }

    public function testValidatesLiteralValues()
    {
        $l = new Lexer();
        try {
            $l->tokenize('`{abc{}`');
            $this->fail('Did not throw');
        } catch (SyntaxErrorException $e) {
            $expected = <<<EOT
Syntax error at character 7
`{abc{}`
       ^
Error decoding JSON: (4) JSON_ERROR_SYNTAX - Syntax error, malformed JSON, given "{abc{}"
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
            array('`true`', Lexer::T_LITERAL),
            array('`false`', Lexer::T_LITERAL),
            array('`null`', Lexer::T_LITERAL),
            array('`"true"`', Lexer::T_LITERAL)
        );
    }

    /**
     * @dataProvider inputProvider
     */
    public function testTokenizesInput($input, $type)
    {
        $l = new Lexer();
        $tokens = $this->tokenArray($l->tokenize($input));
        $this->assertEquals($tokens[0]['type'], $type);
    }

    public function testTokenizesJavasriptLiterals()
    {
        $l = new Lexer();
        $tokens = $this->tokenArray($l->tokenize('`null`, `false`, `true`, `"abc"`, `"ab\\"c"`, `0`, `0.45`, `-0.5`'));
        $this->assertNull($tokens[0]['value']);
        $this->assertFalse($tokens[2]['value']);
        $this->assertTrue($tokens[4]['value']);
        $this->assertEquals('abc', $tokens[6]['value']);
        $this->assertEquals('ab"c', $tokens[8]['value']);
        $this->assertSame(0, $tokens[10]['value']);
        $this->assertSame(0.45, $tokens[12]['value']);
        $this->assertSame(-0.5, $tokens[14]['value']);
    }

    private function tokenArray(TokenStream $tokens)
    {
        $result = array();

        do {
            $result[] = $tokens->token;
            $tokens->next();
        } while ($tokens->token['type'] != Lexer::T_EOF);

        return $result;
    }
}
