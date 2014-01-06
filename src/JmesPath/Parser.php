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

    /** @var array Array of tokens */
    private $tokens;

    /** @var int */
    private $tokenPos;

    /** @var int */
    private $tokenCount;

    /** @var string JMESPath expression */
    private $input;

    /** @var array Known opcodes of the parser */
    private $methods;

    private $fromArrayTokens, $fromObjectTokens;

    /** @var array Null token that is reused over and over */
    private static $nullToken = array('type' => Lexer::T_EOF, 'value' => '');

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

    private static $parselets = array(
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
        $this->methods = array_fill_keys(get_class_methods($this), true);
        $this->fromArrayTokens = $this->fromObjectTokens = self::$exprTokens;
        unset($this->fromArrayTokens[Lexer::T_IDENTIFIER]);
        unset($this->fromObjectTokens[Lexer::T_NUMBER]);
    }

    public function compile($expression)
    {
        static $stopInstruction = array('stop');

        if (!$expression) {
            return array($stopInstruction);
        }

        $this->input = $expression;
        $this->tokens = $this->lexer->tokenize($expression);
        $this->tokenCount = count($this->tokens);
        $this->tokenPos = -1;
        $this->state = array();
        $this->pushState();
        $this->matchPeek(self::$exprTokens);

        while (isset($this->tokens[$this->tokenPos + 1])) {
            $this->parseExpression(3);
        }

        $this->popState();
        $this->stack[] = $stopInstruction;

        return $this->stack;
    }

    /**
     * Throws a SyntaxErrorException for the current token
     *
     * @param array|string $messageOrTypes
     * @throws SyntaxErrorException
     */
    private function throwSyntax($messageOrTypes)
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
    private function matchPeek(array $types)
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
    private function peek($lookAhead = 1)
    {
        $nextPos = $this->tokenPos + $lookAhead;

        return isset($this->tokens[$nextPos])
            ? $this->tokens[$nextPos]
            : self::$nullToken;
    }

    private function getPrecedence()
    {
        $peek = $this->peek();

        return isset(self::$parselets[$peek['type']])
            ? self::$parselets[$peek['type']]
            : 0;
    }

    private function parseExpression($precedence = 0)
    {
        $token = $this->nextToken();

        if (!isset($this->methods[$method = 'parse_' . $token['type']])) {
            $this->throwSyntax('Unexpected token: ' . $token['type']);
        }
        $left = $this->{$method}($token);

        while ($precedence >= $this->getPrecedence() && isset($this->tokens[$this->tokenPos + 1])) {
            $token = $this->nextToken();
            if (!isset($this->methods[$method = 'parse_' . $token['type']])) {
                $this->throwSyntax('Unexpected token: ' . $token['type']);
            }
            $left = $this->{$method}($token, $left);
        }

        return $left;
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
            Lexer::T_OPERATOR => true, // a = "a"
            Lexer::T_RPARENS  => true, // length(abc)
            Lexer::T_PIPE     => true, // foo.*.a | [0],
            Lexer::T_FILTER   => true, // foo[?baz==`10`]
        );

        $this->matchPeek($nextTypes);
        $this->stack[] = array('field', $token['value']);
        $state = end($this->state);
        $state->type = 'array';
        $state->push = true;
    }

    private function parse_T_DOT(array $token)
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // foo.bar
            Lexer::T_NUMBER     => true, // foo.-1
            Lexer::T_STAR       => true, // foo.*
            Lexer::T_LBRACE     => true, // foo[1]
            Lexer::T_LBRACKET   => true, // foo{a: 0}
            Lexer::T_FILTER     => true, // foo.[?bar = 10]
        );

        $this->matchPeek($nextTypes);
        end($this->state)->type = 'object';
        $this->parseExpression(0);
    }

    private function parse_T_STAR(array $token)
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

        $peek = $this->matchPeek($nextTypes);
        $this->stack[] = array('each', null, $previousType);
        $pos = count($this->stack) - 1;

        if ($peek['type'] != Lexer::T_RBRACKET && $peek['type'] != Lexer::T_PIPE) {
            $this->stack[] = self::$markCurrent;
            $this->parseExpression(0);
            $this->stack[] = self::$popCurrent;
        }

        $this->stack[] = array('jump', $pos);
        $this->stack[$pos][1] = count($this->stack);
    }

    private function parse_T_OR(array $token)
    {
        $this->matchPeek(self::$exprTokens);
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

    private function parse_T_LITERAL(array $token)
    {
        $this->stack[] = array('push', $token['value']);
    }

    private function parse_T_PIPE(array $token)
    {
        $this->stack[] = self::$popCurrent;
        $this->stack[] = self::$markCurrent;
        $this->stack[] = array('pop');
        $this->pushState();
        $this->parseExpression(3);
        $this->popState();
    }

    private function parse_T_OPERATOR(array $token)
    {
        static $operators = array(
            '==' => 'eq',
            '!=' => 'not',
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte'
        );

        $this->pushState();
        $this->parseExpression();
        $this->popState();

        // Add the operator opcode and track the jump if false index
        if (isset($operators[$token['value']])) {
            $this->stack[] = array($operators[$token['value']]);
        } else {
            $this->throwSyntax('Invalid operator');
        }
    }

    private function parse_T_FUNCTION(array $token)
    {
        $found = 0;
        $fn = $token['value'];
        $peek = $this->peek();

        while ($peek['type'] !== Lexer::T_RPARENS) {
            $found++;
            $this->pushState();
            $this->parseExpression();
            $this->popState();
            $peek = $this->peek();
            if ($peek['type'] == Lexer::T_COMMA) {
                $this->match(array(Lexer::T_COMMA => true));
                $peek = $this->peek();
            }
        }

        $this->match(array(Lexer::T_RPARENS => true));
        $this->stack[] = array('call', $fn, $found);
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
            Lexer::T_FILTER     => true, // foo[[?bar = 10], baz],
            Lexer::T_COLON      => true, // foo[:1],
        );

        $state = end($this->state);
        $fromType = $state->type;
        $state->push = true;
        $peek = $this->matchPeek($nextTypes);

        if ($peek['type'] == Lexer::T_NUMBER || $peek['type'] == Lexer::T_COLON) {
            if ($fromType == 'object') {
                $this->throwSyntax('Cannot access object keys using number indices');
            }
            $this->parseArrayIndexExpression();
        } elseif ($peek['type'] != Lexer::T_STAR || $fromType == 'object') {
            $this->parseMultiBracket($fromType);
        } else {
            $this->nextToken();
            $peek = $this->peek();
            if ($peek['type'] == Lexer::T_RBRACKET) {
                end($this->state)->type = 'array';
                $this->parse_T_STAR($this->nextToken());
            }
        }
    }

    private function parse_T_FILTER(array $token)
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
        $this->match(array(Lexer::T_RBRACKET => true));
        $this->parseExpression(1);

        // Finish the projection loop
        $this->stack[] = array('jump', $loopIndex);
        $this->stack[$loopIndex][1] = count($this->stack);
    }

    private function parse_T_MERGE(array $token)
    {
        static $mergeOpcode = array('merge');
        $this->stack[] = $mergeOpcode;
        $peek = $this->peek();
        $this->pushState('array', false);
        if ($peek['type'] != Lexer::T_EOF) {
            $this->parse_T_STAR($token);
        }
        $this->popState();
    }

    private function parse_T_LBRACE(array $token)
    {
        static $validClosingToken = array(Lexer::T_RBRACE => true);
        static $validNext = array(
            Lexer::T_COMMA => true,
            Lexer::T_RBRACE => true
        );

        $state = end($this->state);
        $fromType = $state->type;
        $state->push = true;
        $index = $this->prepareMultiBranch();

        do {
            $this->parseKeyValuePair($fromType);
            $peek = $this->matchPeek($validNext);
            if ($peek['type'] == Lexer::T_COMMA) {
                $this->nextToken();
                $peek = $this->matchPeek(self::$exprTokens);
            }
        } while ($peek['type'] !== Lexer::T_RBRACE);

        $this->match($validClosingToken);
        $this->finishMultiBranch($index);
    }

    private function parse_T_AT(array $token)
    {
        end($this->state)->push = true;
    }

    private function parse_T_EOF(array $token) {}

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
        $keyToken = $this->match($validBegin);
        $this->match($validColon);
        $this->peekFromType($type);
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
        $next = $this->match($matchNext);

        do {
            if ($next['type'] == Lexer::T_COLON) {
                $pos++;
            } else {
                $parts[$pos] = $next['value'];
            }
            $next = $this->match($matchNext);
        } while ($next['type'] != Lexer::T_RBRACKET);

        if ($pos == 0) {
            $this->stack[] = array('index', $parts[0]);
        } elseif ($pos > 2) {
            $this->throwSyntax('Invalid array slice syntax');
        } else {
            // Sliced array from start (e.g., [2:])
            $this->stack[] = array('slice', $parts[0], $parts[1], $parts[2]);
        }
    }

    private function parseMultiBracket($fromType)
    {
        $index = $this->prepareMultiBranch();

        do {
            $this->pushState($fromType);
            $this->peekFromType($fromType);
            $this->parseExpression(2);
            $this->popState();
            $this->stack[] = array('store_key', null);
            $token = $this->peek();
            if ($token['type'] == Lexer::T_COMMA) {
                $this->nextToken();
                $token = $this->matchPeek(self::$exprTokens);
            }
        } while ($token['type'] !== Lexer::T_RBRACKET);
        $this->nextToken();

        $this->finishMultiBranch($index);
    }

    private function peekFromType($fromType)
    {
        return $fromType == 'array'
            ? $this->matchPeek($this->fromArrayTokens)
            : $this->matchPeek($this->fromObjectTokens);
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
}
