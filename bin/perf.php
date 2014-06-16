#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use JmesPath\Runtime\RuntimeInterface;

$runtime = \JmesPath\envRuntime();

if (!isset($_SERVER['CACHE'])) {
    $_SERVER['CACHE'] = false;
}

if (isset($argv[1])) {
    $dir = $argv[1];
} else {
    $dir = __DIR__ . '/../tests/compliance/perf';
}

is_dir($dir) or die('Dir not found: ' . $dir);

// Warm up the runner
$runtime->search('abcdefg', array());

$total = 0;
foreach (glob($dir . '/*.json') as $file) {
    if (!strpos($file, 'syntax')) {
        $total += runSuite($file, $runtime);
    }
}

echo "\nTotal time: {$total}ms\n";

function runSuite($file, RuntimeInterface $runtime)
{
    $contents = file_get_contents($file);
    $json = json_decode($contents, true);
    $total = 0;
    foreach ($json as $suite) {
        foreach ($suite['cases'] as $case) {
            $total += runCase(
                str_replace(getcwd(), '.', $file),
                $suite['given'],
                $case['expression'],
                $runtime
            );
        }
    }

    return $total;
}

function runCase(
    $file,
    $given,
    $expression,
    RuntimeInterface $runtime
) {
    $best = 99999;

    for ($i = 0; $i < 1000; $i++) {
        if (!$_SERVER['CACHE']) {
            $runtime->clearCache();
        }
        try {
            $t = microtime(true);
            $runtime->search($expression, $given);
            $tryTime = (microtime(true) - $t) * 1000;
        } catch (\Exception $e) {
            // Failure test cases shouldn't be tested
            return 0;
        }
        if ($tryTime < $best) {
            $best = $tryTime;
        }
    }

    $template = "time: %fms, description: %s, name: %s\n";
    $expression = str_replace("\n", '\n', $expression);
    printf($template, $best, $file, $expression);

    return $best;
}
