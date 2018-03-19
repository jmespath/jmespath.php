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
    public function inputProvider()
    {
        return array(
            array('foo.bar + foo.baz + foo.baz', 7),
            array('foo.bar + foo.baz - foo.baz', 1),
            // array('(foo.bar + foo.baz) - foo.baz', 7),
            array('foo.bar * foo.baz', 3),
            array('foo.baz / foo.bar', 3),
            array('foo.bar % foo.baz', 1),
        );
    }

    /**
     * @dataProvider inputProvider
     */
    public function testArithmetic($expression, $result)
    {
        $failure = '';
        $given = json_decode('{"foo": {"bar": 1, "baz": 3} }', true);
                
        try {
            $runtime = new AstRuntime();
            $evalResult = $runtime($expression, $given);
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

        $failure .= "\n --case {$expression}\n\n"
            . "Expected: " . $this->prettyJson($result) . "\n\n";

        $this->assertEquals(
            $this->convertAssoc($result),
            $this->convertAssoc($evalResult),
            $failure
        );
    }

    private function convertAssoc($data)
    {
        if ($data instanceof \stdClass) {
            return $this->convertAssoc((array) $data);
        } elseif (is_array($data)) {
            return array_map([$this, 'convertAssoc'], $data);
        } else {
            return $data;
        }
    }

    private function prettyJson($json)
    {
        if (defined('JSON_PRETTY_PRINT')) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }

        return json_encode($json);
    }
}