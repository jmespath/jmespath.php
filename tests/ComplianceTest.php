<?php
namespace JmesPath\Tests;

use JmesPath\AstRuntime;
use JmesPath\CompilerRuntime;
use JmesPath\SyntaxErrorException;
use PHPUnit\Framework\TestCase;

class ComplianceTest extends TestCase
{
    private static $path;

    public static function setUpBeforeClass(): void
    {
        self::$path = sys_get_temp_dir() . '/jmespath-compliance-compiled';
        if (!is_dir(self::$path)) {
            mkdir(self::$path, 0777, true);
        }
        array_map('unlink', glob(self::$path . '/jmespath_*.php') ?: []);
    }

    public static function tearDownAfterClass(): void
    {
        array_map('unlink', glob(self::$path . '/jmespath_*.php') ?: []);
        @rmdir(self::$path);
    }

    /**
     * @dataProvider complianceProvider
     */
    public function testPassesCompliance(
        $data,
        $expression,
        $result,
        $error,
        $file,
        $suite,
        $case,
        $compiled,
        $asAssoc
    ): void {
        $evalResult = null;
        $failed = false;
        $failureMsg = '';
        $failure = '';
        $compiledStr = '';

        try {
            if ($compiled) {
                $compiledStr = \JmesPath\Env::COMPILE_DIR . '=' . self::$path . ' ';
                $runtime = new CompilerRuntime(self::$path);
            } else {
                $runtime = new AstRuntime();
            }
            $evalResult = $runtime($expression, $data);
        } catch (\PHPUnit\Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            $failed = $e instanceof SyntaxErrorException ? 'syntax' : 'runtime';
            $failureMsg = sprintf(
                '%s (%s line %d)',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }

        $file = __DIR__ . '/compliance/' . $file . '.json';
        $failure .= "\n{$compiledStr}php bin/jp.php --file {$file} --suite {$suite} --case {$case}\n\n"
            . "Result: " . json_encode($evalResult, JSON_PRETTY_PRINT) . "\n\n"
            . "Expected: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
        $failure .= 'Associative? ' . var_export($asAssoc, true) . "\n\n";

        if (!$error && $failed) {
            $this->fail("Should not have failed\n{$failure}=> {$failed} {$failureMsg}");
        } elseif ($error && !$failed) {
            $this->fail("Should have failed\n{$failure}");
        } elseif ($error) {
            $expectedKind = $error === 'syntax' ? 'syntax' : 'runtime';
            if ($failed !== $expectedKind) {
                $this->fail("Expected {$expectedKind} ({$error}) but got {$failed}\n{$failure}");
            }
        }

        $this->assertEquals(
            $this->convertAssoc($result),
            $this->convertAssoc($evalResult),
            $failure
        );
    }

    public static function complianceProvider(): array
    {
        $cases = [];

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
            return $this->convertAssoc((array) $data);
        } elseif (is_array($data)) {
            return array_map([$this, 'convertAssoc'], $data);
        } else {
            return $data;
        }
    }
}
