<?php
namespace JmesPath\Tests;

use JmesPath\Runtime\AstRuntime;
use JmesPath\Runtime\CompilerRuntime;
use JmesPath\SyntaxErrorException;

class ComplianceTest extends \PHPUnit_Framework_TestCase
{
    private static $defaultRuntime;
    private static $compilerRuntime;

    public static function setUpBeforeClass()
    {
        self::$defaultRuntime = new AstRuntime();
        self::$compilerRuntime = new CompilerRuntime([
            'dir' => __DIR__ . '/../../compiled'
        ]);
        self::$compilerRuntime->clearCache();
    }

    public static function tearDownAfterClass()
    {
        self::$compilerRuntime->clearCache();
    }

    /**
     * @dataProvider complianceProvider
     */
    public function testPassesCompliance($data, $expression, $result, $error, $file, $suite, $case, $compiled)
    {
        $failed = $evalResult = $failureMsg = false;
        $debug = fopen('php://temp', 'r+');
        $compiledStr = '';

        try {
            if ($compiled) {
                $compiledStr = \JmesPath\Env::COMPILE_DIR . '=on ';
                $evalResult = self::$defaultRuntime->debug($expression, $data, $debug);
            } else {
                $evalResult = self::$compilerRuntime->debug($expression, $data, $debug);
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

        if (!$error && $failed) {
            $this->fail("Should not have failed\n{$failure}=> {$failed} {$failureMsg}");
        } elseif ($error && !$failed) {
            $this->fail("Should have failed\n{$failure}");
        }

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
            $json = json_decode($contents, true);
            foreach ($json as $suiteNumber => $suite) {
                foreach ($suite['cases'] as $caseNumber => $case) {
                    $caseData = array(
                        $suite['given'],
                        $case['expression'],
                        isset($case['result']) ? $case['result'] : null,
                        isset($case['error']) ? $case['error'] : false,
                        $name,
                        $suiteNumber,
                        $caseNumber,
                        false
                    );
                    $cases[] = $caseData;
                    $caseData[7] = true;
                    $cases[] = $caseData;
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
