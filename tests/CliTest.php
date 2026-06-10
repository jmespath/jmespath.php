<?php
namespace JmesPath\Tests;

use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public function testComplianceErrorCaseUsesFileOption(): void
    {
        $file = sys_get_temp_dir() . '/jmespath-cli-' . bin2hex(random_bytes(12)) . '.json';
        file_put_contents($file, json_encode([
            [
                'given' => ['foo' => 'bar'],
                'cases' => [
                    [
                        'expression' => 'foo',
                        'error' => 'syntax',
                    ],
                ],
            ],
        ]));

        try {
            $command = sprintf(
                '%s %s --file %s --suite 0 --case 0 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(__DIR__ . '/../bin/jp.php'),
                escapeshellarg($file)
            );
            exec($command, $output, $status);

            $this->assertSame(0, $status, implode("\n", $output));
            $output = implode("\n", $output);
            $this->assertStringContainsString('syntax error', $output);
            $this->assertStringNotContainsString('Undefined array key', $output);
        } finally {
            @unlink($file);
        }
    }

    public function testUsageMentionsFileOption(): void
    {
        $command = sprintf(
            '%s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../bin/jp.php')
        );
        exec($command, $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
        $output = implode("\n", $output);
        $this->assertStringContainsString('--file path_to_compliance_json', $output);
        $this->assertStringNotContainsString('--script', $output);
    }
}
