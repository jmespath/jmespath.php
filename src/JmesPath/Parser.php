<?php

namespace JmesPath;

/**
 * LL(1) recursive descent JMESPath parser utilizing a Pratt parser
 */
class Parser implements ParserInterface
{
    /** @var LexerInterface */
    private $lexer;

    /** @var PrattParser */
    private $pratt;

    /** @var array Bytecode stack */
    private $stack;

    /** @var array Stack of ParseState objects */
    private $state;

    /** @var array Store common opcodes as statics for performance */
    private static $popCurrent = array('pop_current');
    private static $pushCurrent = array('push_current');
    private static $markCurrent = array('mark_current');

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

    private static $parselets = array(
        Lexer::T_EOF        => 0,
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
        Lexer::T_MERGE      => 1,
        Lexer::T_RBRACKET   => 1,
        Lexer::T_RBRACE     => 1,
        Lexer::T_COMMA      => 1,
        Lexer::T_RPARENS    => 1,
        Lexer::T_OR         => 1,
        Lexer::T_PIPE       => 2,
    );

    /**
     * @param LexerInterface $lexer Lexer used to tokenize expressions
     */
    public function __construct(LexerInterface $lexer)
    {
        $this->lexer = $lexer;
        $this->pratt = new PrattParser($this->lexer);
        foreach (self::$parselets as $token => $precedence) {
            $this->pratt->register($token, array($this, '_' . $token), $precedence);
        }
    }

    public function compile($expression)
    {
        $this->stack = array(array('push_current'));
        $this->state = array(new ParseState);
        $this->pratt->parse($expression);
        $this->stack[] = array('stop');
        if (!end($this->state)->push) {
            unset($this->stack[0]);
        }

        return $this->stack;
    }

    public function _T_IDENTIFIER(array $token, PrattParser $p)
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

        $p->matchPeek($nextTypes);
        $this->stack[] = array('field', $token['value']);
        end($this->state)->push = true;
    }

    public function _T_DOT(array $token, PrattParser $p)
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // foo.bar
            Lexer::T_NUMBER     => true, // foo.-1
            Lexer::T_STAR       => true, // foo.*
            Lexer::T_LBRACE     => true, // foo[1]
            Lexer::T_LBRACKET   => true, // foo{a: 0}
            Lexer::T_FILTER     => true, // foo.[?bar = 10]
        );
        $p->matchPeek($nextTypes);

        $this->state[] = new ParseState('object');
        $p->parseExpression(0);
        array_pop($this->state);
    }

    public function _T_STAR(array $token, PrattParser $p)
    {
        $this->stack[] = array('each', null, end($this->state)->type);
        $pos = count($this->stack) - 1;
        $this->stack[] = array('mark_current');
        $p->parseExpression(0);
        $this->stack[] = array('pop_current');
        $this->stack[] = array('jump', $pos);
        $this->stack[$pos][1] = count($this->stack);
    }

    public function _T_OR(array $token, PrattParser $p)
    {
        $this->stack[] = array('is_null');
        $this->stack[] = array('jump_if_false', null);
        $index = count($this->stack) - 1;
        // Pop the empty variable at TOS
        $this->stack[] = array('pop');
        $this->stack[] = array('push_current');
        $pos = count($this->stack) - 1;
        $this->state[] = new ParseState;
        $p->parseExpression();
        $this->stack[$index][1] = count($this->stack);
        $state = array_pop($this->state);
        if (!$state->push) {
            unset($this->stack[$pos]);
        }
    }

    public function _T_LITERAL(array $token, PrattParser $p)
    {
        $this->stack[] = array('push', $token['value']);
    }

    public function _T_NUMBER(array $token, PrattParser $p)
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
        $p->matchPeek($nextTypes);
        $this->stack[] = array('index', (int) $token['value']);
    }

    public function _T_PIPE(array $token, PrattParser $p)
    {
        $this->stack[] = array('pop_current');
        $this->stack[] = array('mark_current');
    }

    public function _T_OPERATOR(array $token, PrattParser $p)
    {
        static $operators = array(
            '==' => 'eq',
            '!=' => 'not',
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte'
        );

        $this->stack[] = array('push_current');
        $pos = count($this->stack) - 1;
        $this->state[] = new ParseState();
        $p->parseExpression();
        if (!array_pop($this->state)->push) {
            unset($this->stack[$pos]);
        }

        // Add the operator opcode and track the jump if false index
        if (isset($operators[$token['value']])) {
            $this->stack[] = array($operators[$token['value']]);
        } else {
            $p->throwSyntax('Invalid operator');
        }
    }

    public function _T_FUNCTION(array $token, PrattParser $p)
    {
        $found = 0;
        $fn = $token['value'];
        $peek = $p->peek();

        while ($peek['type'] !== Lexer::T_RPARENS) {
            $found++;
            $this->stack[] = array('push_current');
            $pos = count($this->stack) - 1;
            $this->state[] = new ParseState();
            $p->parseExpression();
            if (!array_pop($this->state)->push) {
                unset($this->stack[$pos]);
            }
            $peek = $p->peek();
            if ($peek['type'] == Lexer::T_COMMA) {
                $p->match(array(Lexer::T_COMMA => true));
                $peek = $p->peek();
            }
        }

        $p->match(array(Lexer::T_RPARENS => true));
        $this->stack[] = array('call', $fn, $found);
    }

    public function _T_LBRACKET(array $token, PrattParser $p)
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

        $fromType = end($this->state)->type;
        $peek = $p->matchPeek($nextTypes);

        if ($peek['type'] == Lexer::T_NUMBER || $peek['type'] == Lexer::T_COLON) {
            if ($fromType == 'object') {
                $p->throwSyntax('Cannot access object keys using number indices');
            }
            $this->parseArrayIndexExpression($p);
        } elseif ($peek['type'] != Lexer::T_STAR || $fromType == 'object') {
            $this->parseMultiBracket($p);
        } else {
            $token = $p->nextToken();
            $peek = $p->peek();
            if ($peek['type'] == Lexer::T_RBRACKET) {
                end($this->state)->type = 'array';
                $p->nextToken();
                $this->T_STAR($token, $p);
            }
        }
    }

    public function _T_FILTER(array $token, PrattParser $p)
    {
        $this->stack[] = array('each', null);
        $loopIndex = count($this->stack) - 1;
        $this->stack[] = self::$markCurrent;

        $this->state[] = new ParseState;
        $this->stack[] = self::$pushCurrent;
        $pos = count($this->stack) - 1;
        $p->parseExpression();
        if (!array_pop($this->state)->push) {
            unset($this->stack[$pos]);
        }

        // If the evaluated filter was true, then jump to the wildcard loop
        $this->stack[] = self::$popCurrent;
        $this->stack[] = array('jump_if_true', count($this->stack) + 4);

        // Kill temp variables when a filter filters a node
        $this->stack[] = array('pop');
        $this->stack[] = array('push', null);
        $this->stack[] = array('jump', $loopIndex);

        // Actually yield values that matched the filter
        $p->match(array(Lexer::T_RBRACKET => true));
        $p->parseExpression();

        // Finish the projection loop
        $this->stack[] = array('jump', $loopIndex);
        $this->stack[$loopIndex][1] = count($this->stack);
    }

    public function _T_MERGE(array $token, PrattParser $p)
    {
        static $mergeOpcode = array('merge');
        $this->stack[] = $mergeOpcode;
        $peek = $p->peek();
        $this->state[] = new ParseState('array');
        if ($peek['type'] != Lexer::T_EOF) {
            $this->T_STAR($token, $p);
        }
        array_pop($this->state);
    }

    public function _T_LBRACE(array $token, PrattParser $p)
    {
        static $validNext = array(
            Lexer::T_COMMA => true,
            Lexer::T_RBRACE => true
        );

        $fromType = end($this->state)->type;
        $index = $this->prepareMultiBranch();

        do {
            $this->parseKeyValuePair($p, $fromType);
            $peek = $p->matchPeek($validNext);
            if ($peek['type'] == Lexer::T_COMMA) {
                $p->nextToken();
                $peek = $p->matchPeek($validNext);
            }
        } while ($peek['type'] !== Lexer::T_RBRACE);

        $p->match(array(Lexer::T_RBRACE => true));
        $this->finishMultiBranch($index);
    }

    public function _T_EOF(array $token, PrattParser $p) {}

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

    private function parseKeyValuePair(PrattParser $p, $type)
    {
        $keyToken = $p->match(array(Lexer::T_IDENTIFIER => true));
        $p->match(array(Lexer::T_COLON => true));

        // Requires at least one value that can start an expression, and
        // don't allow number indices on objects or strings on arrays
        $valid = self::$exprTokens;
        if ($type == 'array') {
            unset($valid[Lexer::T_IDENTIFIER]);
        } elseif ($type == 'object') {
            unset($valid[Lexer::T_NUMBER]);
        }
        $p->matchPeek($valid);

        $this->stack[] = self::$pushCurrent;
        $pos = count($this->stack) - 1;
        $this->state[] = new ParseState($type);
        $p->parseExpression();
        if (!array_pop($this->state)->push) {
            unset($this->stack[$pos]);
        }

        $this->stack[] = array('store_key', $keyToken['value']);
    }

    private function parseArrayIndexExpression(PrattParser $p)
    {
        static $matchNext = array(
            Lexer::T_NUMBER => true,
            Lexer::T_COLON => true,
            Lexer::T_RBRACKET => true
        );

        $pos = 0;
        $parts = array(null, null, null);
        $next = $p->match($matchNext);

        do {
            if ($next['type'] == Lexer::T_COLON) {
                $pos++;
            } else {
                $parts[$pos] = $next['value'];
            }
            $next = $p->match($matchNext);
        } while ($next['type'] != Lexer::T_RBRACKET);

        if ($pos == 0) {
            $this->stack[] = array('index', $parts[0]);
        } elseif ($pos > 2) {
            $p->throwSyntax('Invalid array slice syntax');
        } else {
            // Sliced array from start (e.g., [2:])
            $this->stack[] = array('slice', $parts[0], $parts[1], $parts[2]);
        }
    }

    private function parseMultiBracket(PrattParser $p)
    {
        $index = $this->prepareMultiBranch();

        do {
            $this->stack[] = self::$pushCurrent;
            $this->state[] = new ParseState;
            $pos = count($this->stack) - 1;
            $p->parseExpression();
            if (!array_pop($this->state)->push) {
                unset($this->stack[$pos]);
            }
            $this->stack[] = array('store_key', null);
            $token = $p->peek();
            if ($token['type'] == Lexer::T_COMMA) {
                $p->nextToken();
                $token = $p->peek();
            }
        } while ($token['type'] !== Lexer::T_RBRACKET);
        $p->nextToken();

        $this->finishMultiBranch($index);
    }
}
