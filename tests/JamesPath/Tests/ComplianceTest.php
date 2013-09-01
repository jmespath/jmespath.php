<?php

namespace JamesPath\Tests;

use JamesPath\Parser;
use JamesPath\Interpreter;
use JamesPath\Lexer;

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
        $interpreter = new Interpreter($opcodes);
        $parsed = $interpreter->execute($data);

        $this->assertEquals(
            $result,
            $parsed,
            $expression . "\n\n" . var_export($data, true) . "\n\n" . var_export($parsed, true) . "\n\n" . var_export($opcodes, true)
        );
    }

    public function complianceProvider()
    {
        $cases = array();

        foreach (array('basic', 'indices', 'ormatch', 'wildcard', 'escape') as $name) {
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
