<?php

namespace JamesPath;

/**
 * Syntax errors raise this exception that gives context
 */
class SyntaxErrorException extends \InvalidArgumentException
{
    public function __construct($expectedTypesOrMessage, Token $token, Lexer $lexer)
    {
        $message = "Syntax error at character {$token->position}\n"
            . $lexer->getInput() . "\n" . str_repeat(' ', $token->position) . "^\n";

        if (!is_array($expectedTypesOrMessage)) {
            $message .= $expectedTypesOrMessage;
        } else {
            $message .= sprintf(
                'Expected %s; found %s "%s"',
                implode(' or ', (array) $expectedTypesOrMessage),
                $token->type,
                $token->value
            );
        }

        parent::__construct($message);
    }
}
