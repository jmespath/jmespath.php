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

    /** @var array Scope changes */
    private static $scope = [
        Lexer::T_COMMA,
        Lexer::T_OR,
        Lexer::T_RBRACE,
        Lexer::T_RBRACKET,
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

        $token = $this->tokens->current();
        while ($token['type'] !== Lexer::T_EOF) {
            $token = $this->parseInstruction($token);
        }

        $this->stack[] = ['stop'];

        return $this->stack;
    }

    private function parse_T_IDENTIFIER(array $token)
    {
        $this->stack[] = ['field', $token['value']];

        return $this->match(self::$nextExpr);
    }

    private function parse_T_NUMBER(array $token)
    {
        $this->stack[] = ['field', $token['value']];

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

    private function parse_T_LBRACKET(array $token)
    {
        static $expectedFirst = [Lexer::T_NUMBER => true, Lexer::T_STAR => true];
        static $expectedAfterEntry = [
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true,
            Lexer::T_COMMA => true,
            Lexer::T_RBRACKET => true
        ];

        $currentToken = $this->match($expectedFirst);
        $value = $currentToken['value'];
        $nextToken = $this->match($expectedAfterEntry);
        if ($currentToken['type'] == Lexer::T_NUMBER && $nextToken['type'] == Lexer::T_RBRACKET) {
            // A simple index extraction
            $this->stack[] = ['index', $value];
        } else {
            // A multi expression

        }

        return $this->match(self::$nextExpr);
    }

    private function parse_T_LBRACE(array $token)
    {
        static $expectedFirst = [Lexer::T_IDENTIFIER, Lexer::T_NUMBER => true, Lexer::T_STAR => true];
        static $expectedAfterEntry = [
            Lexer::T_IDENTIFIER,
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true,
            Lexer::T_COMMA => true,
            Lexer::T_RBRACE => true
        ];

        $currentToken = $this->match($expectedFirst);
        $value = $currentToken['value'];
        $nextToken = $this->match($expectedAfterEntry);
        if ($nextToken['type'] == Lexer::T_RBRACKET &&
            ($currentToken['type'] == Lexer::T_NUMBER || $currentToken['type'] == Lexer::T_IDENTIFIER)
        ) {
            // A simple index extraction
            $this->stack[] = ['field', $value];
        } else {
            // A multi expression

        }

        return $this->match(self::$nextExpr);
    }

    private function parse_T_OR(array $token)
    {
        // Parse until the next terminal condition
        $token = $this->match(self::$firstTokens);
        $this->stack[] = ['jump_if_true', null];
        $index = count($this->stack) - 1;

        do {
            $token = $this->parseInstruction($token);
        } while (!isset(self::$scope[$token['type']]));

        $this->stack[$index][1] = count($this->stack);

        return $token;
    }

    private function parse_T_STAR(array $token)
    {
        // Create a bytecode loop

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

    /**
     * Call an validate a parse instruction
     *
     * @param array $token Token to parse
     * @return array Returns the next token
     * @throws \RuntimeException When an invalid token is encountered
     */
    private function parseInstruction(array $token)
    {
        $method = 'parse_' . $token['type'];
        if (!isset($this->methods[$method])) {
            throw new \RuntimeException('Invalid token: ' . $token['type']);
        }

        return $this->{$method}($token);
    }
}
