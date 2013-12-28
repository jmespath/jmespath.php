#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

if (!isset($argv[2])) {
    // Simple expression extraction
    $expression = $argv[1];
    $data = json_decode(stream_get_contents(STDIN), true);
} elseif (!isset($argv[3])) {
    die("Must specify an expression OR a jmespath compliance script, test suite, and test case\n");
} else {
    // Manually run a compliance test
    $path = __DIR__ . "/tests/JmesPath/compliance/{$argv[1]}.json";
    file_exists($path) or die('File not found at ' . $path);
    $json = json_decode(file_get_contents($path), true);
    $set = $json[$argv[2]];
    $data = $set['given'];
    $expression = $set['cases'][$argv[3]]['expression'];
}

JmesPath\debugSearch($expression, $data);
