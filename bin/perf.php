#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

$dir = isset($argv[1]) ? $argv[1] : __DIR__ . '/../tests/compliance/perf';
is_dir($dir) or die('Dir not found: ' . $dir);
// Warm up the runner
\JmesPath\Env::search('foo', []);

$total = 0;
foreach (glob($dir . '/*.json') as $file) {
    $total += runSuite($file);
}
echo "\nTotal time: {$total}\n";

function runSuite($file)
{
    $contents = file_get_contents($file);
    $json = json_decode($contents, true);
    $total = 0;
    foreach ($json as $suite) {
        foreach ($suite['cases'] as $case) {
            $total += runCase(
                str_replace(getcwd(), '.', $file),
                $suite['given'],
                $case['expression']
            );
        }
    }
    return $total;
}

function runCase($file, $given, $expression)
{
    $best = 99999;
    $runtime = \JmesPath\Env::createRuntime();

    for ($i = 0; $i < 1000; $i++) {
        $t = microtime(true);
        $runtime($expression, $given);
        $tryTime = (microtime(true) - $t) * 1000;
        if ($tryTime < $best) {
            $best = $tryTime;
        }
        if (!getenv('CACHE')) {
            $runtime = \JmesPath\Env::createRuntime();
            // Delete compiled scripts if not caching.
            if ($runtime instanceof \JmesPath\CompilerRuntime) {
                array_map('unlink', glob(sys_get_temp_dir() . '/jmespath_*.php'));
            }
        }
    }

    $template = "time: %fms, %s: %s\n";
    $expression = str_replace("\n", '\n', $expression);
    printf($template, $best, basename($file), substr($expression, 0, 50));

    return $best;
}
