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

    /** @var array Stack of marked tokens for speculative parsing */
    private $markedTokens = array();

    /** @var array First acceptable token */
    private static $firstTokens = array(
        Lexer::T_IDENTIFIER => true,
        Lexer::T_NUMBER => true,
        Lexer::T_STAR => true,
        Lexer::T_LBRACKET => true,
        Lexer::T_LBRACE => true,
        Lexer::T_EOF => true,
        Lexer::T_FUNCTION => true
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
        $this->stack = $this->markedTokens = array();
        $this->lexer->setInput($path);
        $this->tokens = $this->lexer->getIterator();
        $token = $this->tokens->current();

        // Ensure that the first token is valid
        if (!isset(self::$firstTokens[$token['type']])) {
            $this->throwSyntax(self::$firstTokens);
        }

        while ($token['type'] !== Lexer::T_EOF) {
            $token = $this->parseInstruction($token);
        }

        $this->stack[] = array('stop');

        return $this->stack;
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
     * Throw a syntax error exception for the current token
     *
     * @param $messageOrTypes
     *
     * @throws SyntaxErrorException
     */
    private function throwSyntax($messageOrTypes)
    {
        throw new SyntaxErrorException(
            $messageOrTypes,
            $this->tokens->current(),
            $this->lexer->getInput()
        );
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
        $this->validate($types, $token = $this->nextToken());

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
        $this->validate($types, $token = $this->peek());

        return $token;
    }

    /**
     * Match a token against one or more types
     *
     * @param array $types Type(s) to match
     * @param array $token Token to match
     *
     * @return array Returns a token
     * @throws SyntaxErrorException
     */
    private function validate(array $types, array $token)
    {
        if (!isset($types[$token['type']])) {
            $this->throwSyntax($types);
        }
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
        list($position, $stack) = array_pop($this->markedTokens);

        if (!$success) {
            $this->tokens->seek($position);
            $this->stack = $stack;
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
            $this->throwSyntax('No matching opcode for ' . $token['type']);
        }

        return $this->{$method}($token) ?: $this->nextToken();
    }

    private function parse_T_IDENTIFIER(array $token)
    {
        $this->stack[] = array('field', $token['value']);
    }

    private function parse_T_NUMBER(array $token)
    {
        $this->stack[] = array('index', (int) $token['value']);
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

        $next = $this->match($expectedAfterDot);
        if ($next['type'] == Lexer::T_NUMBER) {
            // Handle cases like foo.-1
            $this->parse_T_IDENTIFIER($next);
        } elseif ($next['type'] == Lexer::T_LBRACKET) {
            return $this->parse_T_LBRACKET($next);
        } elseif ($next['type'] == Lexer::T_LBRACE) {
            $this->parse_T_LBRACE($next);
        } else {
            return $next;
        }
    }

    /**
     * Parses an OR expression using a jump_if_true opcode. Parses tokens until
     * a scope change (COMMA, OR, RBRACE, RBRACKET, or EOF) token is found.
     */
    private function parse_T_OR(array $token)
    {
        // Parse until the next terminal condition
        $token = $this->match(self::$firstTokens);
        $this->stack[] = array('is_null');
        $this->stack[] = array('jump_if_false', null);
        $index = count($this->stack) - 1;

        // Pop the empty variable at TOS
        $this->stack[] = array('pop');

        // Push the current node onto the stack if needed
        if ($token['type'] != Lexer::T_FUNCTION) {
            $this->stack[] = array('push_current');
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
    private function parse_T_STAR(array $token, $type = 'object')
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
        $this->stack[] = array('each', null, $type);
        $index = count($this->stack) - 1;
        $token = $this->consumeWildcard($token);
        $this->stack[$index][1] = count($this->stack) + 1;
        $this->stack[] = array('jump', $index);

        return $token;
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
        $token = $this->nextToken();
        $inNode = false;

        if ($token['type'] != Lexer::T_RPARENS) {
            $found++;
            do {
                switch ($token['type']) {
                    case Lexer::T_EOF:
                        $this->throwSyntax('Expected T_RPARENS');
                    case Lexer::T_COMMA:
                        $found++;
                        $inNode = false;
                        $token = $this->nextToken();
                        break;
                    case Lexer::T_FUNCTION:
                        $token = $this->parseInstruction($token);
                        break;
                    case Lexer::T_AT:
                        $token = $this->nextToken();
                        $this->stack[] = array('push_current');
                        $inNode = true;
                        break;
                    default:
                        if ($inNode) {
                            $token = $this->parseInstruction($token);
                        } else {
                            $this->stack[] = array('push', $token['value']);
                            $token = $this->nextToken();
                        }
                }
            } while ($token['type'] != Lexer::T_RPARENS);
        }

        $this->stack[] = array('call', $func['value'], $found);
    }

    /**
     * Consume wildcard tokens until a scope change
     *
     * @param array $token Current token being parsed
     *
     * @return array Returns the next token
     */
    private function consumeWildcard(array $token)
    {
        $this->stack[] = array('mark_current');
        while (!isset(self::$scope[$token['type']])) {
            // Don't continue the original project in a subprojection for "[]"
            $peek = $this->peek();
            if ($token['type'] == Lexer::T_LBRACKET && $peek['type'] == Lexer::T_RBRACKET) {
                break;
            }
            $token = $this->parseInstruction($token);
        }
        $this->stack[] = array('pop_current');

        return $token;
    }

    private function parse_T_LBRACKET(array $token)
    {
        static $expectedAfter = array(
            Lexer::T_IDENTIFIER => true,
            Lexer::T_NUMBER => true,
            Lexer::T_STAR => true,
            Lexer::T_RBRACKET => true,
            Lexer::T_PRIMITIVE => true,
            Lexer::T_FUNCTION => true,
            Lexer::T_AT => true
        );

        $token = $this->match($expectedAfter);

        // Don't JmesForm the data into a split array
        if ($token['type'] == Lexer::T_RBRACKET) {
            $this->stack[] = array('merge');
            return $this->parse_T_STAR($token, 'array');
        }

        $value = $token['value'];
        $nextToken = $this->peek();

        // Parse simple expressions like [10] or [*]
        if ($nextToken['type'] == Lexer::T_RBRACKET) {
            // A simple extraction. Skip the RBRACKET
            if ($token['type'] == Lexer::T_NUMBER) {
                $this->nextToken();
                $this->stack[] = array('index', (int) $value);
                return null;
            } elseif ($token['type'] == Lexer::T_STAR) {
                $this->nextToken();
                return $this->parse_T_STAR($token, 'array');
            }
        }

        if ($this->speculateMultiBracket($token)) {
            return null;
        } elseif ($token = $this->speculateFilter($token)) {
//            var_export($token);
  //          die();
            return $token;
        } else {
            $this->throwSyntax('Expression is not a multi-expression or a filter expression. No viable rule found');
        }
    }

    private function parse_T_LBRACE(array $token)
    {
        $token = $this->match(array(Lexer::T_IDENTIFIER => true, Lexer::T_NUMBER => true));
        $this->parseMultiBrace($token);
    }

    private function parseMultiBracket(array $token)
    {
        $index = $this->prepareMultiBranch();

        if ($token['type'] != Lexer::T_FUNCTION) {
            $this->stack[] = array('push_current');
        }

        do {
            if ($token['type'] != Lexer::T_COMMA) {
                $token = $this->parseInstruction($token);
            } else {
                $this->storeMultiBranchKey(null);
                $next = $this->match(self::$firstTokens);
                if ($next['type'] != Lexer::T_FUNCTION) {
                    $this->stack[] = array('push_current');
                }
                $token = $this->parseInstruction($next);
            }
        } while ($token['type'] != Lexer::T_RBRACKET);

        $this->finishMultiBranch($index, null);
    }

    private function parseMultiBrace(array $token)
    {
        $index = $this->prepareMultiBranch();
        $currentKey = $token['value'];
        $this->match(array(Lexer::T_COLON => true));
        $token = $this->match(self::$firstTokens);

        if ($token['type'] != Lexer::T_FUNCTION) {
            $this->stack[] = array('push_current');
        }

        do {
            if ($token['type'] != Lexer::T_COMMA) {
                $token = $this->parseInstruction($token);
            } else {
                $this->storeMultiBranchKey($currentKey);
                $token = $this->match(array(Lexer::T_IDENTIFIER => true));
                $this->match(array(Lexer::T_COLON => true));
                $currentKey = $token['value'];
                // Parse the next instruction and handle TOS if not a function
                $next = $this->match(self::$firstTokens);
                if ($next['type'] != Lexer::T_FUNCTION) {
                    $this->stack[] = array('push_current');
                }
                $token = $this->parseInstruction($next);
            }
        } while ($token['type'] != Lexer::T_RBRACE);

        $this->finishMultiBranch($index, $currentKey);
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
     * @param int         $index Index to update for the pre-jump instruction
     * @param string|null $key   Key used to store the last result value
     */
    private function finishMultiBranch($index, $key)
    {
        $this->stack[] = array('store_key', $key);
        $this->stack[] = array('pop_current');
        $this->stack[$index][1] = count($this->stack);
    }

    /**
     * Determines if the expression in a bracket is a multi-select
     *
     * @param array $token Left node in the expression
     *
     * @return bool Returns true if this is a multi-select or false if not
     */
    private function speculateMultiBracket(array $token)
    {
        $this->markToken();
        $success = true;
        try {
            $this->parseMultiBracket($token);
        } catch (SyntaxErrorException $e) {
            $success = false;
        }

        $this->resetToken($success);

        return $success;
    }

    /**
     * Determines if the expression in a bracket is a filter
     *
     * @param array $token Left node in the expression
     *
     * @return bool Returns true if this is a filter or false if not
     * @throws SyntaxErrorException
     */
    private function speculateFilter(array $token)
    {
        $this->markToken();

        // Create a bytecode loop
        $this->stack[] = array('each', null);
        $loopIndex = count($this->stack) - 1;
        $this->stack[] = array('mark_current');

        try {
            $this->parseFullExpression($token);
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
        $token = $this->consumeWildcard($this->nextToken());
        // Finish the projection loop
        $this->stack[] = array('jump', $loopIndex);
        $this->stack[$loopIndex][1] = count($this->stack);

        // Stop the token marking
        $this->resetToken(true);

        return $token;
    }

    /**
     * Parse an entire filter expression including the left, operator, and right
     */
    private function parseFullExpression(array $token)
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
        $operatorToken = $this->parseFilterExpression($token, array(Lexer::T_OPERATOR => true));

        // Parse the right hand part of the expression until a T_RBRACKET
        $afterExpression = $this->parseFilterExpression($this->nextToken(), array(
            Lexer::T_RBRACKET => true,
            Lexer::T_OR => true
        ));

        // Add the operator opcode and track the jump if false index
        if (isset($operators[$operatorToken['value']])) {
            $this->stack[] = array($operators[$operatorToken['value']]);
        } else {
            $this->throwSyntax('Invalid operator');
        }

        if ($afterExpression['type'] == Lexer::T_OR) {
            $token = $this->match(self::$firstTokens);
            $this->stack[] = array('is_falsey');
            $this->stack[] = array('jump_if_false', null);
            $index = count($this->stack) - 1;
            $this->stack[] = array('pop');
            $this->parseFullExpression($token);
            $this->stack[$index][1] = count($this->stack);
        }
    }

    /**
     * Parse either the left or right part of a filter expression until a
     * specific node is encountered.
     *
     * @param array $token     Starting token
     * @param array $untilTypes Parse until a token of this type is encountered
     * @return array Returns the last token
     *
     * @throws SyntaxErrorException When EOF is encountered before the "until"
     */
    private function parseFilterExpression(array $token, array $untilTypes)
    {
        $inNode = false;

        do {
            switch ($token['type']) {
                case Lexer::T_FUNCTION:
                    $this->parse_T_FUNCTION($token);
                    $token = $this->nextToken();
                    break;
                case Lexer::T_EOF:
                    $this->throwSyntax('Invalid expression');
                case Lexer::T_AT:
                    $token = $this->nextToken();
                    $this->stack[] = array('push_current');
                    $inNode = true;
                    break;
                default:
                    if ($inNode) {
                        $token = $this->parseInstruction($token);
                    } else {
                        $this->stack[] = array('push', $token['value']);
                        $token = $this->nextToken();
                    }
            }
        } while (!isset($untilTypes[$token['type']]));

        return $token;
    }
}
