<?php

namespace JamesPath;

/**
 * Assembler that parses tokens from a lexer into opcodes
 */
class Parser
{
    /** @var Lexer */
    private $lexer;

    /** @var array opcode stack*/
    private $stack;

    /** @var array Known opcodes of the parser */
    private $methods;

    /** @var array Default lexical tokens to expect */
    private static $nextExpr = [
        Lexer::T_DOT => true,
        Lexer::T_EOF => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_OR => true
    ];

    /** @var array First acceptable token */
    private static $firstTokens = [
        Lexer::T_IDENTIFIER => true,
        Lexer::T_STAR => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_EOF => true
    ];

    /**
     * @param Lexer $lexer Lexer used to tokenize paths
     */
    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
        $this->methods = array_flip(get_class_methods($this));
    }

    /**
     * Compile a JamesPath expression into an array of opcodes
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

        // Ensure that the first token is valid
        if (!isset(self::$firstTokens[$this->lexer->current()->type])) {
            throw new SyntaxErrorException(self::$firstTokens, $this->lexer->current(), $this->lexer);
        }

        $this->extractTokens();

        return $this->stack;
    }

    private function extractTokens()
    {
        $token = $this->lexer->current();
        while ($token->type !== Lexer::T_EOF) {
            $method = 'parse_' . $token->type;
            if (!isset($this->methods[$method])) {
                throw new \RuntimeException('Invalid token: ' . $token->type);
            }
            $token = $this->{$method}($token);
        }
    }

    private function parse_T_IDENTIFIER(Token $token)
    {
        $this->stack[] = ['push', $token->value];
        $this->stack[] = ['op', 'field'];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_LBRACKET(Token $token)
    {
        static $expectedAfterOpenBracket = [Lexer::T_NUMBER => true, Lexer::T_STAR => true];
        static $expectedClosingBracket = [Lexer::T_RBRACKET => true];

        $next = $this->match($expectedAfterOpenBracket);
        if ($next->type == Lexer::T_STAR) {
            $this->stack[] = ['op', 'star'];
        } else {
            $this->stack[] = ['push', $next->value];
            $this->stack[] = ['op', 'index'];
        }
        $this->match($expectedClosingBracket);

        return $this->match(self::$nextExpr);
    }

    private function parse_T_NUMBER(Token $token)
    {
        $this->stack[] = ['push', $token->value];
        $this->stack[] = ['op', 'field'];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_OR(Token $token)
    {
        $this->stack[] = ['op', 'or'];

        return $this->match(self::$firstTokens);
    }

    private function parse_T_STAR(Token $token)
    {
        $this->stack[] = ['op', 'star'];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_DOT(Token $token)
    {
        static $expectedAfterDot = [
            Lexer::T_IDENTIFIER => true,
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true
        ];

        return $this->match($expectedAfterDot);
    }

    /**
     * Match the next token against one or more types
     *
     * @param array $types Type to match
     * @return Token
     * @throws SyntaxErrorException
     */
    private function match(array $types)
    {
        $this->lexer->next();
        $token = $this->lexer->current();
        if (!isset($types[$token->type])) {
            throw new SyntaxErrorException($types, $token, $this->lexer);
        }

        return $token;
    }
}
