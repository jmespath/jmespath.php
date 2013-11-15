<?php

require 'vendor/autoload.php';

use JmesPath\Lexer;
use JmesPath\Parser;
use JmesPath\Interpreter;

$expression = $argv[1];
$data = json_decode(stream_get_contents(STDIN), true);

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

echo sprintf("Result\n======\n\n%s\n\n", json_encode($result, JSON_PRETTY_PRINT));
echo "Time\n====\n\n";
echo "Parse time:     {$parseTime} ms\n";
echo "Interpret time: {$interpretTime} ms\n";
echo "Total time:     " . ($parseTime + $interpretTime) . " ms\n\n";
