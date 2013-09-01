<?php

namespace JmesPath;

/**
 * Syntax errors raise this exception that gives context
 */
class SyntaxErrorException extends \InvalidArgumentException
{
    public function __construct($expectedTypesOrMessage, array $token, Lexer $lexer)
    {
        $message = "Syntax error at character {$token['pos']}\n"
            . $lexer->getInput() . "\n" . str_repeat(' ', $token['pos']) . "^\n";

        if (!is_array($expectedTypesOrMessage)) {
            $message .= $expectedTypesOrMessage;
        } else {
            $message .= sprintf(
                'Expected %s; found %s "%s"',
                implode(' or ', array_keys($expectedTypesOrMessage)),
                $token['type'],
                $token['value']
            );
        }

        parent::__construct($message);
    }
}
