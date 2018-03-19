<?php
namespace JmesPath\Tests;

use JmesPath\AstRuntime;
use JmesPath\CompilerRuntime;
use JmesPath\SyntaxErrorException;

/**
 * @covers JmesPath\Parser
 */
class ArithmeticTest extends \PHPUnit_Framework_TestCase
{
    public function testArithmetic()
    {
        $given = json_decode('{"foo": {"bar": {"baz": 3}}}', true);
        $given = json_decode('{"foo": {"bar": 1, "baz": 3} }', true);
        $expression = 'foo.bar + foo.baz + foo.baz';
        // $result = json_decode('{"baz": "correct"}', true);
        
        try {
            $runtime = new AstRuntime();
            $evalResult = $runtime($expression, $given);
            var_dump($evalResult);

        } catch (\Exception $e) {
            $failed = $e instanceof SyntaxErrorException ? 'syntax' : 'runtime';
            $failureMsg = sprintf(
                '%s (%s line %d)',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            echo $failureMsg;
        }
    }
}