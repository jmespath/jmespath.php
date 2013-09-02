<?php

namespace JmesPath;

/**
 * Assembler that parses tokens from a lexer into opcodes
 */
class Parser
{
    /** @var Lexer */
    private $lexer;

    /** @var \ArrayIterator */
    private $tokens;

    /** @var array opcode stack*/
    private $stack;

    /** @var array Known opcodes of the parser */
    private $methods;

    /** @var array Default lexical tokens to expect */
    private static $nextExpr = [
        Lexer::T_DOT => true,
        Lexer::T_EOF => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_LBRACE => true,
        Lexer::T_OR => true
    ];

    /** @var array First acceptable token */
    private static $firstTokens = [
        Lexer::T_IDENTIFIER => true,
        Lexer::T_STAR => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_LBRACE => true,
        Lexer::T_EOF => true
    ];

    /**
     * @param Lexer $lexer Lexer used to tokenize paths
     */
    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
        $this->methods = array_fill_keys(get_class_methods($this), true);
    }

    /**
     * Compile a JmesPath expression into an array of opcodes
     *
     * @param string $path Path to parse
     *
     * @return array
     * @throws SyntaxErrorException
     */
    public function compile($path)
    {
        $this->stack = [];
        $this->lexer->setInput($path);
        $this->tokens = $this->lexer->getIterator();

        // Ensure that the first token is valid
        if (!isset(self::$firstTokens[$this->tokens->current()['type']])) {
            throw new SyntaxErrorException(
                self::$firstTokens,
                $this->tokens->current(),
                $this->lexer->getInput()
            );
        }

        $this->extractTokens();

        return $this->stack;
    }

    private function extractTokens()
    {
        $token = $this->tokens->current();
        while ($token['type'] !== Lexer::T_EOF) {
            $method = 'parse_' . $token['type'];
            if (!isset($this->methods[$method])) {
                throw new \RuntimeException('Invalid token: ' . $token['type']);
            }
            $token = $this->{$method}($token);
        }
    }

    private function parse_T_IDENTIFIER(array $token)
    {
        $this->stack[] = ['field', $token['value']];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_LBRACKET(array $token)
    {
        static $expectedClosingBracket = [Lexer::T_RBRACKET => true];
        static $expectedAfterNumber = [Lexer::T_RBRACKET => true, Lexer::T_COMMA => true];
        static $expectedAfterOpenBracket = [
            Lexer::T_NUMBER => true,
            Lexer::T_STAR   => true,
            Lexer::T_COMMA  => true
        ];

        $nextToken = $this->match($expectedAfterOpenBracket);
        if ($nextToken['type'] == Lexer::T_NUMBER) {
            $value = $nextToken['value'];
            $nextToken = $this->match($expectedAfterNumber);
            if ($nextToken['type'] == Lexer::T_RBRACKET) {
                // A simple index extraction
                $this->stack[] = ['index', $value];
            } else {
                // A multi index extraction
                $this->stack[] = ['push', $value];
                while ($nextToken['type'] == Lexer::T_COMMA) {
                    $nextToken = $this->match([Lexer::T_NUMBER => true]);
                    $this->stack[] = ['push', $nextToken['value']];
                    $nextToken = $this->match([Lexer::T_COMMA => true, Lexer::T_RBRACKET => true]);
                }
                $this->stack[] = ['mindex'];
            }
        } elseif ($nextToken['type'] == Lexer::T_STAR) {
            $this->stack[] = ['star'];
            $this->match($expectedClosingBracket);
        }

        return $this->match(self::$nextExpr);
    }

    private function parse_T_NUMBER(array $token)
    {
        $this->stack[] = ['field', $token['value']];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_OR(array $token)
    {
        $this->stack[] = ['or'];

        return $this->match(self::$firstTokens);
    }

    private function parse_T_STAR(array $token)
    {
        $this->stack[] = ['star'];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_DOT(array $token)
    {
        static $expectedAfterDot = [
            Lexer::T_IDENTIFIER => true,
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true,
            Lexer::T_LBRACE => true
        ];

        return $this->match($expectedAfterDot);
    }

    private function parse_T_LBRACE(array $token)
    {
        static $expectedAfterField = [Lexer::T_RBRACE => true, Lexer::T_COMMA => true];
        static $expectedAfterOpenBrace = [
            Lexer::T_NUMBER     => true,
            Lexer::T_IDENTIFIER => true
        ];

        $nextToken = $this->match($expectedAfterOpenBrace);
        $this->stack[] = ['push', $nextToken['value']];
        $nextToken = $this->match($expectedAfterField);

        while ($nextToken['type'] == Lexer::T_COMMA) {
            $nextToken = $this->match([Lexer::T_IDENTIFIER => true]);
            $this->stack[] = ['push', $nextToken['value']];
            $nextToken = $this->match([Lexer::T_COMMA => true, Lexer::T_RBRACE => true]);
        }
        $this->stack[] = ['mfield'];

        return $this->match(self::$nextExpr);
    }

    /**
     * Match the next token against one or more types
     *
     * @param array $types Type to match
     * @return array Returns a token map
     * @throws SyntaxErrorException
     */
    private function match(array $types)
    {
        $this->tokens->next();
        $token = $this->tokens->current();
        if (isset($types[$token['type']])) {
            return $token;
        }

        throw new SyntaxErrorException($types, $token, $this->lexer->getInput());
    }
}
