<?php

namespace JamesPath;

/**
 * Uses a Lexer to generate an array of bytecode
 */
class BytecodeParser
{
    /** @var Lexer */
    private $lexer;

    /** @var array */
    private $stack = [];

    /** @var array */
    private $nextExpr = [Lexer::T_DOT, Lexer::T_EOF, Lexer::T_LBRACKET, Lexer::T_OR];

    /** @var array */
    private $methods;

    /**
     * @param Lexer $lexer Lexer used to tokenize paths
     */
    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
        $this->methods = array_flip(get_class_methods($this));
    }

    /**
     * Parse a JamesPath expression into an array of opcodes
     *
     * @param string $path Path to parse
     *
     * @return array
     */
    public function parse($path)
    {
        $this->lexer->setInput($path);
        $this->matchToken(
            $this->lexer->current(),
            [Lexer::T_IDENTIFIER, Lexer::T_STAR, Lexer::T_LBRACKET, Lexer::T_EOF]
        );
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

        return $this->match($this->nextExpr);
    }

    private function parse_T_LBRACKET(Token $token)
    {
        $next = $this->match([Lexer::T_NUMBER, Lexer::T_STAR]);
        if ($next->type == Lexer::T_STAR) {
            $this->stack[] = ['op', 'star'];
        } else {
            $this->stack[] = ['push', $next->value];
            $this->stack[] = ['op', 'index'];
        }
        $this->match([Lexer::T_RBRACKET]);

        return $this->match($this->nextExpr);
    }

    private function parse_T_NUMBER(Token $token)
    {
        $this->stack[] = ['push', $token->value];
        $this->stack[] = ['op', 'field'];

        return $this->match($this->nextExpr);
    }

    private function parse_T_OR(Token $token)
    {
        $this->stack[] = ['op', 'or'];
        $this->lexer->next();

        return $this->lexer->current();
    }

    private function parse_T_STAR(Token $token)
    {
        $this->stack[] = ['op', 'star'];

        return $this->match($this->nextExpr);
    }

    private function parse_T_DOT(Token $token)
    {
        return $this->match([Lexer::T_IDENTIFIER, Lexer::T_NUMBER, Lexer::T_STAR]);
    }

    /**
     * Match a token against a list of expected types
     *
     * @param Token $token to match
     * @param array $types Value types
     * @throws SyntaxErrorException
     */
    private function matchToken(Token $token, array $types)
    {
        if (!in_array($token->type, $types)) {
            throw new SyntaxErrorException($types, $token, $this->lexer);
        }
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
        $this->matchToken($token, $types);

        return $token;
    }
}
