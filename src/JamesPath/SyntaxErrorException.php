<?php

namespace JamesPath;

class SyntaxErrorException extends \InvalidArgumentException
{
    public function __construct($expectedTypes, Token $token, Lexer $lexer)
    {
        $message = "Syntax error at character {$token->position}\n"
            . $lexer->getInput() . "\n" . str_repeat(' ', $token->position) . "^\n"
            . sprintf('Expected %s; found %s "%s"',
            implode(' or ', array_map(function ($t) use ($lexer) {
                return $lexer->getTokenName($t);
            }, (array) $expectedTypes)),
            $lexer->getTokenName($token->type),
            $token->value
        );

        parent::__construct($message);
    }
}
