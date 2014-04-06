<?php

namespace JmesPath;

/**
 * Syntax errors raise this exception that gives context
 */
class SyntaxErrorException extends \InvalidArgumentException
{
    /**
     * @param string $expectedTypesOrMessage Expected array of tokens or message
     * @param array  $token                  Current token
     * @param string $expression             Expression input
     */
    public function __construct($expectedTypesOrMessage, array $token, $expression)
    {
        $message = "Syntax error at character {$token['pos']}\n"
            . $expression . "\n" . str_repeat(' ', $token['pos']) . "^\n";

        if (!is_array($expectedTypesOrMessage)) {
            $message .= $expectedTypesOrMessage;
        } else {
            $message .= sprintf(
                'Expected one of the following: %s; found %s "%s"',
                implode(', ', array_keys($expectedTypesOrMessage)),
                $token['type'],
                $token['value']
            );
        }

        parent::__construct($message);
    }
}
