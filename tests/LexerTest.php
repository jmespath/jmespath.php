<?php
namespace JmesPath\Tests;

use JmesPath\Lexer;
use JmesPath\SyntaxErrorException;
use PHPUnit\Framework\TestCase;

/**
 * @covers JmesPath\Lexer
 */
class LexerTest extends TestCase
{
    public static function inputProvider(): array
    {
        return [
            ['0', 'number'],
            ['1', 'number'],
            ['2', 'number'],
            ['3', 'number'],
            ['4', 'number'],
            ['5', 'number'],
            ['6', 'number'],
            ['7', 'number'],
            ['8', 'number'],
            ['9', 'number'],
            ['-1', 'number'],
            ['-1.5', 'number'],
            ['109.5', 'number'],
            ['.', 'dot'],
            ['{', 'lbrace'],
            ['}', 'rbrace'],
            ['[', 'lbracket'],
            [']', 'rbracket'],
            [':', 'colon'],
            [',', 'comma'],
            ['||', 'or'],
            ['*', 'star'],
            ['foo', 'identifier'],
            ['"foo"', 'quoted_identifier'],
            ['`true`', 'literal'],
            ['`false`', 'literal'],
            ['`null`', 'literal'],
            ['`"true"`', 'literal']
        ];
    }

    /**
     * @dataProvider inputProvider
     */
    public function testTokenizesInput(string $input, string $type): void
    {
        $l = new Lexer();
        $tokens = $l->tokenize($input);
        $this->assertEquals($tokens[0]['type'], $type);
    }

    public function testTokenizesJsonLiterals(): void
    {
        $l = new Lexer();
        $tokens = $l->tokenize('`null`, `false`, `true`, `"abc"`, `"ab\\"c"`,'
            . '`0`, `0.45`, `-0.5`');
        $this->assertNull($tokens[0]['value']);
        $this->assertFalse($tokens[2]['value']);
        $this->assertTrue($tokens[4]['value']);
        $this->assertEquals('abc', $tokens[6]['value']);
        $this->assertEquals('ab"c', $tokens[8]['value']);
        $this->assertSame(0, $tokens[10]['value']);
        $this->assertSame(0.45, $tokens[12]['value']);
        $this->assertSame(-0.5, $tokens[14]['value']);
    }

    public function testTokenizesJsonNumbers(): void
    {
        $l = new Lexer();
        $tokens = $l->tokenize('`10`, `1.2`, `-10.20e-10`, `1.2E+2`');
        $this->assertEquals(10, $tokens[0]['value']);
        $this->assertEquals(1.2, $tokens[2]['value']);
        $this->assertEquals(-1.02E-9, $tokens[4]['value']);
        $this->assertEquals(120, $tokens[6]['value']);
    }

    public function testCanWorkWithElidedJsonLiterals(): void
    {
        $l = new Lexer();
        $tokens = $l->tokenize('`foo`');
        $this->assertEquals('foo', $tokens[0]['value']);
        $this->assertEquals('literal', $tokens[0]['type']);
    }

    public function testHugeIndexLiteralIsUnknownInsteadOfClamped(): void
    {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('foo[999999999999999999999999999999999999999]');

        $this->assertSame('identifier', $tokens[0]['type']);
        $this->assertSame('lbracket', $tokens[1]['type']);
        $this->assertSame('unknown', $tokens[2]['type']);
    }

    public function testBareMinusIndexIsUnknown(): void
    {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('foo[-]');

        $this->assertSame('unknown', $tokens[2]['type']);
    }

    public function testIndexIntegerBoundariesAndLeadingZeros(): void
    {
        $lexer = new Lexer();

        $tokens = $lexer->tokenize('foo[' . PHP_INT_MAX . ']');
        $this->assertSame(PHP_INT_MAX, $tokens[2]['value']);

        $tokens = $lexer->tokenize('foo[' . PHP_INT_MIN . ']');
        $this->assertSame(PHP_INT_MIN, $tokens[2]['value']);

        $tokens = $lexer->tokenize('foo[' . PHP_INT_MAX . '0]');
        $this->assertSame('unknown', $tokens[2]['type']);

        $tokens = $lexer->tokenize('foo[' . PHP_INT_MIN . '0]');
        $this->assertSame('unknown', $tokens[2]['type']);

        $tokens = $lexer->tokenize('foo[0000000000000000000000007]');
        $this->assertSame(7, $tokens[2]['value']);

        $tokens = $lexer->tokenize('foo[-0]');
        $this->assertSame(0, $tokens[2]['value']);
    }
}
