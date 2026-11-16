<?php
namespace JmesPath\Tests;

use JmesPath\SyntaxErrorException;
use PHPUnit\Framework\TestCase;

/**
 * @covers JmesPath\SyntaxErrorException
 */
class SyntaxErrorExceptionTest extends TestCase
{
    public function testCreatesWithNoArray(): void
    {
        $e = new SyntaxErrorException(
            'Found comma',
            ['type' => 'comma', 'pos' => 3, 'value' => ','],
            'abc,def'
        );
        $expected = <<<EOT
Syntax error at character 3
abc,def
   ^
Found comma
EOT;
        $this->assertStringContainsString($expected, $e->getMessage());
    }

    public function testCreatesWithArray(): void
    {
        $e = new SyntaxErrorException(
            ['dot' => true, 'eof' => true],
            ['type' => 'comma', 'pos' => 3, 'value' => ','],
            'abc,def'
        );
        $expected = <<<EOT
Syntax error at character 3
abc,def
   ^
Expected one of the following: dot, eof; found comma ","
EOT;
        $this->assertStringContainsString($expected, $e->getMessage());
    }

    /**
     * @dataProvider literalValueProvider
     */
    public function testCreatesWithLiteralToken($value, string $found): void
    {
        $e = new SyntaxErrorException(
            ['dot' => true, 'eof' => true],
            ['type' => 'literal', 'pos' => 3, 'value' => $value],
            'abc`def`'
        );

        $this->assertStringContainsString("; found literal \"{$found}\"", $e->getMessage());
    }

    public static function literalValueProvider(): array
    {
        return [
            ['def', 'def'],
            [[1, 2], '[1,2]'],
            [['a' => 1], '{"a":1}'],
            [true, 'true'],
            [false, 'false'],
            [null, 'null'],
        ];
    }

    public function testDefaultsMissingTokenKeysToEndOfExpression(): void
    {
        $e = new SyntaxErrorException(['comma' => true], ['type' => 'eof'], 'abc');

        $this->assertStringContainsString('Syntax error at character 3', $e->getMessage());
    }
}
