<?php

namespace JmesPath;

/**
 * LL(1) recursive descent JMESPath parser utilizing a Pratt parsing technique
 */
class Parser
{
    /** @var LexerInterface */
    private $lexer;

    /** @var TokenStream Stream of tokens */
    private $tokens;

    /** @var array First acceptable token */
    private static $exprTokens = array(
        Lexer::T_FILTER     => true,
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
        Lexer::T_LPARENS    => 0,
        Lexer::T_LITERAL    => 0,
        Lexer::T_FUNCTION   => 0,
        Lexer::T_LBRACKET   => 0,
        Lexer::T_LBRACE     => 0,
        Lexer::T_AT         => 0,
        Lexer::T_COMPARATOR => 2,
        Lexer::T_STAR       => 3,
        Lexer::T_FILTER     => 3,
        Lexer::T_MERGE      => 4,
        Lexer::T_OR         => 5,
        Lexer::T_RBRACKET   => 6,
        Lexer::T_RBRACE     => 6,
        Lexer::T_RPARENS    => 6,
        Lexer::T_COMMA      => 6,
        Lexer::T_PIPE       => 7,
        Lexer::T_EOF        => 99
    );

    /** @var array Cached current AST node */
    private static $currentNode = array('type' => 'current_node');

    /**
     * @param LexerInterface $lexer Lexer used to tokenize expressions
     */
    public function __construct(LexerInterface $lexer)
    {
        $this->lexer = $lexer;
    }

    /**
     * Parses a JMESPath expression into an AST
     *
     * @param string $expression JMESPath expression to compile
     *
     * @return array Returns an array based AST
     * @throws SyntaxErrorException
     */
    public function parse($expression)
    {
        if (!$expression) {
            return array();
        }

        $this->tokens = $this->lexer->tokenize($expression);
        $this->tokens->next(self::$exprTokens);

        return $this->parseExpression(999);
    }

    /**
     * Parses an expression until a token is encountered greater than the
     * provided precedence.
     *
     * @param int $precedence Precedence cut-off
     *
     * @return array
     */
    private function parseExpression($precedence = 0)
    {
        return $this->precedenceParse(
            $precedence,
            $this->{'parse_' . $this->tokens->token['type']}()
        );
    }

    private function precedenceParse($precedence = 0, $left = null)
    {
        $type = $this->tokens->token['type'];
        while ($type != Lexer::T_EOF && $precedence >= self::$precedence[$type]) {
            $left = $this->{'parse_' . $type}($left);
            $type = $type = $this->tokens->token['type'];
        }

        return $left;
    }

    private function parse_T_IDENTIFIER()
    {
        static $nextTypes = array(
            Lexer::T_MERGE      => true, // foo[]
            Lexer::T_LBRACKET   => true, // a[0]
            Lexer::T_RBRACE     => true, // {a: b}
            Lexer::T_RBRACKET   => true, // [a] / foo[a = substring(@, 0, 1)]
            Lexer::T_COMMA      => true, // [a, b]
            Lexer::T_EOF        => true, // foo,
            Lexer::T_DOT        => true, // foo.bar
            Lexer::T_OR         => true, // foo || bar
            Lexer::T_COMPARATOR => true, // a = "a"
            Lexer::T_RPARENS    => true, // length(abc)
            Lexer::T_PIPE       => true, // foo.*.a | [0],
            Lexer::T_FILTER     => true, // foo[?baz==`10`]
        );

        $token = $this->tokens->token;
        $this->tokens->next($nextTypes);

        return array('type' => 'field', 'key' => $token['value']);
    }

    private function parse_T_DOT(array $left = null)
    {
        static $nextTypes = array(
            Lexer::T_IDENTIFIER => true, // foo.bar
            Lexer::T_NUMBER     => true, // foo.-1
            Lexer::T_STAR       => true, // foo.*
            Lexer::T_LBRACE     => true, // foo[1]
            Lexer::T_LBRACKET   => true, // foo{a: 0}
            Lexer::T_FILTER     => true, // foo.[?bar = 10]
        );

        $this->tokens->next($nextTypes);

        // Special handling for expression "." "*"
        if ($this->tokens->token['type'] == Lexer::T_STAR) {
            return $this->parse_T_STAR($left);
        } elseif ($this->tokens->token['type'] == Lexer::T_LBRACKET) {
            // If the next token is "[", then it's a multi-select-list
            $this->tokens->next();
            $next = $this->parseMultiSelectList();
        } else {
            $next = $this->parseExpression();
        }

        // Only create a sub-expression if there is a left node. If there's
        // no left node, then it means this is after projection node.
        if (!$left) {
            return $next;
        }

        return array(
            'type'     => 'subexpression',
            'children' => array($left, $next)
        );
    }

    private function parse_T_STAR(array $left = null)
    {
        static $nextTypes = array(
            Lexer::T_DOT        => true, // *.bar
            Lexer::T_EOF        => true, // foo.*
            Lexer::T_MERGE      => true, // foo.*[]
            Lexer::T_LBRACKET   => true, // foo.*[0]
            Lexer::T_RBRACKET   => true, // foo.[a, b.*]
            Lexer::T_LBRACE     => true, // foo.*{a: 0, b: 1}
            Lexer::T_RBRACE     => true, // foo.{a: a, b: b.*}
            Lexer::T_OR         => true, // foo.* || foo
            Lexer::T_COMMA      => true, // foo.[a.*, b],
            Lexer::T_PIPE       => true, // foo.* | [0],
            Lexer::T_COMPARATOR => true, // foo.* == `[]`
        );

        $this->tokens->next($nextTypes);

        return array(
            'type'     => 'projection',
            'from'     => 'object',
            'children' => array(
                $left ?: self::$currentNode,
                $this->precedenceParse() ?: self::$currentNode
            )
        );
    }

    private function parse_T_MERGE(array $left = null)
    {
        $this->tokens->next();

        return array(
            'type'     => 'projection',
            'from'     => 'array',
            'children' => array(
                array(
                    'type'     => 'merge',
                    'children' => array($left ?: self::$currentNode)
                ),
                $this->precedenceParse(3) ?: self::$currentNode
            )
        );
    }

    private function parse_T_OR(array $left)
    {
        $this->tokens->next(self::$exprTokens);

        return array(
            'type'     => 'or',
            'children' => array($left, $this->parseExpression())
        );
    }

    private function parse_T_LITERAL()
    {
        $token = $this->tokens->token;
        $this->tokens->next();

        return array('type' => 'literal', 'value' => $token['value']);
    }

    private function parse_T_PIPE(array $left)
    {
        $this->tokens->next(self::$exprTokens);

        return array(
            'type'     => 'pipe',
            'children' => array($left, $this->parseExpression(5))
        );
    }

    private function parse_T_COMPARATOR(array $left)
    {
        static $operators = array(
            '==' => true,
            '!=' => true,
            '>'  => true,
            '>=' => true,
            '<'  => true,
            '<=' => true
        );

        $token = $this->tokens->token;
        if (!isset($operators[$token['value']])) {
            $this->throwSyntax('Invalid operator: ' . $token['value']);
        }

        $this->tokens->next(self::$exprTokens);

        return array(
            'type'     => 'comparator',
            'relation' => $token['value'],
            'children' => array($left, $this->parseExpression())
        );
    }

    private function parse_T_FUNCTION()
    {
        $args = array();
        $token = $this->tokens->token;
        $this->tokens->next();

        while ($this->tokens->token['type'] !== Lexer::T_RPARENS) {
            $args[] = $this->parseExpression();
            if ($this->tokens->token['type'] == Lexer::T_COMMA) {
                $this->tokens->next();
            }
        }

        $this->tokens->next();

        return array(
            'type'     => 'function',
            'fn'       => $token['value'],
            'children' => $args
        );
    }

    private function parse_T_AT()
    {
        $this->tokens->next();

        return self::$currentNode;
    }

    private function parse_T_FILTER(array $left = null)
    {
        $this->tokens->next(self::$exprTokens);
        $expression = $this->parseExpression(5);
        if ($this->tokens->token['type'] != Lexer::T_RBRACKET) {
            $this->throwSyntax('Expected a closing T_RBRACKET for the filter');
        }
        $this->tokens->next();

        return array(
            'type'       => 'projection',
            'children'   => array(
                $left ?: self::$currentNode,
                array(
                    'type' => 'condition',
                    'children' => array(
                        $expression,
                        $this->precedenceParse(3) ?: self::$currentNode
                    )
                )
            )
        );
    }

    private function parse_T_LBRACKET(array $left = null)
    {
        static $nextTypes = array(
            Lexer::T_NUMBER     => true, // foo[0]
            Lexer::T_STAR       => true, // foo[*]
            Lexer::T_COLON      => true, // foo[:1]
            Lexer::T_IDENTIFIER => true, // [a, b]
            Lexer::T_LITERAL    => true, // [`true`]
            Lexer::T_FUNCTION   => true, // [count(@)]
            Lexer::T_FILTER     => true, // [[?bar = 10], baz]
        );

        static $nextTypesAfterIdentifier = array(
            Lexer::T_NUMBER     => true, // foo[0]
            Lexer::T_STAR       => true, // foo[*]
            Lexer::T_COLON      => true, // foo[:1]
            Lexer::T_FILTER     => true, // foo[[?bar = 10], baz],
        );

        $this->tokens->next($left ? $nextTypesAfterIdentifier : $nextTypes);
        $type = $this->tokens->token['type'];

        if ($type == Lexer::T_NUMBER || $type == Lexer::T_COLON) {
            $node = $this->parseArrayIndexExpression();
            return $left
                ? array('type' => 'subexpression', 'children' => array($left, $node))
                : $node;
        } elseif ($type !== Lexer::T_STAR) {
            return $this->parseMultiSelectList();
        } else {
            return $this->parseWildcardIndex($left);
        }
    }

    private function parseWildcardIndex(array $left = null)
    {
        static $consumeRbracket = array(Lexer::T_RBRACKET => true);
        $this->tokens->next($consumeRbracket);
        $this->tokens->next();

        return array(
            'type'     => 'projection',
            'from'     => 'array',
            'children' => array(
                $left ?: self::$currentNode,
                $this->precedenceParse(3) ?: self::$currentNode
            )
        );
    }

    /**
     * Parses an array index expression (e.g., [0], [1:2:3]
     */
    private function parseArrayIndexExpression()
    {
        static $matchNext = array(
            Lexer::T_NUMBER   => true,
            Lexer::T_COLON    => true,
            Lexer::T_RBRACKET => true
        );

        $pos = 0;
        $parts = array(null, null, null);

        do {
            if ($this->tokens->token['type'] == Lexer::T_COLON) {
                $pos++;
            } else {
                $parts[$pos] = $this->tokens->token['value'];
            }
            $this->tokens->next($matchNext);
        } while ($this->tokens->token['type'] != Lexer::T_RBRACKET);

        // Consume the closing bracket
        $this->tokens->next();

        if ($pos == 0) {
            // No colons were found so this is a simple index extraction
            return array('type' => 'index', 'index' => $parts[0]);
        } elseif ($pos > 2) {
            $this->throwSyntax('Invalid array slice syntax: too many colons');
        }

        // Sliced array from start (e.g., [2:])
        return array('type' => 'slice', 'args' => $parts);
    }

    /**
     * Parses a multi-select-list expression:
     * multi-select-list = "[" ( expression *( "," expression ) ) "]"
     */
    private function parseMultiSelectList()
    {
        $nodes = array();
        do {
            $nodes[] = $this->parseExpression(5);
            $this->assertNotEof();
            if ($this->tokens->token['type'] == Lexer::T_COMMA) {
                $this->tokens->next(self::$exprTokens);
            }
        } while ($this->tokens->token['type'] !== Lexer::T_RBRACKET);

        $this->tokens->next();

        return array('type' => 'multi_select_list', 'children' => $nodes);
    }

    private function parse_T_LBRACE()
    {
        $this->tokens->next();
        $kvps = array();

        do {
            $kvps[] = $this->parseKeyValuePair();
            if ($this->tokens->token['type'] == Lexer::T_COMMA) {
                $this->tokens->next(self::$exprTokens);
            }
        } while ($this->tokens->token['type'] !== Lexer::T_RBRACE);

        $this->tokens->next();

        return array('type' => 'multi_select_hash', 'children' => $kvps);
    }

    private function parseKeyValuePair()
    {
        static $validColon = array(Lexer::T_COLON => true);
        $keyToken = $this->tokens->token;
        $this->tokens->next($validColon);
        $this->tokens->next();

        return array(
            'type'     => 'key_value_pair',
            'children' => array($this->parseExpression(2)),
            'key'      => $keyToken['value']
        );
    }

    private function assertNotEof()
    {
        if ($this->tokens->token['type'] == Lexer::T_EOF) {
            $this->throwSyntax('Unfinished expression');
        }
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
