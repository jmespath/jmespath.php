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
            ['foo.`[1, 2]`', 'found literal "[1,2]"'],
            ['foo.`1e400`', 'found literal "INF"'],
            ['foo[`[1]`]', 'found literal "[1]"'],
            ['{`[1]`: a}', 'found literal "[1]"'],
            ['[0 `[1, 2]`]', 'found literal "[1,2]"'],
            ['=', 'Syntax error at character 0'],
            ['<', 'Syntax error at character 0'],
            ['>', 'Syntax error at character 0'],
            ['|', 'Syntax error at character 0'],
            ['@(foo)', 'Invalid function name'],
            ['`"not_a_function"`(@)', 'Invalid function name'],
            ["'not_a_function'(@)", 'Invalid function name'],
            ['@=', 'Did not reach the end of the token stream'],
            ['$foo = bar', 'Did not reach the end of the token stream'],
            ['$foo $bar', 'Did not reach the end of the token stream'],
            ['$', 'Syntax error at character 0'],
            ['$foo(@)', 'Invalid function name'],
            ['foo.$bar', 'found variable "bar"'],
            ['{$foo: bar}', 'found variable "foo"'],
            ['foo.let $a = bar in $a', 'A let expression is not allowed after a dot'],
            ['foo[*].let $a = bar in $a', 'A let expression is not allowed after a dot'],
            ['let $a bar in $a', 'Expected one of the following: assign; found identifier "bar"'],
            ['let $a = foo, bar in $a', 'Expected one of the following: variable; found identifier "bar"'],
            ['let $a = foo', 'Expected "in" after the let bindings'],
            ['let $a = foo in', 'Unexpected "eof" token'],
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

    public function testParsesLetExpression(): void
    {
        $parser = new Parser();

        $this->assertSame(
            [
                'type'     => 'let',
                'children' => [
                    [
                        'type'     => 'bindings',
                        'children' => [
                            [
                                'type'     => 'variable_binding',
                                'value'    => 'a',
                                'children' => [
                                    ['type' => 'field', 'value' => 'foo'],
                                ],
                            ],
                            [
                                'type'     => 'variable_binding',
                                'value'    => 'b',
                                'children' => [
                                    ['type' => 'current'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type'     => 'multi_select_list',
                        'children' => [
                            ['type' => 'variable', 'value' => 'a'],
                            ['type' => 'variable', 'value' => 'b'],
                        ],
                    ],
                ],
            ],
            $parser->parse('let $a = foo, $b = @ in [$a, $b]')
        );
    }

    public static function invalidLedTokenProvider(): array
    {
        return [
            ['avg([].size)+'],
            ['a = b'],
            ['a[?b = c]'],
            ['let $ = a in @'],
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
