<?php

namespace JamesPath\Tests;

use JamesPath\Lexer;
use JamesPath\Parser;

class ComplianceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider complianceProvider
     */
    public function testPassesCompliance($data, $expression, $result)
    {
        // Fix the old "or" syntax
        $expression = str_replace(' or ', ' || ', $expression);

        $ast = Parser::compile($expression);
        $parsed = Parser::search($expression, $data);
        $this->assertEquals(
            $result,
            $parsed,
            $expression . "\n\n" . var_export($data, true) . "\n\n" . var_export($parsed, true) . "\n\n" . $ast
        );
    }

    public function complianceProvider()
    {
        $cases = array();

        foreach (array('basic', 'indices', 'ormatch', 'wildcard') as $name) {
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
