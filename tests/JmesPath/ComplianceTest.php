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
    public function testPassesCompliance($data, $expression, $result, $file, $suite, $case)
    {
        // Fix the old "or" syntax
        $expression = str_replace(' or ', ' || ', $expression);

        $parser = new Parser(new Lexer());
        $opcodes = $parser->compile($expression);
        $interpreter = new Interpreter();
        $parsed = $interpreter->execute($opcodes, $data);

        $failure = "\nphp jp.php {$file} {$suite} {$case}\n"
            . "\n$expression\n"
            . "\n\nInput: " . $this->prettyJson($data)
            . "\n\nResult: " . $this->prettyJson($parsed)
            . "\n\nExpected: " . $this->prettyJson($result)
            . "\n\nopcodes: " . $this->prettyJson($opcodes);

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
            $contents = file_get_contents(__DIR__ . "/../../vendor/boto/jmespath/tests/compliance/{$name}.json");
            $json = json_decode($contents, true);
            foreach ($json as $suiteNumber => $suite) {
                foreach ($suite['cases'] as $caseNumber => $case) {
                    $cases[] = array($suite['given'], $case['expression'], $case['result'], $name, $suiteNumber, $caseNumber);
                }
            }
        }

        return $cases;
    }

    private function prettyJson($json)
    {
        if (defined('JSON_PRETTY_PRINT')) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }

        return json_encode($json);
    }
}
