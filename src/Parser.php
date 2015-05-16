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
    private $tokens;
    private $token;
    private $tpos;
    private $expression;
    private static $nullToken = ['type' => 'eof'];
    private static $currentNode = ['type' => 'current'];

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
        'colon'             => 0,
        'pipe'              => 1,
        'comparator'        => 2,
        'or'                => 5,
        'flatten'           => 6,
        'star'              => 20,
        'filter'            => 21,
        'dot'               => 40,
        'lbrace'            => 50,
        'lbracket'          => 55,
        'lparen'            => 60,
    ];

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
    public function __construct(Lexer $lexer = null)
    {
        $this->lexer = $lexer ?: new Lexer();
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
        $this->expression = $expression;
        $this->tokens = $this->lexer->tokenize($expression);
        $this->tpos = -1;
        $this->next();
        $result = $this->expr();

        if ($this->token['type'] === 'eof') {
            return $result;
        }

        throw $this->syntax('Did not reach the end of the token stream');
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
        $left = $this->{"nud_{$this->token['type']}"}();
        while ($rbp < self::$bp[$this->token['type']]) {
            $left = $this->{"led_{$this->token['type']}"}($left);
        }

        return $left;
    }

    private function nud_identifier()
    {
        $token = $this->token;
        $this->next();
        return ['type' => 'field', 'value' => $token['value']];
    }

    private function nud_quoted_identifier()
    {
        $token = $this->token;
        $this->next();
        $this->assertNotToken('lparen');
        return ['type' => 'field', 'value' => $token['value']];
    }

    private function nud_current()
    {
        $this->next();
        return self::$currentNode;
    }

    private function nud_literal()
    {
        $token = $this->token;
        $this->next();
        return ['type' => 'literal', 'value' => $token['value']];
    }

    private function nud_expref()
    {
        $this->next();
        return ['type' => 'expref', 'children' => [$this->expr(self::$bp['expref'])]];
    }

    private function nud_lbrace()
    {
        static $validKeys = ['quoted_identifier' => true, 'identifier' => true];
        $this->next($validKeys);
        $pairs = [];

        do {
            $pairs[] = $this->parseKeyValuePair();
            if ($this->token['type'] == 'comma') {
                $this->next($validKeys);
            }
        } while ($this->token['type'] !== 'rbrace');

        $this->next();

        return['type' => 'multi_select_hash', 'children' => $pairs];
    }

    private function nud_flatten()
    {
        return $this->led_flatten(self::$currentNode);
    }

    private function nud_filter()
    {
        return $this->led_filter(self::$currentNode);
    }

    private function nud_star()
    {
        return $this->parseWildcardObject(self::$currentNode);
    }

    private function nud_lbracket()
    {
        $this->next();
        $type = $this->token['type'];
        if ($type == 'number' || $type == 'colon') {
            return $this->parseArrayIndexExpression();
        } elseif ($type == 'star' && $this->lookahead() == 'rbracket') {
            return $this->parseWildcardArray();
        } else {
            return $this->parseMultiSelectList();
        }
    }

    private function led_lbracket(array $left)
    {
        static $nextTypes = ['number' => true, 'colon' => true, 'star' => true];
        $this->next($nextTypes);
        switch ($this->token['type']) {
            case 'number':
            case 'colon':
                return [
                    'type' => 'subexpression',
                    'children' => [$left, $this->parseArrayIndexExpression()]
                ];
            default:
                return $this->parseWildcardArray($left);
        }
    }

    private function led_flatten(array $left)
    {
        $this->next();

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
        $this->next(self::$afterDot);

        if ($this->token['type'] == 'star') {
            return $this->parseWildcardObject($left);
        }

        return [
            'type'     => 'subexpression',
            'children' => [$left, $this->parseDot(self::$bp['dot'])]
        ];
    }

    private function led_or(array $left)
    {
        $this->next();
        return [
            'type'     => 'or',
            'children' => [$left, $this->expr(self::$bp['or'])]
        ];
    }

    private function led_pipe(array $left)
    {
        $this->next();
        return [
            'type'     => 'pipe',
            'children' => [$left, $this->expr(self::$bp['pipe'])]
        ];
    }

    private function led_lparen(array $left)
    {
        $args = [];
        $this->next();

        while ($this->token['type'] != 'rparen') {
            $args[] = $this->expr(0);
            if ($this->token['type'] == 'comma') {
                $this->next();
            }
        }

        $this->next();

        return [
            'type'     => 'function',
            'value'    => $left['value'],
            'children' => $args
        ];
    }

    private function led_filter(array $left)
    {
        $this->next();
        $expression = $this->expr();
        if ($this->token['type'] != 'rbracket') {
            throw $this->syntax('Expected a closing rbracket for the filter');
        }

        $this->next();
        $rhs = $this->parseProjection(self::$bp['filter']);

        return [
            'type'       => 'projection',
            'from'       => 'array',
            'children'   => [
                $left ?: self::$currentNode,
                [
                    'type' => 'condition',
                    'children' => [$expression, $rhs]
                ]
            ]
        ];
    }

    private function led_comparator(array $left)
    {
        $token = $this->token;
        $this->next();

        return [
            'type'     => 'comparator',
            'value'    => $token['value'],
            'children' => [$left, $this->expr()]
        ];
    }

    private function parseProjection($bp)
    {
        $type = $this->token['type'];
        if (self::$bp[$type] < 10) {
            return self::$currentNode;
        } elseif ($type == 'dot') {
            $this->next(self::$afterDot);
            return $this->parseDot($bp);
        } elseif ($type == 'lbracket' || $type == 'filter') {
            return $this->expr($bp);
        }

        throw $this->syntax('Syntax error after projection');
    }

    private function parseDot($bp)
    {
        if ($this->token['type'] == 'lbracket') {
            $this->next();
            return $this->parseMultiSelectList();
        }

        return $this->expr($bp);
    }

    private function parseKeyValuePair()
    {
        static $validColon = ['colon' => true];
        $key = $this->token['value'];
        $this->next($validColon);
        $this->next();

        return [
            'type'     => 'key_val_pair',
            'value'    => $key,
            'children' => [$this->expr()]
        ];
    }

    private function parseWildcardObject(array $left = null)
    {
        $this->next();

        return [
            'type'     => 'projection',
            'from'     => 'object',
            'children' => [
                $left ?: self::$currentNode,
                $this->parseProjection(self::$bp['star'])
            ]
        ];
    }

    private function parseWildcardArray(array $left = null)
    {
        static $getRbracket = ['rbracket' => true];
        $this->next($getRbracket);
        $this->next();

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
        $expected = $matchNext;

        do {
            if ($this->token['type'] == 'colon') {
                $pos++;
                $expected = $matchNext;
            } elseif ($this->token['type'] == 'number') {
                $parts[$pos] = $this->token['value'];
                $expected = ['colon' => true, 'rbracket' => true];
            }
            $this->next($expected);
        } while ($this->token['type'] != 'rbracket');

        // Consume the closing bracket
        $this->next();

        if ($pos === 0) {
            // No colons were found so this is a simple index extraction
            return ['type' => 'index', 'value' => $parts[0]];
        }

        if ($pos > 2) {
            throw $this->syntax('Invalid array slice syntax: too many colons');
        }

        // Sliced array from start (e.g., [2:])
        return [
            'type'     => 'projection',
            'from'     => 'array',
            'children' => [
                ['type' => 'slice', 'value' => $parts],
                $this->parseProjection(self::$bp['star'])
            ]
        ];
    }

    private function parseMultiSelectList()
    {
        $nodes = [];

        do {
            $nodes[] = $this->expr();
            if ($this->token['type'] == 'comma') {
                $this->next();
                $this->assertNotToken('rbracket');
            }
        } while ($this->token['type'] != 'rbracket');
        $this->next();

        return ['type' => 'multi_select_list', 'children' => $nodes];
    }

    private function syntax($msg)
    {
        return new SyntaxErrorException($msg, $this->token, $this->expression);
    }

    private function lookahead()
    {
        return (!isset($this->tokens[$this->tpos + 1]))
            ? 'eof'
            : $this->tokens[$this->tpos + 1]['type'];
    }

    private function next(array $match = null)
    {
        if (!isset($this->tokens[$this->tpos + 1])) {
            $this->token = self::$nullToken;
        } else {
            $this->token = $this->tokens[++$this->tpos];
        }

        if ($match && !isset($match[$this->token['type']])) {
            throw $this->syntax($match);
        }
    }

    private function assertNotToken($type)
    {
        if ($this->token['type'] == $type) {
            throw $this->syntax("Token {$this->tpos} not allowed to be $type");
        }
    }

    /**
     * @internal Handles undefined tokens without paying the cost of validation
     */
    public function __call($method, $args)
    {
        $prefix = substr($method, 0, 4);
        if ($prefix == 'nud_' || $prefix == 'led_') {
            $token = substr($method, 4);
            $message = "Unexpected \"$token\" token ($method). Expected one of"
                . " the following tokens: "
                . implode(', ', array_map(function ($i) {
                    return '"' . substr($i, 4) . '"';
                }, array_filter(
                    get_class_methods($this),
                    function ($i) use ($prefix) {
                        return strpos($i, $prefix) === 0;
                    }
                )));
            throw $this->syntax($message);
        }

        throw new \BadMethodCallException("Call to undefined method $method");
    }
}
