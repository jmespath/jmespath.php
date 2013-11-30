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
    public function testPassesCompliance($data, $expression, $result, $error, $file, $suite, $case)
    {
        // Fix the old "or" syntax
        $expression = str_replace(' or ', ' || ', $expression);

        $parsed = null;
        $failed = false;
        $parser = new Parser(new Lexer());
        $opcodes = $parser->compile($expression);
        $interpreter = new Interpreter();
        try {
            $parsed = $interpreter->execute($opcodes, $data);
        } catch (\Exception $e) {
            $failed = true;
        }

        $failure = "\nphp jp.php {$file} {$suite} {$case}\n"
            . "\n$expression\n"
            . "\n\nInput: " . $this->prettyJson($data)
            . "\n\nResult: " . $this->prettyJson($parsed)
            . "\n\nError: " . $error
            . "\n\nExpected: " . $this->prettyJson($result)
            . "\n\nopcodes: " . $this->prettyJson($opcodes);

        $this->assertEquals(
            $failed,
            $error,
            $failure
        );

        $this->assertEquals(
            $result,
            $parsed,
            $failure
        );
    }

    public function complianceProvider()
    {
        $cases = array();

        foreach (array(
            'basic',
            'indices',
            'ormatch',
            'wildcard',
            'escape',
            'multiselect',
            'functions'
        ) as $name) {
            $contents = file_get_contents(__DIR__ . "/compliance/{$name}.json");
            $json = json_decode($contents, true);
            foreach ($json as $suiteNumber => $suite) {
                foreach ($suite['cases'] as $caseNumber => $case) {
                    $cases[] = array(
                        $suite['given'],
                        $case['expression'],
                        isset($case['result']) ? $case['result'] : null,
                        isset($case['error']) ? $case['error'] : false,
                        $name,
                        $suiteNumber,
                        $caseNumber
                    );
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
