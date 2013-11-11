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

    /** @var array First acceptable token */
    private static $firstTokens = [
        Lexer::T_IDENTIFIER => true,
        Lexer::T_NUMBER => true,
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

        return $this->matchAny();
    }

    private function parse_T_NUMBER(array $token)
    {
        $this->stack[] = ['index', $token['value']];

        return $this->matchAny();
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

        $token = $this->match($expectedFirst);
        $value = $token['value'];
        $nextToken = $this->peek();

        if ($token['type'] == Lexer::T_NUMBER && $nextToken['type'] == Lexer::T_RBRACKET) {
            // A simple index extraction
            $this->match([Lexer::T_RBRACKET => true]);
            $this->stack[] = ['index', $value];
        } else {
            $this->parseMultiBracket($token);
        }

        return $this->matchAny();
    }

    private function parseMultiBracket(array $token)
    {
        $this->stack[] = ['dup_top'];
        $this->stack[] = ['push', []];
        $this->stack[] = ['rot_two'];

        do {
            $token = $this->parseInstruction($token);
            if ($token['type'] == Lexer::T_COMMA) {
                $this->stack[] = ['store_key'];
                $this->stack[] = ['rot_two'];
                $this->stack[] = ['dup_top'];
                $this->stack[] = ['rot_three'];
                $token = $this->parseInstruction($this->match(self::$firstTokens));
            }
        } while ($token['type'] != Lexer::T_RBRACKET);

        $this->stack[] = ['store_key'];
        $this->stack[] = ['rot_two'];
        $this->stack[] = ['pop'];
    }

    private function parse_T_LBRACE(array $token)
    {
        $token = $this->match([Lexer::T_IDENTIFIER => true, Lexer::T_NUMBER => true]);
        $value = $token['value'];
        $nextToken = $this->peek();

        if ($nextToken['type'] == Lexer::T_RBRACKET &&
            ($token['type'] == Lexer::T_NUMBER || $token['type'] == Lexer::T_IDENTIFIER)
        ) {
            // A simple index extraction
            $this->stack[] = ['field', $value];
        } else {
            $this->parseMultiBrace($token);
        }

        return $this->matchAny();
    }

    private function parseMultiBrace(array $token)
    {
        $this->stack[] = ['dup_top'];
        $this->stack[] = ['push', []];
        $this->stack[] = ['rot_two'];

        $currentKey = $token['value'];
        $this->match([Lexer::T_COLON => true]);
        $token = $this->match(self::$firstTokens);

        do {
            $token = $this->parseInstruction($token);
            if ($token['type'] == Lexer::T_COMMA) {
                $this->stack[] = ['store_key', $currentKey];
                $this->stack[] = ['rot_two'];
                $this->stack[] = ['dup_top'];
                $this->stack[] = ['rot_three'];
                $token = $this->match([Lexer::T_IDENTIFIER => true]);
                $this->match([Lexer::T_COLON => true]);
                $currentKey = $token['value'];
                $token = $this->parseInstruction($this->match(self::$firstTokens));
            }
        } while ($token['type'] != Lexer::T_RBRACE);

        $this->stack[] = ['store_key', $currentKey];
        $this->stack[] = ['rot_two'];
        $this->stack[] = ['pop'];
    }

    /**
     * Parses an OR expression using a jump_if_true opcode. Parses tokens until
     * a scope change (COMMA, OR, RBRACE, RBRACKET, or EOF) token is found.
     */
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

    /**
     * Parses a wildcard expression using a bytecode loop. Parses tokens until
     * a scope change (COMMA, OR, RBRACE, RBRACKET, or EOF) token is found.
     */
    private function parse_T_STAR(array $token)
    {
        static $afterStar = [
            Lexer::T_DOT => true,
            Lexer::T_EOF => true,
            Lexer::T_LBRACKET => true,
            Lexer::T_RBRACKET => true,
            Lexer::T_LBRACE => true,
            Lexer::T_RBRACE => true,
            Lexer::T_OR => true,
            Lexer::T_COMMA => true
        ];

        // Create a bytecode loop
        $token = $this->match($afterStar);
        $this->stack[] = ['each', null];
        $index = count($this->stack) - 1;

        while (!isset(self::$scope[$token['type']])) {
            $token = $this->parseInstruction($token);
        }

        $this->stack[$index][1] = count($this->stack) + 1;
        $this->stack[] = ['goto', $index];

        return $this->matchAny();
    }

    /**
     * Match any token
     *
     * @return array
     */
    private function matchAny()
    {
        static $nullToken = ['type' => Lexer::T_EOF];
        $this->tokens->next();

        return $this->tokens->current() ?: $nullToken;
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
        static $nullToken = ['type' => Lexer::T_EOF];
        $this->tokens->next();
        $token = $this->tokens->current() ?: $nullToken;

        if (isset($types[$token['type']])) {
            return $token;
        }

        throw new SyntaxErrorException($types, $token, $this->lexer->getInput());
    }

    /**
     * Grab the next lexical token without consuming it
     *
     * @return array
     */
    private function peek()
    {
        $position = $this->tokens->key();
        $this->tokens->next();
        $value = $this->tokens->current();
        $this->tokens->seek($position);

        return $value;
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
