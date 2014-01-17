#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use JmesPath\Lexer;
use JmesPath\Parser;
use JmesPath\Tree\TreeInterpreter;

$dir = !isset($argv[1]) ? __DIR__ . '/tests/JmesPath/compliance' : $argv[1];
is_dir($dir) or die('Dir not found: ' . $dir);
$files = glob($dir . '/*.json');
$parser = new Parser(new Lexer());
$interpreter = new TreeInterpreter();
$total = 0;

// Warm up the runner
$interpreter->visit($parser->compile('foo.bar'), array('foo' => array('bar' => 1)));

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
                str_replace(getcwd(), '.', $file),
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
    TreeInterpreter $interpreter
) {
    $bestParse = 99999;
    $bestInterpret = 99999;

    for ($i = 0; $i < 1000; $i++) {
        $t = microtime(true);
        try {
            $opcodes = $parser->compile($expression);
            $parseTime = (microtime(true) - $t) * 1000;
            $t = microtime(true);
            $interpreter->visit($opcodes, $given);
            $interpretTime = (microtime(true) - $t) * 1000;
        } catch (\Exception $e) {
            $parseTime = (microtime(true) - $t) * 1000;
            $interpretTime = 0;
        }
        if ($parseTime < $bestParse) {
            $bestParse = $parseTime;
        }
        if ($interpretTime < $bestInterpret) {
            $bestInterpret = $interpretTime;
        }
    }

    $template = "parse_time: %fms, search_time: %fms description: %s name: %s\n";
    $expression = str_replace("\n", '\n', $expression);
    printf($template, $bestParse, $bestInterpret, $file, $expression);

    return $bestInterpret + $bestParse;
}
