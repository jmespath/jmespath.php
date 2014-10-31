<?php
namespace JmesPath\Tests;

use JmesPath\AstRuntime;
use JmesPath\CompilerRuntime;
use JmesPath\SyntaxErrorException;

class ComplianceTest extends \PHPUnit_Framework_TestCase
{
    private static $defaultRuntime;
    private static $compilerRuntime;

    public static function setUpBeforeClass()
    {
        $dir = __DIR__ . '/../../compiled';
        self::$defaultRuntime = new AstRuntime();
        self::$compilerRuntime = new CompilerRuntime($dir);
        array_map('unlink', glob($dir . '/jmespath_*.php'));
    }

    public static function tearDownAfterClass()
    {
        $dir = __DIR__ . '/../../compiled';
        array_map('unlink', glob($dir . '/jmespath_*.php'));
    }

    /**
     * @dataProvider complianceProvider
     */
    public function testPassesCompliance($data, $expression, $result, $error, $file, $suite, $case, $compiled, $asAssoc)
    {
        $failed = $evalResult = $failureMsg = false;
        $debug = fopen('php://temp', 'r+');
        $compiledStr = '';

        try {
            if ($compiled) {
                $compiledStr = \JmesPath\Env::COMPILE_DIR . '=on ';
                $fn = self::$defaultRuntime;
                $evalResult = $fn($expression, $data, $debug);
            } else {
                $fn = self::$compilerRuntime;
                $evalResult = $fn($expression, $data, $debug);
            }
        } catch (\Exception $e) {
            $failed = $e instanceof SyntaxErrorException ? 'syntax' : 'runtime';
            $failureMsg = sprintf('%s (%s line %d)', $e->getMessage(), $e->getFile(), $e->getLine());
        }

        rewind($debug);
        $file = __DIR__ . '/compliance/' . $file . '.json';
        $failure = "\n{$compiledStr}php bin/jp.php --file {$file} --suite {$suite} --case {$case}\n\n"
            . stream_get_contents($debug) . "\n\n"
            . "Expected: " . $this->prettyJson($result) . "\n\n";
        $failure .= 'Associative? ' . var_export($asAssoc, true) . "\n\n";

        if (!$error && $failed) {
            $this->fail("Should not have failed\n{$failure}=> {$failed} {$failureMsg}");
        } elseif ($error && !$failed) {
            $this->fail("Should have failed\n{$failure}");
        }

        $result = $this->convertAssoc($result);
        $evalResult = $this->convertAssoc($evalResult);
        $this->assertEquals($result, $evalResult, $failure);
    }

    public function complianceProvider()
    {
        $cases = array();

        $files = array_map(function ($f) {
            return basename($f, '.json');
        }, glob(__DIR__ . '/compliance/*.json'));

        foreach ($files as $name) {
            $contents = file_get_contents(__DIR__ . "/compliance/{$name}.json");
            foreach ([true, false] as $asAssoc) {
                $json = json_decode($contents, true);
                $jsonObj = json_decode($contents);
                foreach ($json as $suiteNumber => $suite) {
                    $given = $asAssoc ? $suite['given'] : $jsonObj[$suiteNumber]->given;
                    foreach ($suite['cases'] as $caseNumber => $case) {
                        $caseData = [
                            $given,
                            $case['expression'],
                            isset($case['result']) ? $case['result'] : null,
                            isset($case['error']) ? $case['error'] : false,
                            $name,
                            $suiteNumber,
                            $caseNumber,
                            false,
                            $asAssoc
                        ];
                        $cases[] = $caseData;
                        $caseData[7] = true;
                        $cases[] = $caseData;
                    }
                }
            }
        }

        return $cases;
    }

    private function convertAssoc($data)
    {
        if ($data instanceof \stdClass) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->convertAssoc($value);
            }
        }

        return $data;
    }

    private function prettyJson($json)
    {
        if (defined('JSON_PRETTY_PRINT')) {
            return json_encode($json, JSON_PRETTY_PRINT);
        }

        return json_encode($json);
    }
}
