<?php

namespace JmesPath;

/**
 * LL(k) recursive descent parser used to assemble bytecode
 */
class Parser
{
    /** @var Lexer */
    private $lexer;

    /** @var array Array of tokens */
    private $tokens;

    /** @var int */
    private $tokenPos;

    /** @var int */
    private $tokenCount;

    /** @var array opcode stack*/
    private $stack;

    /** @var array Known opcodes of the parser */
    private $methods;

    /** @var string JMESPath expression */
    private $input;

    /** @var array Null token that is reused over and over */
    private static $nullToken = array('type' => Lexer::T_EOF, 'value' => '');

    /** @var array First acceptable token */
    private static $exprTokens = array(
        Lexer::T_IDENTIFIER => true,
        Lexer::T_NUMBER     => true,
        Lexer::T_STAR       => true,
        Lexer::T_LBRACKET   => true,
        Lexer::T_LBRACE     => true,
        Lexer::T_FUNCTION   => true,
        Lexer::T_LITERAL    => true,
        Lexer::T_MERGE      => true,
        Lexer::T_AT         => true,
    );

    /** @var array Scope changes */
    private static $scope = array(
        Lexer::T_COMMA    => true,
        Lexer::T_OR       => true,
        Lexer::T_RBRACE   => true,
        Lexer::T_RBRACKET => true,
        Lexer::T_PIPE     => true,
        Lexer::T_EOF      => true,
    );

    private static $noPushTokens = array(
        Lexer::T_FUNCTION => true,
        Lexer::T_FILTER   => true,
        Lexer::T_LITERAL  => true,
        Lexer::T_NUMBER   => true,
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
     * Compile a JMESPath expression into an array of opcodes
     *
     * @param string $path Path to parse
     *
     * @return array
     * @throws SyntaxErrorException
     */
    public function compile($path)
    {
        $this->stack = array();

        if ($path) {
            $this->input = $path;
            $this->tokens = $this->lexer->tokenize($path);
            $this->tokenPos = 0;
            $this->tokenCount = count($this->tokens);
            $token = $this->tokens[0];

            // Ensure that the first token is valid
            if (!isset(self::$exprTokens[$token['type']])) {
                throw $this->syntax(self::$exprTokens);
            }

            do {
                $this->parseInstruction($token);
                $token = $this->nextToken();
            } while ($token['type'] !== Lexer::T_EOF);
        }

        $this->stack[] = array('stop');

        return $this->stack;
    }

    /**
     * Returns a SyntaxErrorException for the current token
     *
     * @param array|string $messageOrTypes
     *
     * @return SyntaxErrorException
     */
    private function syntax($messageOrTypes)
    {
        return new SyntaxErrorException(
            $messageOrTypes,
            $this->tokens[$this->tokenPos],
            $this->input
        );
    }

    /**
     * @return array Returns the next token after advancing
     */
    private function nextToken()
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
        $nextPos = $this->tokenPos + $lookAhead;

        return isset($this->tokens[$nextPos])
            ? $this->tokens[$nextPos]
            : self::$nullToken;
    }

    /**
     * Returns the type of the previous token, or null if it was the start
     *
     * @return null|string Returns 'array', 'object', or null if unknown
     */
    private function previousType()
    {
        $prevPos = $this->tokenPos - 1;
        if (isset($this->tokens[$prevPos])) {
            if ($this->tokens[$prevPos]['type'] == Lexer::T_DOT) {
                return 'object';
            } elseif ($this->tokens[$prevPos]['type'] != Lexer::T_OR) {
                // Note: a previous type of 'OR' means we dunno if array or Obj
                return 'array';
            }
        }

        return null;
    }

    /**
     * Attempts to invoke a parsing method for the given token
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
        static $nextTypes = array(
            Lexer::T_MERGE    => true, // foo[]
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
            Lexer::T_PIPE     => true, // foo.*.a | [0]
        );

        $this->matchPeek($nextTypes);

        $this->stack[] = array('field', $token['value']);
    }

    private function parse_T_NUMBER(array $token)
    {
        static $nextTypes = array(
            Lexer::T_RBRACE   => true, // {a: 1}
            Lexer::T_RBRACKET => true, // [1] / foo[1 < 2]
            Lexer::T_RPARENS  => true, // foo[substring(@, 0, 1)]
            Lexer::T_COMMA    => true, // [1, 2]
            Lexer::T_OR       => true, // foo.-1 || bar
            Lexer::T_OPERATOR => true, // foo[1 < 2]
            Lexer::T_EOF      => true, // foo.-1
            Lexer::T_PIPE     => true, // foo.-1 | bar
        );

        $this->matchPeek($nextTypes);

        $this->stack[] = array('index', (int) $token['value']);
    }

    private function parse_T_DOT(array $token)
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // foo.bar
            Lexer::T_NUMBER     => true, // foo.-1
            Lexer::T_STAR       => true, // foo.*
            Lexer::T_LBRACE     => true, // foo[1]
            Lexer::T_LBRACKET   => true, // foo{a: 0}
            Lexer::T_FILTER     => true,
        );

        $next = $this->matchPeek($nextTypes);

        if ($next['type'] == Lexer::T_NUMBER) {
            // Handle cases like foo.-1
            $this->parse_T_IDENTIFIER($this->nextToken());
        }
    }

    private function parse_T_LITERAL(array $token)
    {
        $this->stack[] = array('push', $token['value']);
    }

    /**
     * The at-token is a no-op as the current node is already on the stack
     */
    private function parse_T_AT(array $token) {}

    /**
     * Parses an or-expression using a jump_if_true opcode. Parses tokens until
     * a scope changing token is found.
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
        if (!isset(self::$noPushTokens[$token['type']])) {
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
     * a scope changing token is found.
     */
    private function parse_T_STAR(array $token, $type = 'object')
    {
        static $nextTypes = array(
            Lexer::T_DOT      => true, // *.bar
            Lexer::T_EOF      => true, // foo.*
            Lexer::T_MERGE    => true, // foo.*[]
            Lexer::T_LBRACKET => true, // foo.*[0]
            Lexer::T_RBRACKET => true, // foo.[a, b.*]
            Lexer::T_LBRACE   => true, // foo.*{a: 0, b: 1}
            Lexer::T_RBRACE   => true, // foo.{a: a, b: b.*}
            Lexer::T_OR       => true, // foo.* || foo
            Lexer::T_COMMA    => true, // foo.[a.*, b],
            Lexer::T_PIPE     => true, // foo.* | [0],
        );

        $peek = $this->matchPeek($nextTypes);

        // Create a bytecode loop
        $this->stack[] = array('each', null, $type);
        $index = count($this->stack) - 1;
        $this->consumeWildcard($peek);
        $this->stack[$index][1] = count($this->stack) + 1;
        $this->stack[] = array('jump', $index);
    }

    /**
     * Consume wildcard tokens until a scope changing token is found
     */
    private function consumeWildcard(array $peek)
    {
        $until = self::$scope;
        // Don't continue the original projection in a subprojection for "[]"
        $until[Lexer::T_MERGE] = true;
        $this->stack[] = array('mark_current');

        while (!isset($until[$peek['type']])) {
            $token = $this->nextToken();
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
     * Parse a multi-brace expression key value pair
     *
     * @param string $type Valid types for values (array or object)
     * @throws SyntaxErrorException
     */
    private function parseKeyValuePair($type)
    {
        $keyToken = $this->match(array(Lexer::T_IDENTIFIER => true));
        $this->match(array(Lexer::T_COLON => true));

        // Requires at least one value that can start an expression, and
        // don't allow number indices on objects or strings on arrays
        $valid = self::$exprTokens;
        if ($type == 'array') {
            unset($valid[Lexer::T_IDENTIFIER]);
        } elseif ($type == 'object') {
            unset($valid[Lexer::T_NUMBER]);
        }
        $token = $this->match($valid);

        if (!isset(self::$noPushTokens[$token['type']])) {
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

    private function parse_T_MERGE(array $token)
    {
        $this->stack[] = array('merge');
        $peek = $this->peek();

        // Short circuit the projection loop for specific scope changing tokens
        if (!isset(self::$scope[$peek['type']])) {
            $this->parse_T_STAR($token, 'array');
        }
    }

    private function parse_T_PIPE(array $token)
    {
        $this->stack[] = array('pop_current');
        $this->stack[] = array('mark_current');
    }

    private function parse_T_LBRACKET(array $token)
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // [a, b]
            Lexer::T_NUMBER     => true, // [0]
            Lexer::T_STAR       => true, // [*]
            Lexer::T_LBRACKET   => true, // foo[[0], [1]]
            Lexer::T_RBRACKET   => true, // foo[]
            Lexer::T_LITERAL    => true, // foo[_true, bar]
            Lexer::T_FUNCTION   => true, // foo[count(@)]
            Lexer::T_FILTER     => true, // foo[[?bar = 10], baz]
        );

        $peek = $this->matchPeek($nextTypes);
        $fromType = $this->previousType();

        // Parse simple expressions like [10] or [*]
        $nextTwo = $this->peek(2);
        if ($nextTwo['type'] == Lexer::T_RBRACKET &&
            ($peek['type'] == Lexer::T_NUMBER || $peek['type'] == Lexer::T_STAR)
        ) {
            if ($peek['type'] == Lexer::T_NUMBER) {
                if ($fromType == 'object') {
                    throw $this->syntax('Cannot access object keys using Number indices');
                }
                $this->parse_T_NUMBER($this->nextToken());
                $this->nextToken();
            } elseif ($fromType == 'object') {
                throw $this->syntax('Invalid object wildcard syntax');
            } else {
                $token = $this->nextToken();
                $this->nextToken();
                $this->parse_T_STAR($token, 'array');
            }
            return;
        }

        // Speculatively parse as a multi-bracket expression
        $this->parseMultiBracket($fromType);
    }

    private function parse_T_FILTER(array $token)
    {
        // Create a bytecode loop
        $this->stack[] = array('each', null);
        $loopIndex = count($this->stack) - 1;
        $this->stack[] = array('mark_current');
        $this->parseFullExpression();

        // If the evaluated filter was true, then jump to the wildcard loop
        $this->stack[] = array('pop_current');
        $this->stack[] = array('jump_if_true', count($this->stack) + 4);

        // Kill temp variables when a filter filters a node
        $this->stack[] = array('pop');
        $this->stack[] = array('push', null);
        $this->stack[] = array('jump', $loopIndex);

        // Actually yield values that matched the filter
        $this->match(array(Lexer::T_RBRACKET => true));
        $this->consumeWildcard($this->peek());

        // Finish the projection loop
        $this->stack[] = array('jump', $loopIndex);
        $this->stack[$loopIndex][1] = count($this->stack);
    }

    /**
     * Parses a multi-select expression
     *
     * @param string $type Valid key types (array or object)
     */
    private function parseMultiBracket($type)
    {
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
    }

    /**
     * Parse a multi-bracket expression list element while only allowing
     * certain key types.
     *
     * @param string $type Valid key types (array or object)
     * @throws SyntaxErrorException
     */
    private function parseMultiBracketElement($type)
    {
        // Don't allow Number indices on objects or strings on arrays
        $valid = self::$exprTokens;
        if ($type == 'array') {
            unset($valid[Lexer::T_IDENTIFIER]);
        } elseif ($type == 'object') {
            unset($valid[Lexer::T_NUMBER]);
        }
        $token = $this->match($valid);

        // Push the current node onto the stack if the token is not a function
        if (!isset(self::$noPushTokens[$token['type']])) {
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
     * Parses a function and its arguments
     *
     * @param array $func Function token being parsed
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
     * is encountered.
     *
     * @param array $breakOn Stops parsing when one of these keys are hit
     *
     * @return array Returns the next peek token
     * @throws SyntaxErrorException
     */
    private function parseFunctionArgumentOrLrExpression(array $breakOn)
    {
        $peek = $this->matchPeek(self::$exprTokens);

        // Functions arguments and LR exprs operate on the current node
        if (!isset(self::$noPushTokens[$peek['type']])) {
            $this->stack[] = array('push_current');
        }

        $inNode = $peek['type'] == Lexer::T_IDENTIFIER ||
            $peek['type'] == Lexer::T_FUNCTION ||
            $peek['type'] == Lexer::T_LBRACKET ||
            $peek['type'] == Lexer::T_LBRACE ||
            $peek['type'] == Lexer::T_FILTER ||
            $peek['type'] == Lexer::T_AT;

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

        // Parse the right hand part of the expression until a T_RPARENS
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
