<?php

namespace JmesPath\Tests;

use JmesPath\Parser;
use JmesPath\Interpreter;
use JmesPath\Lexer;
use JmesPath\SyntaxErrorException;

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
        $opcodes = array();
        $interpreter = new Interpreter();
        $parser = new Parser(new Lexer());

        try {
            $opcodes = $parser->compile($expression);
            $parsed = $interpreter->execute($opcodes, $data);
        } catch (SyntaxErrorException $e) {
            $failed = 'syntax';
        } catch (\RuntimeException $e) {
            $failed = 'runtime';
        }

        $failure = "\nphp jp.php {$file} {$suite} {$case}\n"
            . "\n$expression\n"
            . "\n\nInput: " . $this->prettyJson($data)
            . "\n\nResult: " . $this->prettyJson($parsed)
            . "\n\nError: " . $error
            . "\n\nExpected: " . $this->prettyJson($result)
            . "\n\nopcodes: " . $this->prettyJson($opcodes);

        if (!$error && $failed) {
            $this->fail("Should not have failed\n{$failure}");
        } elseif ($error && !$failed) {
            $this->fail("Should have failed\n{$failure}");
        }

        $this->assertEquals(
            $result,
            $parsed,
            $failure
        );
    }

    public function complianceProvider()
    {
        $cases = array();

        $files = array_map(function ($f) {
            return basename($f, '.json');
        }, glob(__DIR__ . '/compliance/*.json'));

        foreach ($files as $name) {
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
