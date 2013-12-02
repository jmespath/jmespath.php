<?php

namespace JmesPath;

/**
 * LL(k) recursive descent parser with backtracking used to assemble bytecode
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

    /** @var array Stack of marked tokens for speculative parsing */
    private $markedTokens = array();

    /** @var array First acceptable token */
    private static $exprTokens = array(
        Lexer::T_IDENTIFIER => true,
        Lexer::T_NUMBER => true,
        Lexer::T_STAR => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_LBRACE => true,
        Lexer::T_FUNCTION => true,
        Lexer::T_AT => true,
        Lexer::T_PRIMITIVE => true
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
        if (!$path) {
            return array();
        }

        $this->stack = $this->markedTokens = array();
        $this->lexer->setInput($path);
        $this->tokens = $this->lexer->getIterator();
        $token = $this->tokens->current();

        // Ensure that the first token is valid
        if (!isset(self::$exprTokens[$token['type']])) {
            throw $this->syntax(self::$exprTokens);
        }

        do {
            $this->parseInstruction($token);
            $token = $this->nextToken();
        } while ($token['type'] !== Lexer::T_EOF);

        $this->stack[] = array('stop');

        return $this->stack;
    }

    /**
     * Returns a SyntaxErrorException for the current token
     *
     * @param $messageOrTypes
     *
     * @return SyntaxErrorException
     */
    private function syntax($messageOrTypes)
    {
        $current = $this->tokens->current();

        return new SyntaxErrorException($messageOrTypes, $current, $this->lexer->getInput());
    }

    /**
     * @return array Returns the next token after advancing
     */
    private function nextToken()
    {
        static $nullToken = array('type' => Lexer::T_EOF);
        $this->tokens->next();

        return $this->tokens->current() ?: $nullToken;
    }

    /**
     * Match the next token against one or more types and advance the lexer
     *
     * @param array $types Type to match
     *
     * @return array Returns the next token
     * @throws SyntaxErrorException
     */
    private function match(array $types)
    {
        $token = $this->nextToken();
        if (!isset($types[$token['type']])) {
            throw $this->syntax($types);
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
    private function matchPeek(array $types)
    {
        $token = $this->peek();
        if (!isset($types[$token['type']])) {
            $this->nextToken();
            throw $this->syntax($types);
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
    private function peek($lookAhead = 1)
    {
        $nextPos = $this->tokens->key() + $lookAhead;

        return isset($this->tokens[$nextPos])
            ? $this->tokens[$nextPos]
            : array('type' => Lexer::T_EOF, 'value' => '');
    }

    /**
     * Returns the type of the previous token, or null if it was the start
     *
     * @return null|string Returns 'Array', 'Object', or null if unknown
     */
    private function previousType()
    {
        $prevPos = $this->tokens->key() - 1;
        $prev = isset($this->tokens[$prevPos]) ? $this->tokens[$prevPos] : null;

        if ($prev) {
            return $prev['type'] == Lexer::T_DOT ? 'Object' : 'Array';
        }

        return null;
    }

    /**
     * Marks the current token iterator position for the start of a speculative
     * parse instruction
     */
    private function markToken()
    {
        $this->markedTokens[] = array($this->tokens->key(), $this->stack);
    }

    /**
     * Pops the most recent speculative parsing marked position and resets the
     * token iterator to the marked position.
     *
     * @param bool $success If set to false, the state is reset to the state
     *                      at the original marked position. If set to true,
     *                      the mark is popped but the state remains.
     */
    private function resetToken($success)
    {
        $result = array_pop($this->markedTokens);

        if (!$success) {
            $this->tokens->seek($result[0]);
            $this->stack = $result[1];
        }
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
            throw $this->syntax('No matching opcode for ' . $token['type']);
        }

        $this->{$method}($token);
    }

    private function parse_T_IDENTIFIER(array $token)
    {
        $this->matchPeek(array(
            Lexer::T_LBRACE   => true, // a{foo: 0}
            Lexer::T_LBRACKET => true, // a[0]
            Lexer::T_RBRACE   => true, // {a: b}
            Lexer::T_RBRACKET => true, // [a] / foo[a = substring(@, 0, 1)]
            Lexer::T_COMMA    => true, // [a, b]
            Lexer::T_EOF      => true, // foo,
            Lexer::T_DOT      => true, // foo.bar
            Lexer::T_OR       => true, // foo || bar
            Lexer::T_OPERATOR => true, // foo[a = "a"]
            Lexer::T_RPARENS  => true, // foo[length(abc)]
        ));

        $this->stack[] = array('field', $token['value']);
    }

    private function parse_T_NUMBER(array $token)
    {
        $this->matchPeek(array(
            Lexer::T_RBRACE   => true, // {a: 1}
            Lexer::T_RBRACKET => true, // [1] / foo[1 < 2]
            Lexer::T_RPARENS  => true, // foo[substring(@, 0, 1)]
            Lexer::T_COMMA    => true, // [1, 2]
            Lexer::T_OR       => true, // foo.-1 || bar
            Lexer::T_OPERATOR => true, // foo[1 < 2]
            Lexer::T_EOF      => true, // foo.-1
        ));

        $this->stack[] = array('index', (int) $token['value']);
    }

    private function parse_T_DOT(array $token)
    {
        $next = $this->matchPeek(array(
            Lexer::T_IDENTIFIER => true, // foo.bar
            Lexer::T_NUMBER     => true, // foo.-1
            Lexer::T_STAR       => true, // foo.*
            Lexer::T_LBRACE     => true, // foo[1]
            Lexer::T_LBRACKET   => true, // foo{a: 0}
        ));

        if ($next['type'] == Lexer::T_NUMBER) {
            // Handle cases like foo.-1
            $this->parse_T_IDENTIFIER($this->nextToken());
        }
    }

    /**
     * Parses an OR expression using a jump_if_true opcode. Parses tokens until
     * a scope change (COMMA, OR, RBRACE, RBRACKET, or EOF) token is found.
     */
    private function parse_T_OR(array $token)
    {
        $peek = $this->matchPeek(self::$exprTokens);
        // Parse until the next terminal condition
        $this->stack[] = array('is_null');
        $this->stack[] = array('jump_if_false', null);
        $index = count($this->stack) - 1;

        // Pop the empty variable at TOS
        $this->stack[] = array('pop');

        // Push the current node onto the stack if needed
        if ($peek['type'] != Lexer::T_FUNCTION) {
            $this->stack[] = array('push_current');
        }

        while (!isset(self::$scope[$peek['type']])) {
            $this->parseInstruction($this->nextToken());
            $peek = $this->peek();
        }

        $this->stack[$index][1] = count($this->stack);
    }

    /**
     * Parses a wildcard expression using a bytecode loop. Parses tokens until
     * a scope change (COMMA, OR, RBRACE, RBRACKET, or EOF) token is found.
     */
    private function parse_T_STAR(array $token, $type = 'Object')
    {
        $peek = $this->matchPeek(array(
            Lexer::T_DOT      => true, // *.bar
            Lexer::T_EOF      => true, // foo.*
            Lexer::T_LBRACKET => true, // foo.*[0]
            Lexer::T_RBRACKET => true, // foo.[a, b.*]
            Lexer::T_LBRACE   => true, // foo.*{a: 0, b: 1}
            Lexer::T_RBRACE   => true, // foo.{a: a, b: b.*}
            Lexer::T_OR       => true, // foo.* || foo
            Lexer::T_COMMA    => true, // foo.[a.*, b]
        ));

        // Create a bytecode loop
        $this->stack[] = array('each', null, $type);
        $index = count($this->stack) - 1;
        $this->consumeWildcard($peek);
        $this->stack[$index][1] = count($this->stack) + 1;
        $this->stack[] = array('jump', $index);
    }

    /**
     * Consume wildcard tokens until a scope change
     *
     * @param array $peek
     */
    private function consumeWildcard(array $peek)
    {
        $this->stack[] = array('mark_current');

        while (!isset(self::$scope[$peek['type']])) {
            $token = $this->nextToken();
            $peek = $this->peek();
            // Don't continue the original project in a subprojection for "[]"
            if ($token['type'] == Lexer::T_LBRACKET && $peek['type'] == Lexer::T_RBRACKET) {
                $this->tokens->seek($this->tokens->key() - 1);
                break;
            }
            $this->parseInstruction($token);
            $peek = $this->peek();
        }

        $this->stack[] = array('pop_current');
    }

    private function parse_T_LBRACE(array $token)
    {
        $fromType = $this->previousType();
        $index = $this->prepareMultiBranch();
        $this->parseKeyValuePair($fromType);

        $peek = $this->peek();
        while ($peek['type'] != Lexer::T_RBRACE) {
            $token = $this->nextToken();
            if ($token['type'] == Lexer::T_COMMA) {
                $this->parseKeyValuePair($fromType);
            }
            $peek = $this->peek();
        }

        $this->match(array(Lexer::T_RBRACE => true));
        $this->finishMultiBranch($index);
    }

    /**
     * Parse a multi-brace expression key value pair while only allowing for
     * certain value types.
     *
     * @param string $type Valid types for values (Array or Object)
     * @throws SyntaxErrorException
     */
    private function parseKeyValuePair($type)
    {
        $keyToken = $this->match(array(Lexer::T_IDENTIFIER => true));
        $this->match(array(Lexer::T_COLON => true));

        // Requires at least one value that can start an expression, and
        // don't allow Number indices on Objects or strings on Arrays
        $valid = self::$exprTokens;
        if ($type == 'Array') {
            unset($valid[Lexer::T_IDENTIFIER]);
        } elseif ($type == 'Object') {
            unset($valid[Lexer::T_NUMBER]);
        }
        $token = $this->match($valid);

        // Account for functions that don't need a current node pushed
        if ($token['type'] != Lexer::T_FUNCTION) {
            $this->stack[] = array('push_current');
        }
        $this->parseInstruction($token);

        // Parse the rest of the expression until a scope changing token
        $peek = $this->peek();
        while ($peek['type'] !== Lexer::T_COMMA && $peek['type'] != Lexer::T_RBRACE) {
            $token = $this->nextToken();
            if ($token['type'] == Lexer::T_EOF) {
                throw $this->syntax('Unexpected T_EOF');
            }
            $this->parseInstruction($token);
            $peek = $this->peek();
        }

        $this->storeMultiBranchKey($keyToken['value']);
    }

    private function parse_T_LBRACKET(array $token)
    {
        $fromType = $this->previousType();

        $peek = $this->matchPeek(array(
            Lexer::T_IDENTIFIER => true, // [a, b]
            Lexer::T_NUMBER     => true, // [0]
            Lexer::T_STAR       => true, // [*]
            Lexer::T_RBRACKET   => true, // foo[]
            Lexer::T_PRIMITIVE  => true, // foo[true, bar]
            Lexer::T_FUNCTION   => true, // foo[count(@)]
            Lexer::T_AT         => true  // foo[@, 2]
        ));

        // Don't JmesForm the data into a split array when a merge occurs
        if ($peek['type'] == Lexer::T_RBRACKET) {
            if ($fromType == 'Object') {
                throw $this->syntax('Cannot merge from an object');
            }
            $token = $this->nextToken();
            $this->stack[] = array('merge');
            $this->parse_T_STAR($token, 'Array');
            return;
        }

        // Parse simple expressions like [10] or [*]
        $nextTwo = $this->peek(2);
        if ($nextTwo['type'] == Lexer::T_RBRACKET &&
            ($peek['type'] == Lexer::T_NUMBER || $peek['type'] == Lexer::T_STAR)
        ) {
            if ($peek['type'] == Lexer::T_NUMBER) {
                if ($fromType == 'Object') {
                    throw $this->syntax('Cannot access Object keys using Number indices');
                }
                $this->parse_T_NUMBER($this->nextToken());
                $this->nextToken();
            } elseif ($fromType == 'Object') {
                throw $this->syntax('Invalid Object wildcard syntax');
            } else {
                $token = $this->nextToken();
                $this->nextToken();
                $this->parse_T_STAR($token, 'Array');
            }
            return;
        }

        // Speculative parsing with backtracking
        if (!$this->speculateMultiBracket($fromType) &&
            !$this->speculateFilter($fromType)
        ) {
            throw $this->syntax('Expected a multi-expression or a filter expression');
        }
    }

    /**
     * Parse a multi-bracket expression list element while only allowing
     * certain key types.
     *
     * @param string $type Valid key types (Array or Object)
     */
    private function parseMultiBracketElement($type)
    {
        // Don't allow Number indices on Objects or strings on Arrays
        $valid = self::$exprTokens;
        if ($type == 'Array') {
            unset($valid[Lexer::T_IDENTIFIER]);
        } elseif ($type == 'Object') {
            unset($valid[Lexer::T_NUMBER]);
        }
        $token = $this->match($valid);

        // Push the current node onto the stack if the token is not a function
        if ($token['type'] != Lexer::T_FUNCTION) {
            $this->stack[] = array('push_current');
        }
        $this->parseInstruction($token);

        // Parse the rest of the expression until a scope changing token
        $peek = $this->peek();
        while ($peek['type'] != Lexer::T_COMMA && $peek['type'] != Lexer::T_RBRACKET) {
            $token = $this->nextToken();
            if ($token['type'] == Lexer::T_EOF) {
                throw $this->syntax('Unexpected T_EOF');
            }
            $this->parseInstruction($token);
            $peek = $this->peek();
        }

        $this->storeMultiBranchKey(null);
    }

    /**
     * Determines if the expression in a bracket is a multi-select
     *
     * @param string $type Valid key types (Array or Object)
     *
     * @return bool Returns true if this is a multi-select or false if not
     */
    private function speculateMultiBracket($type)
    {
        $this->markToken();

        try {
            $index = $this->prepareMultiBranch();
            // Parse at least one element
            $this->parseMultiBracketElement($type);
            // Parse any remaining elements
            $until = array(Lexer::T_COMMA => true, Lexer::T_RBRACKET => true);
            $token = $this->match($until);
            while ($token['type'] != Lexer::T_RBRACKET) {
                $this->parseMultiBracketElement($type);
                $token = $this->match($until);
            }
            $this->finishMultiBranch($index);
            // Remove the token backtracking mark position
            $this->resetToken(true);
            return true;
        } catch (SyntaxErrorException $e) {
            $this->resetToken(false);
            return false;
        }
    }

    /**
     * @return int Returns the index of the jump bytecode instruction
     */
    private function prepareMultiBranch()
    {
        $this->stack[] = array('is_empty');
        $this->stack[] = array('jump_if_true', null);
        $this->stack[] = array('mark_current');
        $this->stack[] = array('pop');
        $this->stack[] = array('push', array());

        return count($this->stack) - 4;
    }

    /**
     * @param string|null $key Key to store the result in
     */
    private function storeMultiBranchKey($key)
    {
        $this->stack[] = array('store_key', $key);
    }

    /**
     * @param int $index Index to update for the pre-jump instruction
     */
    private function finishMultiBranch($index)
    {
        $this->stack[] = array('pop_current');
        $this->stack[$index][1] = count($this->stack);
    }

    /**
     * Parses a function, it's arguments, and manages scalar vs node arguments
     *
     * @param array $func Function token being parsed
     *
     * @throws SyntaxErrorException When EOF is encountered before ")"
     */
    private function parse_T_FUNCTION(array $func)
    {
        $found = 0;
        $until = array(Lexer::T_COMMA => true, Lexer::T_RPARENS => true);
        $after = self::$exprTokens;
        $after[Lexer::T_RPARENS] = true;
        $peek = $this->matchPeek($after);

        while ($peek['type'] != Lexer::T_RPARENS) {
            $found++;
            $peek = $this->parseFunctionArgumentOrLrExpression($until);
            if ($peek['type'] == Lexer::T_COMMA) {
                $this->nextToken();
                $peek = $this->matchPeek($after);
            }
        }

        $this->nextToken();
        $this->stack[] = array('call', $func['value'], $found);
    }

    /**
     * Parse a function or LR expression argument until one of the break tokens
     * is encountered. This method takes the @ token into account when parsing
     * tokens to determine if the token is pushed onto the stack or if it
     * descends into the current node.
     *
     * @param array $breakOn Stops parsing when one of these keys are hit
     *
     * @return array Returns the next peek token
     */
    private function parseFunctionArgumentOrLrExpression(array $breakOn)
    {
        $peek = $this->matchPeek(self::$exprTokens);

        // Functions arguments and LR expressions only operate on the current
        // node when they start with the T_AT (@) token.
        if ($inNode = $peek['type'] == Lexer::T_AT) {
            $this->stack[] = array('push_current');
            $this->nextToken(); // Consume the @ token
            // Allow @ to be by itself by using a union with the break token
            $peek = $this->matchPeek($breakOn + array(
                Lexer::T_DOT      => true, // @.foo
                Lexer::T_LBRACKET => true, // @[0]
                Lexer::T_LBRACE   => true, // @{a: 1}
                Lexer::T_COMMA    => true, // foo[@, 0]
            ));
        }

        // Parse all of the tokens of the argument expression
        while (!isset($breakOn[$peek['type']])) {
            $token = $this->nextToken();
            if ($token['type'] == Lexer::T_EOF) {
                throw $this->syntax('Unexpected T_EOF');
            }
            if ($inNode) {
                $this->parseInstruction($token);
            } else {
                $this->stack[] = array('push', $token['value']);
            }
            $peek = $this->peek();
        }

        return $peek;
    }

    /**
     * Determines if the expression in a bracket is a filter
     *
     * @return bool Returns true if this is a filter or false if not
     * @throws SyntaxErrorException
     */
    private function speculateFilter()
    {
        $this->markToken();

        // Create a bytecode loop
        $this->stack[] = array('each', null);
        $loopIndex = count($this->stack) - 1;
        $this->stack[] = array('mark_current');

        try {
            $this->parseFullExpression();
        } catch (SyntaxErrorException $e) {
            $this->resetToken(false);
            return false;
        }

        // If the evaluated filter was true, then jump to the wildcard loop
        $this->stack[] = array('pop_current');
        $this->stack[] = array('jump_if_true', count($this->stack) + 4);
        // Kill temp variables when a filter filters a node
        $this->stack[] = array('pop');
        $this->stack[] = array('push', null);
        $this->stack[] = array('jump', $loopIndex);
        // Actually yield values that matched the filter
        $this->consumeWildcard($this->nextToken());
        // Finish the projection loop
        $this->stack[] = array('jump', $loopIndex);
        $this->stack[$loopIndex][1] = count($this->stack);

        // Stop the token marking
        $this->resetToken(true);

        return true;
    }

    /**
     * Parse an entire filter expression including the left, operator, and right
     */
    private function parseFullExpression()
    {
        static $operators = array(
            '='  => 'eq',
            '!=' => 'not',
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte'
        );

        // Parse the left hand part of the expression until a T_OPERATOR
        $this->parseFunctionArgumentOrLrExpression(array(Lexer::T_OPERATOR => true));

        $operatorToken = $this->nextToken();

        // Parse the right hand part of the expression until a T_RBRACKET
        $this->parseFunctionArgumentOrLrExpression(array(
            Lexer::T_RBRACKET => true,
            Lexer::T_OR       => true
        ));

        // Add the operator opcode and track the jump if false index
        if (isset($operators[$operatorToken['value']])) {
            $this->stack[] = array($operators[$operatorToken['value']]);
        } else {
            throw $this->syntax('Invalid operator');
        }

        $peek = $this->peek();
        if ($peek['type'] == Lexer::T_OR) {
            $this->nextToken(); // Skip the OR token
            $this->stack[] = array('is_falsey');
            $this->stack[] = array('jump_if_false', null);
            $index = count($this->stack) - 1;
            $this->stack[] = array('pop');
            $this->parseFullExpression();
            $this->stack[$index][1] = count($this->stack);
        }
    }
}
