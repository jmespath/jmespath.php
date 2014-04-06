<?php

namespace JmesPath;

/**
 * JMESPath Pratt parser
 * @link http://hall.org.ua/halls/wizzard/pdf/Vaughan.Pratt.TDOP.pdf
 */
class Parser
{
    /** @var Lexer */
    private $lexer;

    /** @var TokenStream Stream of tokens */
    private $tokens;

    /** @var array Token binding power */
    private static $bp = [
        'eof'               => 0,
        'quoted_identifier' => 0,
        'identifier'        => 0,
        'rbracket'          => 0,
        'rparen'            => 0,
        'comma'             => 0,
        'rbrace'            => 0,
        'number'            => 0,
        'current'           => 0,
        'expref'            => 0,
        'pipe'              => 1,
        'comparator'        => 2,
        'or'                => 5,
        'flatten'           => 6,
        'star'              => 20,
        'dot'               => 40,
        'lbrace'            => 50,
        'filter'            => 50,
        'lbracket'          => 50,
        'lparen'            => 60,
    ];

    /** @var array Cached current AST node */
    private static $currentNode = ['type' => 'current'];

    /** @var array Acceptable tokens after a dot token */
    private static $afterDot = [
        'identifier'        => true, // foo.bar
        'quoted_identifier' => true, // foo."bar"
        'star'              => true, // foo.*
        'lbrace'            => true, // foo[1]
        'lbracket'          => true, // foo{a: 0}
        'function'          => true, // foo.*.to_string(@)
        'filter'            => true, // foo.[?bar==10]
    ];

    /**
     * @param Lexer $lexer Lexer used to tokenize expressions
     */
    public function __construct(Lexer $lexer)
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
        $this->tokens = $this->lexer->tokenize($expression);
        $this->tokens->next();

        if (!$expression) {
            $this->throwSyntax('Empty expression');
        }

        $result = $this->expr();

        if ($this->tokens->token['type'] != 'eof') {
            $this->throwSyntax('Encountered an unexpected "'
                . $this->tokens->token['type'] . '" token and did not reach'
                . ' the end of the token stream');
        }

        return $result;
    }

    /**
     * Parses an expression while rbp < lbp.
     *
     * @param int   $rbp  Right bound precedence
     *
     * @return array
     */
    private function expr($rbp = 0)
    {
        $left = $this->{"nud_{$this->tokens->token['type']}"}();
        while ($rbp < self::$bp[$this->tokens->token['type']]) {
            $left = $this->{"led_{$this->tokens->token['type']}"}($left);
        }

        return $left;
    }

    private function nud_identifier()
    {
        $token = $this->tokens->token;
        $this->tokens->next();
        return ['type' => 'field', 'key' => $token['value']];
    }

    private function nud_quoted_identifier()
    {
        $token = $this->tokens->token;
        $this->tokens->next();
        if ($this->tokens->token['type'] == 'lparen') {
            $this->throwSyntax(
                'Quoted identifiers are not allowed for function names.'
            );
        }

        return ['type' => 'field', 'key' => $token['value']];
    }

    private function nud_current()
    {
        $this->tokens->next();
        return self::$currentNode;
    }

    private function nud_literal()
    {
        $token = $this->tokens->token;
        $this->tokens->next();
        return ['type' => 'literal', 'value' => $token['value']];
    }

    private function nud_expref()
    {
        $this->tokens->next();

        return array(
            'type'     => 'expression',
            'children' => [$this->expr(2)]
        );
    }

    private function nud_lbrace()
    {
        static $validKeys = ['quoted_identifier' => true, 'identifier' => true];
        $this->tokens->next();
        $pairs = [];

        do {
            $pairs[] = $this->parseKeyValuePair();
            if ($this->tokens->token['type'] == 'comma') {
                $this->tokens->next($validKeys);
            }
        } while ($this->tokens->token['type'] !== 'rbrace');

        $this->tokens->next();

        return['type' => 'multi_select_hash', 'children' => $pairs];
    }

    private function nud_flatten()
    {
        return $this->led_flatten(self::$currentNode);
    }

    private function nud_lbracket()
    {
        $this->tokens->next();
        $type = $this->tokens->token['type'];
        if ($type == 'number' || $type == 'colon') {
            return $this->parseArrayIndexExpression();
        }

        if ($type == 'star') {
            try {
                return $this->parseWildcardIndex();
            } catch (SyntaxErrorException $e) {
                $this->tokens->backtrack();
            }
        }

        return $this->parseMultiSelectList();
    }

    private function led_lbracket(array $left)
    {
        static $nextTypes = ['number' => true, 'colon' => true, 'star' => true];
        $this->tokens->next($nextTypes);
        $type = $this->tokens->token['type'];
        if ($type == 'number' || $type == 'colon') {
            return [
                'type' => 'subexpression',
                'children' => [$left, $this->parseArrayIndexExpression()]
            ];
        } else {
            return $this->parseWildcardIndex($left);
        }
    }

    private function led_flatten(array $left)
    {
        $this->tokens->next();

        return [
            'type'     => 'projection',
            'from'     => 'array',
            'children' => [
                ['type' => 'flatten', 'children' => [$left]],
                $this->parseProjection(self::$bp['flatten'])
            ]
        ];
    }

    private function led_dot(array $left)
    {
        $this->tokens->next(self::$afterDot);

        if ($this->tokens->token['type'] == 'star') {
            $this->tokens->next();
            return [
                'type'     => 'projection',
                'from'     => 'object',
                'children' => [
                    $left,
                    $this->parseProjection(self::$bp['star'])
                ]
            ];
        }

        return [
            'type'     => 'subexpression',
            'children' => [$left, $this->parseDot(self::$bp['dot'])]
        ];
    }

    private function led_or(array $left)
    {
        $this->tokens->next();
        return [
            'type'     => 'or',
            'children' => [$left, $this->expr(self::$bp['or'])]
        ];
    }

    private function led_pipe(array $left)
    {
        $this->tokens->next();
        return [
            'type'     => 'pipe',
            'children' => [$left, $this->expr(self::$bp['pipe'])]
        ];
    }

    private function nud_star()
    {
        $this->tokens->next();

        return [
            'type'     => 'projection',
            'from'     => 'object',
            'children' => [
                self::$currentNode,
                $this->parseProjection(self::$bp['star'])
            ]
        ];
    }

    private function led_lparen(array $left)
    {
        $args = [];
        $name = $left['key'];
        $this->tokens->next();

        while ($this->tokens->token['type'] != 'rparen') {
            $args[] = $this->expr(0);
            if ($this->tokens->token['type'] == 'comma') {
                $this->tokens->next();
            }
        }

        $this->tokens->next();

        return ['type' => 'function', 'fn' => $name, 'children' => $args];
    }

    private function parseProjection($bp)
    {
        $type = $this->tokens->token['type'];
        if (self::$bp[$type] < 10) {
            return self::$currentNode;
        } elseif ($type == 'dot') {
            $this->tokens->next(self::$afterDot);
            return $this->parseDot($bp);
        } elseif ($type == 'lbracket' || $type == 'filter') {
            return $this->expr($bp);
        }

        $this->throwSyntax('Syntax error after projection');
    }

    private function parseDot($bp)
    {
        if ($this->tokens->token['type'] == 'lbracket') {
            $this->tokens->next();
            return $this->parseMultiSelectList();
        }

        return $this->expr($bp);
    }

    private function parseKeyValuePair()
    {
        static $validColon = ['colon' => true];
        $key = $this->tokens->token['value'];
        $this->tokens->next($validColon);
        $this->tokens->next();

        return [
            'type'     => 'key_value_pair',
            'key'      => $key,
            'children' => [$this->expr()]
        ];
    }

    private function nud_filter()
    {
        return $this->led_filter(self::$currentNode);
    }

    private function led_filter(array $left)
    {
        $this->tokens->next();
        $expression = $this->expr();
        if ($this->tokens->token['type'] != 'rbracket') {
            $this->throwSyntax('Expected a closing rbracket for the filter');
        }

        $this->tokens->next();
        $rhs = $this->parseProjection(self::$bp['filter']);

        return [
            'type'       => 'projection',
            'from'       => 'array',
            'children'   => [
                $left ?:self::$currentNode,
                [
                    'type' => 'condition',
                    'children' => [$expression, $rhs]
                ]
            ]
        ];
    }

    private function led_comparator(array $left)
    {
        static $operators = [
            '==' => true,
            '!=' => true,
            '>'  => true,
            '>=' => true,
            '<'  => true,
            '<=' => true
        ];

        $token = $this->tokens->token;
        if (!isset($operators[$token['value']])) {
            $this->throwSyntax('Invalid operator: ' . $token['value']);
        }

        $this->tokens->next();

        return [
            'type'     => 'comparator',
            'relation' => $token['value'],
            'children' => [$left, $this->expr()]
        ];
    }

    private function parseWildcardIndex(array $left = null)
    {
        static $getRbracket = ['rbracket' => true];
        $this->tokens->next($getRbracket);
        $this->tokens->next();

        return [
            'type'     => 'projection',
            'from'     => 'array',
            'children' => [
                $left ?: self::$currentNode,
                $this->parseProjection(self::$bp['star'])
            ]
        ];
    }

    /**
     * Parses an array index expression (e.g., [0], [1:2:3]
     */
    private function parseArrayIndexExpression()
    {
        static $matchNext = [
            'number'   => true,
            'colon'    => true,
            'rbracket' => true
        ];

        $pos = 0;
        $parts = [null, null, null];

        do {
            if ($this->tokens->token['type'] == 'colon') {
                $pos++;
            } else {
                $parts[$pos] = $this->tokens->token['value'];
            }
            $this->tokens->next($matchNext);
        } while ($this->tokens->token['type'] != 'rbracket');

        // Consume the closing bracket
        $this->tokens->next();

        if ($pos == 0) {
            // No colons were found so this is a simple index extraction
            return ['type' => 'index', 'index' => $parts[0]];
        } elseif ($pos > 2) {
            $this->throwSyntax('Invalid array slice syntax: too many colons');
        }

        // Sliced array from start (e.g., [2:])
        return ['type' => 'slice', 'args' => $parts];
    }

    /**
     * Parses a multi-select-list expression:
     * multi-select-list = "[" ( expression *( "," expression ) ) "]"
     */
    private function parseMultiSelectList()
    {
        $nodes = [];

        do {
            $nodes[] = $this->expr();
            if ($this->tokens->token['type'] == 'comma') {
                $this->tokens->next();
                if ($this->tokens->token['type'] == 'rbracket') {
                    $this->throwSyntax('Expected expression, found rbracket');
                }
            }
        } while ($this->tokens->token['type'] != 'rbracket');

        $this->tokens->next();

        return ['type' => 'multi_select_list', 'children' => $nodes];
    }

    /**
     * Throws a SyntaxErrorException for the current token
     *
     * @param string $msg Error message
     * @throws SyntaxErrorException
     */
    private function throwSyntax($msg)
    {
        throw new SyntaxErrorException(
            $msg,
            $this->tokens->token,
            (string) $this->tokens
        );
    }

    /**
     * Handles parsing undefined tokens without paying the cost of validation
     */
    public function __call($method, $args)
    {
        $prefix = substr($method, 0, 4);
        if ($prefix == 'nud_' || $prefix == 'led_') {
            $token = substr($method, 4);
            $this->throwSyntax("Unexpected \"$token\" token ($method)");
        }

        throw new \BadMethodCallException("Call to undefined method $method");
    }
}
