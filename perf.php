#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use JmesPath\Lexer;
use JmesPath\Parser;
use JmesPath\Interpreter;

$dir = !isset($argv[1]) ? __DIR__ . '/tests/JmesPath/compliance' : $argv[1];
is_dir($dir) or die('Dir not found: ' . $dir);
$files = glob($dir . '/*.json');
$parser = new Parser(new Lexer());
$interpreter = new Interpreter();
$total = 0;

foreach ($files as $file) {
    if (!strpos($file, 'syntax')) {
        $total += runSuite($parser, $interpreter, $file);
    }
}

echo "\nTotal time: {$total}ms\n";

function runSuite($parser, $interpreter, $file)
{
    $contents = file_get_contents($file);
    $json = json_decode($contents, true);
    $total = 0;
    foreach ($json as $suite) {
        foreach ($suite['cases'] as $case) {
            $total += runCase(
                $file,
                $suite['given'],
                $case['expression'],
                $parser,
                $interpreter
            );
        }
    }

    return $total;
}

function runCase(
    $file,
    $given,
    $expression,
    Parser $parser,
    Interpreter $interpreter
) {
    $t = microtime(true);

    try {
        $opcodes = $parser->compile($expression);
        $parseTime = (microtime(true) - $t) * 1000;
        $t = microtime(true);
        $interpreter->execute($opcodes, $given);
        $interpretTime = (microtime(true) - $t) * 1000;
    } catch (\Exception $e) {
        $parseTime = (microtime(true) - $t) * 1000;
        $interpretTime = '0';
    }

    $expression = str_replace("\n", '\n', $expression);
    $template = "parse_time: %fms, search_time: %fms description: %s name: %s\n";
    printf($template, $parseTime, $interpretTime, $file, $expression);

    return $parseTime + $interpretTime;
}
