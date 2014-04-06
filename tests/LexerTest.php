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
            array('0', 'number'),
            array('1', 'number'),
            array('2', 'number'),
            array('3', 'number'),
            array('4', 'number'),
            array('5', 'number'),
            array('6', 'number'),
            array('7', 'number'),
            array('8', 'number'),
            array('9', 'number'),
            array('-1', 'number'),
            array('-1.5', 'number'),
            array('109.5', 'number'),
            array('.', 'dot'),
            array('{', 'lbrace'),
            array('}', 'rbrace'),
            array('[', 'lbracket'),
            array(']', 'rbracket'),
            array(':', 'colon'),
            array(',', 'comma'),
            array('||', 'or'),
            array('*', 'star'),
            array('foo', 'identifier'),
            array('"foo"', 'quoted_identifier'),
            array('`true`', 'literal'),
            array('`false`', 'literal'),
            array('`null`', 'literal'),
            array('`"true"`', 'literal')
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
        $tokens->next();

        do {
            $result[] = $tokens->token;
            $tokens->next();
        } while ($tokens->token['type'] != 'eof');

        return $result;
    }
}
