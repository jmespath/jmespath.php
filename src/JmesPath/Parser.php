<?php

namespace JmesPath;

/**
 * LL(1) recursive descent JMESPath parser utilizing a Pratt based parser
 */
class Parser implements ParserInterface
{
    /** @var LexerInterface */
    private $lexer;

    /** @var array Bytecode stack */
    private $stack;

    /** @var array Stack of ParseState objects */
    private $state;

    /** @var TokenStream Stream of tokens */
    private $tokens;

    /** @var array Store common opcodes as statics for performance */
    private static $popCurrent = array('pop_current');
    private static $pushCurrent = array('push_current');
    private static $markCurrent = array('mark_current');

    /** @var array First acceptable token */
    private static $exprTokens = array(
        Lexer::T_IDENTIFIER => true,
        Lexer::T_STAR       => true,
        Lexer::T_LBRACKET   => true,
        Lexer::T_LBRACE     => true,
        Lexer::T_FUNCTION   => true,
        Lexer::T_LITERAL    => true,
        Lexer::T_MERGE      => true,
        Lexer::T_AT         => true,
    );

    private static $precedence = array(
        Lexer::T_IDENTIFIER => 0,
        Lexer::T_DOT        => 0,
        Lexer::T_STAR       => 0,
        Lexer::T_LPARENS    => 0,
        Lexer::T_LITERAL    => 0,
        Lexer::T_OPERATOR   => 0,
        Lexer::T_FUNCTION   => 0,
        Lexer::T_FILTER     => 0,
        Lexer::T_LBRACKET   => 0,
        Lexer::T_LBRACE     => 0,
        Lexer::T_AT         => 0,
        Lexer::T_MERGE      => 1,
        Lexer::T_OR         => 2,
        Lexer::T_RBRACKET   => 3,
        Lexer::T_RBRACE     => 3,
        Lexer::T_COMMA      => 3,
        Lexer::T_RPARENS    => 3,
        Lexer::T_PIPE       => 4,
        Lexer::T_EOF        => 99
    );

    /**
     * @param LexerInterface $lexer Lexer used to tokenize expressions
     */
    public function __construct(LexerInterface $lexer)
    {
        $this->lexer = $lexer;
    }

    public function compile($expression)
    {
        static $stopInstruction = array('stop');

        $this->stack = $this->state = array();

        if ($expression) {
            $this->tokens = $this->lexer->tokenize($expression);
            $this->pushState();
            $this->tokens->match(self::$exprTokens);
            $this->parseExpression(999);
            $this->popState();
        }

        $this->stack[] = $stopInstruction;

        return $this->stack;
    }

    /**
     * Throws a SyntaxErrorException for the current token
     *
     * @param string $msg Error message
     * @throws SyntaxErrorException
     */
    private function throwSyntax($msg)
    {
        throw new SyntaxErrorException($msg, $this->tokens->token, (string) $this->tokens);
    }

    private function parseExpression($precedence = 0)
    {
        $this->{'parse_' . $this->tokens->token['type']}();

        while ($this->tokens->token['type'] != Lexer::T_EOF
            && $precedence >= self::$precedence[$this->tokens->token['type']]
        ) {
            $this->{'parse_' . $this->tokens->token['type']}();
        }
    }

    private function parse_T_IDENTIFIER()
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
            Lexer::T_OPERATOR => true, // a = "a"
            Lexer::T_RPARENS  => true, // length(abc)
            Lexer::T_PIPE     => true, // foo.*.a | [0],
            Lexer::T_FILTER   => true, // foo[?baz==`10`]
        );

        $this->stack[] = array('field', $this->tokens->token['value']);
        $state = end($this->state);
        $state->type = 'array';
        $state->push = true;
        $this->tokens->next();
        $this->tokens->match($nextTypes);
    }

    private function parse_T_DOT()
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // foo.bar
            Lexer::T_NUMBER     => true, // foo.-1
            Lexer::T_STAR       => true, // foo.*
            Lexer::T_LBRACE     => true, // foo[1]
            Lexer::T_LBRACKET   => true, // foo{a: 0}
            Lexer::T_FILTER     => true, // foo.[?bar = 10]
        );

        end($this->state)->type = 'object';
        $this->tokens->next();
        $this->tokens->match($nextTypes);
    }

    private function parse_T_STAR()
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

        $state = end($this->state);
        $previousType = $state->type;
        $state->push = true;
        $state->type = 'array';
        $this->tokens->next();
        $this->tokens->match($nextTypes);
        $this->createBytecodeLoop($previousType);
    }

    private function parse_T_OR()
    {
        $this->tokens->next();
        $this->tokens->match(self::$exprTokens);
        $this->stack[] = array('is_null');
        $this->stack[] = array('jump_if_false', null);
        $index = count($this->stack) - 1;
        // Pop the empty variable at TOS
        $this->stack[] = array('pop');
        $this->pushState();
        $this->parseExpression();
        $this->stack[$index][1] = count($this->stack);
        $this->popState();
    }

    private function parse_T_LITERAL()
    {
        $this->stack[] = array('push', $this->tokens->token['value']);
        $this->tokens->next();
    }

    private function parse_T_PIPE()
    {
        $this->stack[] = self::$popCurrent;
        $this->stack[] = self::$markCurrent;
        $this->stack[] = array('pop');
        $this->tokens->next();
        $this->tokens->match(self::$exprTokens);
        $this->pushState();
        $this->parseExpression(3);
        $this->popState();
    }

    private function parse_T_OPERATOR()
    {
        static $operators = array(
            '==' => 'eq',
            '!=' => 'not',
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte'
        );

        $operator = $this->tokens->token['value'];
        $this->tokens->next();
        $this->tokens->match(self::$exprTokens);
        $this->pushState();
        $this->parseExpression();
        $this->popState();
        $this->tokens->next();

        // Add the operator opcode and track the jump if false index
        if (isset($operators[$operator])) {
            $this->stack[] = array($operators[$operator]);
        } else {
            $this->throwSyntax('Invalid operator');
        }
    }

    private function parse_T_FUNCTION()
    {
        $found = 0;
        $fn = $this->tokens->token['value'];
        $this->tokens->next();

        while ($this->tokens->token['type'] !== Lexer::T_RPARENS) {
            $found++;
            $this->pushState();
            $this->parseExpression();
            $this->popState();
            if ($this->tokens->token['type'] == Lexer::T_COMMA) {
                $this->tokens->next();
            }
        }

        $this->stack[] = array('call', $fn, $found);
        $this->tokens->match(array(Lexer::T_RPARENS => true));
        $this->tokens->next();
    }

    private function parse_T_LBRACKET()
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // [a, b]
            Lexer::T_NUMBER     => true, // [0]
            Lexer::T_STAR       => true, // [*]
            Lexer::T_LBRACKET   => true, // foo[[0], [1]]
            Lexer::T_RBRACKET   => true, // foo[]
            Lexer::T_LITERAL    => true, // foo[_true, bar]
            Lexer::T_FUNCTION   => true, // foo[count(@)]
            Lexer::T_FILTER     => true, // foo[[?bar = 10], baz],
            Lexer::T_COLON      => true, // foo[:1],
        );

        $state = end($this->state);
        $fromType = $state->type;
        $state->push = true;
        $this->tokens->next();
        $this->tokens->match($nextTypes);

        if ($this->tokens->token['type'] == Lexer::T_NUMBER ||
            $this->tokens->token['type'] == Lexer::T_COLON
        ) {
            if ($fromType == 'object') {
                $this->throwSyntax('Cannot access object keys using number indices');
            }
            $this->parseArrayIndexExpression();
        } elseif ($this->tokens->token['type'] != Lexer::T_STAR ||
            $fromType == 'object'
        ) {
            $this->parseMultiBracket($fromType);
        } else {
            $this->tokens->next();
            $this->tokens->match(array(Lexer::T_RBRACKET => true));
            $this->tokens->next();
            end($this->state)->type = 'array';
            $this->createBytecodeLoop('array');
        }
    }

    private function parse_T_FILTER()
    {
        $this->stack[] = array('each', null);
        $loopIndex = count($this->stack) - 1;
        $this->stack[] = self::$markCurrent;
        $this->pushState();
        $this->parseExpression(2);
        $this->popState();

        // If the evaluated filter was true, then jump to the wildcard loop
        $this->stack[] = self::$popCurrent;
        $this->stack[] = array('jump_if_true', count($this->stack) + 4);

        // Kill temp variables when a filter filters a node
        $this->stack[] = array('pop');
        $this->stack[] = array('push', null);
        $this->stack[] = array('jump', $loopIndex);

        // Actually yield values that matched the filter
        $this->tokens->next();
        $this->tokens->match(array(Lexer::T_RBRACKET => true));
        $this->tokens->next();
        $this->parseExpression(1);
        $this->tokens->next();

        // Finish the projection loop
        $this->stack[] = array('jump', $loopIndex);
        $this->stack[$loopIndex][1] = count($this->stack);
    }

    private function parse_T_MERGE()
    {
        static $mergeOpcode = array('merge');
        $this->stack[] = $mergeOpcode;
        $this->tokens->next();
        $this->pushState('array', false);
        if ($this->tokens->token['type'] != Lexer::T_EOF) {
            $this->createBytecodeLoop('array');
        }
        $this->popState();
    }

    private function parse_T_LBRACE()
    {
        static $validClosingToken = array(Lexer::T_RBRACE => true);
        static $validNext = array(
            Lexer::T_COMMA => true,
            Lexer::T_RBRACE => true
        );

        $state = end($this->state);
        $fromType = $state->type;
        $state->push = true;
        $this->tokens->next();
        $index = $this->prepareMultiBranch();

        do {
            $this->parseKeyValuePair($fromType);
            $this->tokens->match($validNext);
            if ($this->tokens->token['type'] == Lexer::T_COMMA) {
                $this->tokens->next();
                $this->tokens->match(self::$exprTokens);
            }
        } while ($this->tokens->token['type'] !== Lexer::T_RBRACE);

        $this->tokens->match($validClosingToken);
        $this->finishMultiBranch($index);
        $this->tokens->next();
    }

    private function parse_T_AT()
    {
        end($this->state)->push = true;
        $this->tokens->next();
    }

    private function parse_T_EOF() {}

    private function createBytecodeLoop($previousType)
    {
        $this->stack[] = array('each', null, $previousType);
        $pos = count($this->stack) - 1;

        if ($this->tokens->token['type'] != Lexer::T_RBRACKET &&
            $this->tokens->token['type'] != Lexer::T_PIPE
        ) {
            $this->stack[] = self::$markCurrent;
            $this->parseExpression(0);
            $this->stack[] = self::$popCurrent;
        }

        $this->stack[] = array('jump', $pos);
        $this->stack[$pos][1] = count($this->stack);
    }

    /**
     * @return int Returns the index of the jump bytecode instruction
     */
    private function prepareMultiBranch()
    {
        $this->stack[] = array('is_array');
        $this->stack[] = array('jump_if_false', null);
        $this->stack[] = self::$markCurrent;
        $this->stack[] = array('pop');
        $this->stack[] = array('push', array());

        return count($this->stack) - 4;
    }

    /**
     * @param int $index Index to update for the pre-jump instruction
     */
    private function finishMultiBranch($index)
    {
        $this->stack[] = self::$popCurrent;
        $this->stack[$index][1] = count($this->stack);
    }

    private function parseKeyValuePair($type)
    {
        static $validBegin = array(Lexer::T_IDENTIFIER => true);
        static $validColon = array(Lexer::T_COLON => true);
        $this->tokens->match($validBegin);
        $keyToken = $this->tokens->token;
        $this->tokens->next();
        $this->tokens->match($validColon);
        $this->tokens->next();
        $this->fromType($type);
        $this->pushState();
        $this->parseExpression(2);
        $this->popState();
        $this->stack[] = array('store_key', $keyToken['value']);
    }

    private function parseArrayIndexExpression()
    {
        static $matchNext = array(
            Lexer::T_NUMBER => true,
            Lexer::T_COLON => true,
            Lexer::T_RBRACKET => true
        );

        $pos = 0;
        $parts = array(null, null, null);
        $this->tokens->match($matchNext);

        do {
            if ($this->tokens->token['type'] == Lexer::T_COLON) {
                $pos++;
            } else {
                $parts[$pos] = $this->tokens->token['value'];
            }
            $this->tokens->next();
            $this->tokens->match($matchNext);
        } while ($this->tokens->token['type'] != Lexer::T_RBRACKET);

        // Consume the closing bracket
        $this->tokens->next();

        if ($pos == 0) {
            // No colons were found so this is a simple index extraction
            $this->stack[] = array('index', $parts[0]);
        } elseif ($pos > 2) {
            $this->throwSyntax('Invalid array slice syntax: too many colons');
        } else {
            // Sliced array from start (e.g., [2:])
            $this->stack[] = array('slice', $parts[0], $parts[1], $parts[2]);
        }
    }

    private function parseMultiBracket($fromType)
    {
        $index = $this->prepareMultiBranch();

        do {
            // Parse each comma separated expression until a T_RBRACKET
            $this->pushState($fromType);
            $this->fromType($fromType);
            $this->parseExpression(2);
            $this->popState();
            $this->stack[] = array('store_key', null);

            // Skip commas
            if ($this->tokens->token['type'] == Lexer::T_COMMA) {
                $this->tokens->next();
                $this->tokens->match(self::$exprTokens);
            }

        } while ($this->tokens->token['type'] !== Lexer::T_RBRACKET);

        $this->finishMultiBranch($index);
        $this->tokens->next();
    }

    private function fromType($fromType)
    {
        static $fromArrayTokens, $fromObjectTokens;

        if (!$fromArrayTokens) {
            $fromArrayTokens = $fromObjectTokens = self::$exprTokens;
            unset($fromArrayTokens[Lexer::T_IDENTIFIER]);
            unset($fromObjectTokens[Lexer::T_NUMBER]);
        }

        $this->tokens->match($fromType == 'array' ? $fromArrayTokens : $fromObjectTokens);
    }

    private function pushState($type = false, $needsPush = true)
    {
        if ($needsPush) {
            $this->stack[] = self::$pushCurrent;
        }

        $this->state[] = new ParseState(count($this->stack) - 1, $type, $needsPush);
    }

    private function popState()
    {
        static $patch = array(
            'each' => true,
            'jump' => true,
            'jump_if_true' => true,
            'jump_if_false' => true
        );

        $state = array_pop($this->state);
        if (!$state->needsPush || $state->push) {
            return;
        }

        unset($this->stack[$state->pos]);
        // Reindex the stack
        $this->stack = array_values($this->stack);
        // Move jump positions
        for ($i = $state->pos, $t = count($this->stack) - 1; $i < $t; $i++) {
            if (isset($patch[$this->stack[$i][0]])) {
                $this->stack[$i][1]--;
            }
        }
    }

    /**
     * Handles parsing undefined tokens without paying the cost of validation
     */
    public function __call($method, $args)
    {
        if (substr($method, 0, 6) == 'parse_') {
            $this->throwSyntax('Unexpected token');
        } else {
            trigger_error('Call to undefined method: ' . $method);
        }
    }
}
