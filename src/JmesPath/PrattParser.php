<?php

namespace JmesPath;

class PrattParser
{
    const UNARY = 1;
    const BINARY = 2;

    /** @var Lexer */
    private $lexer;

    /** @var array Array of tokens */
    private $tokens;

    /** @var int */
    private $tokenPos;

    /** @var int */
    private $tokenCount;

    /** @var string JMESPath expression */
    private $input;

    /** @var array Null token that is reused over and over */
    private static $nullToken = array('type' => Lexer::T_EOF, 'value' => '');

    /** @var array Array of parselets */
    private $parselets = array();

    /**
     * @param LexerInterface $lexer Lexer used to tokenize paths
     */
    public function __construct(LexerInterface $lexer)
    {
        $this->lexer = $lexer;
    }

    /**
     * Register a token parselet with the parser
     *
     * @param $token
     * @param $function
     * @param int $precedence
     * @param int $args
     * @throws \InvalidArgumentException
     */
    public function register($token, $function, $precedence = 0, $args = self::BINARY)
    {
        if (isset($this->parselets[$token])) {
            throw new \InvalidArgumentException("{$token} is already registered");
        }

        $this->parselets[$token] = array(
            'fn'         => $function,
            'precedence' => $precedence,
            'args'       => $args
        );
    }

    public function parse($expression)
    {
        $this->input = $expression;
        $this->tokens = $this->lexer->tokenize($expression);
        $this->tokenCount = count($this->tokens);
        $this->tokenPos = -1;

        while (isset($this->tokens[$this->tokenPos + 1])) {
            $this->parseExpression();
        }
    }

    public function parseExpression($precedence = 0)
    {
        $token = $this->nextToken();

        if (!isset($this->parselets[$token['type']])) {
            $this->throwSyntax('Unexpected token: ' . $token['type']);
        }

        $left = call_user_func($this->parselets[$token['type']]['fn'], $token, $this);

        while ($precedence >= $this->getPrecedence() && isset($this->tokens[$this->tokenPos + 1])) {
            $token = $this->nextToken();
            if (!isset($this->parselets[$token['type']])) {
                $this->throwSyntax('Unexpected token: ' . $token['type']);
            }
            $left = call_user_func($this->parselets[$token['type']]['fn'], $token, $this, $left);
        }

        return $left;
    }

    /**
     * Throws a SyntaxErrorException for the current token
     *
     * @param array|string $messageOrTypes
     * @throws SyntaxErrorException
     */
    public function throwSyntax($messageOrTypes)
    {
        throw new SyntaxErrorException(
            $messageOrTypes,
            $this->tokens[$this->tokenPos],
            $this->input
        );
    }

    /**
     * @return array Returns the next token after advancing
     */
    public function nextToken()
    {
        return $this->tokens[++$this->tokenPos];
    }

    /**
     * Match the next token against one or more types and advance the lexer
     *
     * @param array $types Type to match
     *
     * @return array Returns the next token
     * @throws SyntaxErrorException
     */
    public function match(array $types)
    {
        $token = $this->nextToken();
        if (!isset($types[$token['type']])) {
            $this->throwSyntax($types);
        }

        return $token;
    }

    /**
     * Match the peek token against one or more types
     *
     * @param array $types Type to match
     *
     * @return array Returns the next token
     * @throws SyntaxErrorException
     */
    public function matchPeek(array $types)
    {
        $token = $this->peek();
        if (!isset($types[$token['type']])) {
            $this->nextToken();
            $this->throwSyntax($types);
        }

        return $token;
    }

    /**
     * Grab the next lexical token without consuming it
     *
     * @param int $lookAhead Number of token to lookahead
     *
     * @return array
     */
    public function peek($lookAhead = 1)
    {
        $nextPos = $this->tokenPos + $lookAhead;

        return isset($this->tokens[$nextPos])
            ? $this->tokens[$nextPos]
            : self::$nullToken;
    }

    private function getPrecedence()
    {
        $peek = $this->peek();

        return isset($this->parselets[$peek['type']])
            ? $this->parselets[$peek['type']]['precedence']
            : 0;
    }
}
