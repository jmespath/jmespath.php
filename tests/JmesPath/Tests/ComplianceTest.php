<?php

namespace JmesPath\Tests;

use JmesPath\Parser;
use JmesPath\Interpreter;
use JmesPath\Lexer;

class ComplianceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider complianceProvider
     */
    public function testPassesCompliance($data, $expression, $result)
    {
        // Fix the old "or" syntax
        $expression = str_replace(' or ', ' || ', $expression);

        $parser = new Parser(new Lexer());
        $opcodes = $parser->compile($expression);
        $interpreter = new Interpreter();
        $parsed = $interpreter->execute($opcodes, $data);

        $failure = $expression . "\n\nInput: "
            . json_encode($data, JSON_PRETTY_PRINT)
            . "\n\nResult: " . json_encode($data, JSON_PRETTY_PRINT)
            . "\n\nExpected: " . json_encode($result, JSON_PRETTY_PRINT)
            . "\n\nopcodes: " . json_encode($opcodes, JSON_PRETTY_PRINT);

        $this->assertEquals(
            $result,
            $parsed,
            $failure
        );
    }

    public function complianceProvider()
    {
        $cases = array();

        foreach (array('basic', 'indices', 'ormatch', 'wildcard', 'escape', 'multiselect') as $name) {
            $contents = file_get_contents(__DIR__ . "/../../../vendor/boto/jmespath/tests/compliance/{$name}.json");
            $json = json_decode($contents, true);
            foreach ($json as $suite) {
                foreach ($suite['cases'] as $case) {
                    $cases[] = array($suite['given'], $case['expression'], $case['result']);
                }
            }
        }

        return $cases;
    }
}
