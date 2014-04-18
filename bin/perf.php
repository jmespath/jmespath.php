#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use JmesPath\Runtime\RuntimeInterface;

$runtimeArgs = $extra = array();
for ($i = 1, $t = count($argv); $i < $t; $i++) {
    $arg = $argv[$i];
    if ($arg == '--compile') {
        $runtimeArgs['compile'] = true;
        $_SERVER['jp_compile'] = true;
    } elseif ($arg == '--cache') {
        $_SERVER['jp_cache'] = true;
    } else {
        $extra[] = $arg;
    }
}

$dir = __DIR__ . '/../tests/compliance/perf';
if (count($extra)) {
    if (count($extra) > 1 || strpos($extra[0], '--') === 0) {
        die("perf.php [--compile] [--cache] [script_directory]\n\n");
    } else {
        $dir = $extra[0];
    }
}

is_dir($dir) or die('Dir not found: ' . $dir);
$runtime = \JmesPath\createRuntime($runtimeArgs);

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
        if (!$_SERVER['jp_cache']) {
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
