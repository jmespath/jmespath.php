<?php
namespace JmesPath\Tests;

use JmesPath\Lexer;
use JmesPath\Parser;
use JmesPath\SyntaxErrorException;
use PHPUnit\Framework\TestCase;

/**
 * @covers JmesPath\Parser
 */
class ParserTest extends TestCase
{
    /**
     * @dataProvider invalidExpressionProvider
     */
    public function testHandlesInvalidExpressions(string $expr, string $msg): void
    {
        $p = new Parser(new Lexer());

        $this->expectException(SyntaxErrorException::class);
        $this->expectExceptionMessage($msg);

        $p->parse($expr);
    }

    public static function invalidExpressionProvider(): array
    {
        return [
            ['', 'Unexpected "eof" token'],
            ['.bar', 'Syntax error at character 0'],
            ['a,', 'Syntax error at character 1'],
            ['a.,', 'Syntax error at character 2'],
            ['=', 'Syntax error at character 0'],
            ['<', 'Syntax error at character 0'],
            ['>', 'Syntax error at character 0'],
            ['|', 'Syntax error at character 0'],
            ['@(foo)', 'Invalid function name'],
            ['`"not_a_function"`(@)', 'Invalid function name'],
            ["'not_a_function'(@)", 'Invalid function name'],
            ['@=', 'Did not reach the end of the token stream'],
            ['`1` `2`', 'Did not reach the end of the token stream'],
            ['{a: @', 'Syntax error at character 5'],
            ['foo[-]', 'Syntax error at character 4'],
            ['foo[999999999999999999999999999999999999999]', 'Syntax error at character 4']
        ];
    }

    /**
     * @dataProvider invalidLedTokenProvider
     */
    public function testInvalidExpressionsThrowCleanSyntaxErrors(string $expr): void
    {
        $diags = [];
        set_error_handler(function ($errno, $errstr) use (&$diags) {
            $diags[] = $errstr;
            return true;
        });

        try {
            try {
                (new Parser())->parse($expr);
                $this->fail("Expected SyntaxErrorException for: $expr");
            } catch (SyntaxErrorException $e) {
                $this->assertStringContainsString('Syntax error at character', $e->getMessage());
            }
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $diags, "PHP warnings/notices emitted while parsing: $expr");
    }

    public function testParsesIdentifierFunctionName(): void
    {
        $parser = new Parser();

        $this->assertSame(
            [
                'type'     => 'function',
                'value'    => 'length',
                'children' => [
                    ['type' => 'current'],
                ],
            ],
            $parser->parse('length(@)')
        );
    }

    public static function invalidLedTokenProvider(): array
    {
        return [
            ['avg([].size)+'],
            ['a = b'],
            ['@ `1`'],
            ['@``'],
            ['foo[]+'],
            ['foo#bar'],
            ['@ `"`'],
            ['+foo'],
            ['"foo'],
        ];
    }
}
