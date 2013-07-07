<?php

namespace JamesPath\Tests;

use JamesPath\Lexer;
use JamesPath\Parser;

class ComplianceTest extends \PHPUnit_Framework_TestCase
{
    public function basicTests()
    {
        return $this->loadTestCases('basic');
    }

    /**
     * @dataProvider basicTests
     */
    public function testPassesBasic($data, $expression, $result)
    {
        $this->doTest($data, $expression, $result);
    }

    public function indicesTests()
    {
        return $this->loadTestCases('indices');
    }

    /**
     * @dataProvider indicesTests
     */
    public function testPassesIndices($data, $expression, $result)
    {
        $this->doTest($data, $expression, $result);
    }

    public function orMatchTests()
    {
        return $this->loadTestCases('ormatch');
    }

    /**
     * @dataProvider orMatchTests
     */
    public function testPassesOrMatch($data, $expression, $result)
    {
        // Fix the old "or" syntax
        $expression = str_replace(' or ', ' || ', $expression);
        $this->doTest($data, $expression, $result);
    }

    public function wildcardTests()
    {
        return $this->loadTestCases('wildcard');
    }

    /**
     * @dataProvider wildcardTests
     */
    public function testPassesWildcard($data, $expression, $result)
    {
        $this->doTest($data, $expression, $result);
    }

    protected function doTest($data, $expression, $result)
    {
        $ast = Parser::compile($expression);
        $parsed = Parser::search($expression, $data);
        $this->assertEquals(
            $result,
            $parsed,
            $expression . "\n\n" . var_export($data, true) . "\n\n" . var_export($parsed, true) . "\n\n" . $ast
        );
    }

    protected function loadTestCases($name)
    {
        $contents = file_get_contents(__DIR__ . '/../../../vendor/boto/jmespath/tests/compliance/' . $name . '.json');
        $json = json_decode($contents, true);
        $cases = array();

        foreach ($json as $suite) {
            foreach ($suite['cases'] as $case) {
                $cases[] = array($suite['given'], $case['expression'], $case['result']);
            }
        }

        return $cases;
    }
}
