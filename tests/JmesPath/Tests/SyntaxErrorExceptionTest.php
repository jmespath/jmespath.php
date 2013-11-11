<?php

namespace JmesPath\Tests;

use JmesPath\Lexer;
use JmesPath\SyntaxErrorException;

/**
 * @covers JmesPath\SyntaxErrorException
 */
class SyntaxErrorExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testCreatesWithNoArray()
    {
        $e = new SyntaxErrorException(
            'Found comma',
            array('type' => Lexer::T_COMMA, 'pos' => 3, 'value' => ','),
            'abc,def'
        );
        $expected = <<<EOT
Syntax error at character 3
abc,def
   ^
Found comma
EOT;
        $this->assertContains($expected, $e->getMessage());
    }

    public function testCreatesWithArray()
    {
        $e = new SyntaxErrorException(
            [Lexer::T_DOT => true, Lexer::T_EOF => true],
            array('type' => Lexer::T_COMMA, 'pos' => 3, 'value' => ','),
            'abc,def'
        );
        $expected = <<<EOT
Syntax error at character 3
abc,def
   ^
Expected T_DOT or T_EOF; found T_COMMA ","
EOT;
        $this->assertContains($expected, $e->getMessage());
    }
}
