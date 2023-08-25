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
            ['|', 'Syntax error at character 0']
        ];
    }
}
