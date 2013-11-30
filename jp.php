<?php

require 'vendor/autoload.php';

use JmesPath\Lexer;
use JmesPath\Parser;
use JmesPath\Interpreter;

if (!isset($argv[2])) {
    // Simple expression extraction
    $expression = $argv[1];
    $data = json_decode(stream_get_contents(STDIN), true);
} elseif (!isset($argv[3])) {
    die('Must specify an expression OR a jmespath compliance script, test suite, and test case');
} else {
    // Manually run a compliance test
    $suite = $argv[1];
    $outer = $argv[2];
    $inner = $argv[3];
    $path = __DIR__ . "/tests/JmesPath/compliance/{$suite}.json";
    if (!file_exists($path)) {
        die('File not found at ' . $path);
    }
    $json = json_decode(file_get_contents($path), true);
    $set = $json[$outer];
    $data = $set['given'];
    $expression = $set['cases'][$inner]['expression'];
}

echo "Expression\n==========\n\n{$expression}\n\n";

$lexer = new Lexer();
$lexer->setInput($expression);
echo "Tokens\n======\n\n";
foreach ($lexer as $token) {
    echo str_pad($token['pos'], 3, ' ', STR_PAD_LEFT) . '   ';
    echo str_pad($token['type'], 15, ' ') . '   ';
    echo $token['value'] . "\n";
}
echo "\n";

$parser = new Parser($lexer);
$interpreter = new Interpreter(true);

$t = microtime(true);
$opcodes = $parser->compile($expression);
$parseTime = (microtime(true) - $t) * 1000;

$t = microtime(true);
$result = $interpreter->execute($opcodes, $data);
$interpretTime = (microtime(true) - $t) * 1000;

if (defined('JSON_PRETTY_PRINT')) {
    $jsonData = json_encode($result, JSON_PRETTY_PRINT);
} else {
    $jsonData = json_encode($result);
}

echo sprintf("Result\n======\n\n%s\n\n", $jsonData);
echo "Time\n====\n\n";
echo "Parse time:     {$parseTime} ms\n";
echo "Interpret time: {$interpretTime} ms\n";
echo "Total time:     " . ($parseTime + $interpretTime) . " ms\n\n";
