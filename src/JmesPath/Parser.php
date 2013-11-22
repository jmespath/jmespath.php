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

    /** @var array */
    private $currentToken;

    /** @var array */
    private $previousToken;

    /** @var array opcode stack*/
    private $stack;

    /** @var array Known opcodes of the parser */
    private $methods;

    /** @var int The number of open multi expressions */
    private $inMultiBranch = 0;

    /** @var array First acceptable token */
    private static $firstTokens = array(
        Lexer::T_IDENTIFIER => true,
        Lexer::T_NUMBER => true,
        Lexer::T_STAR => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_LBRACE => true,
        Lexer::T_EOF => true
    );

    /** @var array Scope changes */
    private static $scope = array(
        Lexer::T_COMMA => true,
        Lexer::T_OR => true,
        Lexer::T_RBRACE => true,
        Lexer::T_RBRACKET => true,
        Lexer::T_EOF => true
    );

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
        $this->stack = array();
        $this->lexer->setInput($path);
        $this->tokens = $this->lexer->getIterator();
        $this->currentToken = $this->tokens->current();
        $this->previousToken = null;

        // Ensure that the first token is valid
        if (!isset(self::$firstTokens[$this->currentToken['type']])) {
            throw new SyntaxErrorException(
                self::$firstTokens,
                $this->currentToken,
                $this->lexer->getInput()
            );
        }

        $token = $this->currentToken;
        while ($token['type'] !== Lexer::T_EOF) {
            $token = $this->parseInstruction($token);
        }

        $this->stack[] = array('stop');

        return $this->stack;
    }

    private function parse_T_IDENTIFIER(array $token)
    {
        $this->stack[] = array('field', $token['value']);
    }

    private function parse_T_NUMBER(array $token)
    {
        if ($this->previousToken && $this->previousToken['type'] == Lexer::T_DOT) {
            // Account for "foo.-1"
            $this->stack[] = array('field', $token['value']);
        } else {
            $this->stack[] = array('index', (int) $token['value']);
        }
    }

    private function parse_T_DOT(array $token)
    {
        static $expectedAfterDot = array(
            Lexer::T_IDENTIFIER => true,
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true,
            Lexer::T_LBRACE => true,
            Lexer::T_LBRACKET => true
        );

        return $this->match($expectedAfterDot);
    }

    /**
     * Parses an OR expression using a jump_if_true opcode. Parses tokens until
     * a scope change (COMMA, OR, RBRACE, RBRACKET, or EOF) token is found.
     */
    private function parse_T_OR(array $token)
    {
        // Parse until the next terminal condition
        $token = $this->match(self::$firstTokens);
        $this->stack[] = array('jump_if_true', null);
        $index = count($this->stack) - 1;
        $this->stack[] = array('pop');

        // Special stack handling when an OR is inside of a multi branch
        if ($this->inMultiBranch) {
            $this->stack[] = array('rot_two');
            $this->stack[] = array('dup_top');
            $this->stack[] = array('rot_three');
        }

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
        static $afterStar = array(
            Lexer::T_DOT => true,
            Lexer::T_EOF => true,
            Lexer::T_LBRACKET => true,
            Lexer::T_RBRACKET => true,
            Lexer::T_LBRACE => true,
            Lexer::T_RBRACE => true,
            Lexer::T_OR => true,
            Lexer::T_COMMA => true
        );

        // Create a bytecode loop
        $token = $this->match($afterStar);
        $this->stack[] = array('each', null);
        $index = count($this->stack) - 1;

        while (!isset(self::$scope[$token['type']])) {
            // Don't continue the original project in a subprojection for "[]"
            $peek = $this->peek();
            if ($token['type'] == Lexer::T_LBRACKET && $peek['type'] == Lexer::T_RBRACKET) {
                break;
            }
            $token = $this->parseInstruction($token);
        }

        $this->stack[$index][1] = count($this->stack) + 1;
        $this->stack[] = array('goto', $index);

        return $token;
    }

    private function parse_T_LBRACKET(array $token)
    {
        static $expectedAfter = array(
            Lexer::T_IDENTIFIER => true,
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true,
            Lexer::T_RBRACKET => true
        );

        $token = $this->match($expectedAfter);

        // Don't JmesForm the data into a split array
        if ($token['type'] == Lexer::T_RBRACKET) {
            $this->stack[] = array('merge');
            return $this->parse_T_STAR($token);
        }

        $value = $token['value'];
        $nextToken = $this->peek();

        if ($nextToken['type'] != Lexer::T_RBRACKET || $token['type'] == Lexer::T_IDENTIFIER) {
            $this->parseMultiBracket($token);
        } else {
            // A simple extraction
            $this->match(array(Lexer::T_RBRACKET => true));
            if ($token['type'] == Lexer::T_NUMBER) {
                $this->stack[] = array('index', (int) $value);
            } elseif ($token['type'] == Lexer::T_STAR) {
                return $this->parse_T_STAR($token);
            }
        }
    }

    private function parseMultiBracket(array $token)
    {
        $index = $this->prepareMultiBranch();

        do {
            if ($token['type'] != Lexer::T_COMMA) {
                $token = $this->parseInstruction($token);
            } else {
                $this->storeMultiBranchKey(null);
                $token = $this->parseInstruction($this->match(self::$firstTokens));
            }
        } while ($token['type'] != Lexer::T_RBRACKET);

        $this->finishMultiBranch($index, null);
    }

    private function parse_T_LBRACE(array $token)
    {
        $token = $this->match(array(Lexer::T_IDENTIFIER => true, Lexer::T_NUMBER => true));
        $value = $token['value'];
        $nextToken = $this->peek();

        if ($nextToken['type'] == Lexer::T_RBRACE &&
            ($token['type'] == Lexer::T_NUMBER || $token['type'] == Lexer::T_IDENTIFIER)
        ) {
            // A simple index extraction
            $this->stack[] = array('field', $value);
            $this->nextToken();
        } else {
            $this->parseMultiBrace($token);
        }
    }

    private function parseMultiBrace(array $token)
    {
        $index = $this->prepareMultiBranch();
        $currentKey = $token['value'];
        $this->match(array(Lexer::T_COLON => true));
        $token = $this->match(self::$firstTokens);

        do {
            if ($token['type'] != Lexer::T_COMMA) {
                $token = $this->parseInstruction($token);
            } else {
                $this->storeMultiBranchKey($currentKey);
                $token = $this->match(array(Lexer::T_IDENTIFIER => true));
                $this->match(array(Lexer::T_COLON => true));
                $currentKey = $token['value'];
                $token = $this->parseInstruction($this->match(self::$firstTokens));
            }
        } while ($token['type'] != Lexer::T_RBRACE);

        $this->finishMultiBranch($index, $currentKey);
    }

    /**
     * @return int Returns the index of the jump bytecode instruction
     */
    private function prepareMultiBranch()
    {
        ++$this->inMultiBranch;
        $this->stack[] = array('jump_if_false', null);
        $this->stack[] = array('dup_top');
        $this->stack[] = array('push', array());
        $this->stack[] = array('rot_two');

        return count($this->stack) - 4;
    }

    /**
     * @param string|null $key Key to store the result in
     */
    private function storeMultiBranchKey($key)
    {
        $this->stack[] = array('store_key', $key);
        $this->stack[] = array('rot_two');
        $this->stack[] = array('dup_top');
        $this->stack[] = array('rot_three');
    }

    /**
     * @param int         $index Index to update for the pre-jump instruction
     * @param string|null $key   Key used to store the last result value
     */
    private function finishMultiBranch($index, $key)
    {
        $this->stack[] = array('store_key', $key);
        $this->stack[] = array('rot_two');
        $this->stack[] = array('pop');
        $this->stack[$index][1] = count($this->stack);
        --$this->inMultiBranch;
    }

    /**
     * @return array Returns the next token after advancing
     */
    private function nextToken()
    {
        static $nullToken = array('type' => Lexer::T_EOF);
        $this->previousToken = $this->currentToken;
        $this->tokens->next();
        $this->currentToken = $this->tokens->current() ?: $nullToken;

        return $this->currentToken;
    }

    /**
     * Match the next token against one or more types
     *
     * @param array $types Type to match
     * @return array Returns a token
     * @throws SyntaxErrorException
     */
    private function match(array $types)
    {
        $token = $this->nextToken();

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
        $nextPos = $this->tokens->key() + 1;

        return isset($this->tokens[$nextPos])
            ? $this->tokens[$nextPos]
            : array('type' => Lexer::T_EOF, 'value' => '');
    }

    /**
     * Call an validate a parse instruction
     *
     * @param array $token Token to parse
     * @return array Returns the next token
     * @throws SyntaxErrorException When an invalid token is encountered
     */
    private function parseInstruction(array $token)
    {
        $method = 'parse_' . $token['type'];
        if (!isset($this->methods[$method])) {
            throw new SyntaxErrorException(
                'No matching opcode for ' . $token['type'],
                $token,
                $this->lexer->getInput()
            );
        }

        return $this->{$method}($token) ?: $this->nextToken();
    }
}
