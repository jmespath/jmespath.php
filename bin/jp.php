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
    $path = __DIR__ . "/../tests/JmesPath/compliance/{$argv[1]}.json";
    file_exists($path) or die('File not found at ' . $path);
    $json = json_decode(file_get_contents($path), true);
    $set = $json[$argv[2]];
    $data = $set['given'];
    $expression = $set['cases'][$argv[3]]['expression'];
    if (isset($argv[4])) {
        $expression = $argv[4];
    } else {
        echo "Expects\n=======\n";
        if (isset($set['cases'][$argv[3]]['result'])) {
            echo json_encode($set['cases'][$argv[3]]['result'], JSON_PRETTY_PRINT) . "\n\n";
        } elseif (isset($set['cases'][$argv[3]]['error'])) {
            echo "{$set['cases'][$argv[3]]['error']} error\n\n";
        } else {
            echo "No result?\n\n";
        }
    }
}

JmesPath\debugSearch($expression, $data);
