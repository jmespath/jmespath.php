<?php

namespace JmesPath;

/**
 * LL(1) recursive descent JMESPath lexer
 */
class Lexer
{
    const T_EOF = 'T_EOF';
    const T_IDENTIFIER = 'T_IDENTIFIER';
    const T_DOT = 'T_DOT';
    const T_STAR = 'T_STAR';
    const T_NUMBER = 'T_NUMBER';
    const T_OR = 'T_OR';
    const T_LBRACKET = 'T_LBRACKET';
    const T_RBRACKET = 'T_RBRACKET';
    const T_COMMA = 'T_COMMA';
    const T_LBRACE = 'T_LBRACE';
    const T_RBRACE = 'T_RBRACE';
    const T_WHITESPACE = 'T_WHITESPACE';
    const T_UNKNOWN = 'T_UNKNOWN';
    const T_COLON = 'T_COLON';
    const T_OPERATOR = 'T_OPERATOR';
    const T_FUNCTION = 'T_FUNCTION';
    const T_LPARENS = 'T_LPARENS';
    const T_RPARENS = 'T_RPARENS';
    const T_MERGE = 'T_MERGE';
    const T_LITERAL = 'T_LITERAL';
    const T_FILTER = 'T_FILTER';
    const T_AT = 'T_AT';

    /** @var array Array of simple matches to token types */
    private $simpleTokens = array(
        ' '  => self::T_WHITESPACE,
        "\n" => self::T_WHITESPACE,
        "\t" => self::T_WHITESPACE,
        "\r" => self::T_WHITESPACE,
        '.'  => self::T_DOT,
        '*'  => self::T_STAR,
        ','  => self::T_COMMA,
        ':'  => self::T_COLON,
        '{'  => self::T_LBRACE,
        '}'  => self::T_RBRACE,
        ']'  => self::T_RBRACKET,
        '('  => self::T_LPARENS,
        ')'  => self::T_RPARENS,
        '='  => self::T_OPERATOR,
        '@'  => self::T_AT,
    );

    private $primitives = array(
        'true'  => true,
        'false' => true,
        'null'  => true
    );

    private $primitiveMap = array(
        'true'  => true,
        'false' => false,
        'null'  => null
    );

    private $identifiers;
    private $firstTokenIdentifiers;
    private $jsonLiterals;

    private $input;
    private $pos;
    private $len;
    private $c;

    public function __construct()
    {
        // Create a hash of valid string and number characters
        $this->firstTokenIdentifiers = $this->identifiers = array_fill_keys(
            array_merge(
                range('a', 'z'),
                range('A', 'Z'),
                range('0', '9')
            ),
            true
        );

        // Identifiers can also include "-" and "_"
        $this->identifiers['_'] = 1;
        $this->identifiers['-'] = 1;

        // JSON literal characters
        $this->jsonLiterals = $this->identifiers;
        $this->jsonLiterals['.'] = true;
    }

    /**
     * Tokenize the JMESPath expression into token arrays. The regular
     * expression of the class breaks the expression into parts. Each part is
     * then analyzed to determine the token type until finally, the EOF token
     * is added signifying the end of the token stream.
     *
     * @param string $input JMESPath input
     *
     * @return array
     *
     * @throws SyntaxErrorException
     */
    public function tokenize($input)
    {
        $this->input = $input;
        $this->len = strlen($input);
        $this->pos = 0;
        $this->c = $this->len ? $this->input[0] : null;
        $tokens = array();

        while ($this->c !== null) {
            if (isset($this->firstTokenIdentifiers[$this->c])) {
                $tokens[] = $this->consumeIdentifier();
            } elseif (isset($this->simpleTokens[$this->c])) {
                $type = $this->simpleTokens[$this->c];
                if ($type != self::T_WHITESPACE) {
                    $tokens[] = array(
                        'type'  => $type,
                        'value' => $this->c,
                        'pos'   => $this->pos
                    );
                }
                $this->consume();
            } elseif ($this->c == '"') {
                $tokens[] = array(
                    'type'  => self::T_IDENTIFIER,
                    'pos'   => $this->pos,
                    'value' => $this->consumeQuotedString()
                );
            } elseif ($this->c == '_') {
                $tokens[] = $this->consumeLiteral();
            } elseif ($this->c == '[') {
                $tokens[] = $this->consumeLbracket();
            } elseif ($this->c == '<' || $this->c == '>' || $this->c == '!') {
                $tokens[] = $this->consumeOperator($this->c);
            } elseif ($this->c == '|') {
                $tokens[] = $this->consumePipe();
            } elseif ($this->c == '-') {
                $tokens[] = $this->consumeNumber();
            } else {
                $this->throwSyntax();
            }
        }

        // Always end the token stream with an EOF token
        $tokens[] = array(
            'type'  => self::T_EOF,
            'pos'   => $this->len,
            'value' => null
        );

        return $tokens;
    }

    private function throwSyntax($message = 'Unexpected character', $pos = null)
    {
        throw new SyntaxErrorException(
            $message,
            array(
                'value' => $this->c,
                'pos'   => $pos !== null ? $pos : $this->pos
            ),
            $this->input
        );
    }

    private function consume()
    {
        if (isset($this->input[++$this->pos])) {
            $this->c = $this->input[$this->pos];
        } else {
            $this->c = null;
        }
    }

    private function consumeNumber()
    {
        $pos = $this->pos;
        $str = $this->c;
        $this->consume();

        while ($this->c >= '0' && $this->c <= '9') {
            $str .= $this->c;
            $this->consume();
        }

        return array(
            'type'  => Lexer::T_NUMBER,
            'value' => (int) $str,
            'pos'   => $pos
        );
    }

    private function consumeIdentifier()
    {
        $pos = $this->pos;
        $value = '';

        do {
            $value .= $this->c;
            $this->consume();
        } while (isset($this->identifiers[$this->c]));

        if ($this->c == '(') {
            $this->consume();
            return array(
                'type'  => self::T_FUNCTION,
                'value' => $value,
                'pos'   => $pos
            );
        } elseif (ctype_digit($value)) {
            return array(
                'type'  => self::T_NUMBER,
                'value' => (int) $value,
                'pos'   => $pos
            );
        } else {
            return array(
                'type'  => self::T_IDENTIFIER,
                'value' => $value,
                'pos'   => $pos
            );
        }
    }

    private function consumeLiteral()
    {
        $pos = $this->pos;
        $this->consume();

        if ($this->c == '"') {
            $value = $this->consumeQuotedString();
        } else {
            $value = '';
            while ($this->c !== null && isset($this->jsonLiterals[$this->c])) {
                $value .= $this->c;
                $this->consume();
            }
            if ($value === '') {
                $this->throwSyntax('Invalid JSON literal', $pos);
            } elseif (isset($this->primitives[$value])) {
                $value = $this->primitiveMap[$value];
            } else {
                $value = json_decode($value);
                if ($error = json_last_error()) {
                    $this->throwSyntax('Error decoding JSON literal: ' . $error, $pos);
                }
            }
        }

        return array(
            'type'  => Lexer::T_LITERAL,
            'value' => $value,
            'pos'   => $pos
        );
    }

    private function consumeQuotedString()
    {
        $value = '';
        $this->consume();

        while ($this->c != '"') {
            if ($this->c === null) {
                $this->throwSyntax('Unclosed quote');
            }
            // Fix escaped quotes
            if ($this->c == '\\') {
                $this->consume();
                if ($this->c != '"') {
                    $value .= '\\';
                } else {
                    $this->consume();
                    $value .= '"';
                    continue;
                }
            }
            $value .= $this->c;
            $this->consume();
        }
        $this->consume();

        return $value;
    }

    private function consumeLbracket()
    {
        $this->consume();

        switch ($this->c) {
            case ']':
                $this->consume();
                return array(
                    'type'  => self::T_MERGE,
                    'value' => '[]',
                    'pos'   => $this->pos - 2
                );
            case '?':
                $this->consume();
                return array(
                    'type'  => self::T_FILTER,
                    'value' => '[?',
                    'pos'   => $this->pos - 2
                );
            default:
                return array(
                    'type'  => self::T_LBRACKET,
                    'value' => '[',
                    'pos'   => $this->pos - 1
                );
        }
    }

    private function consumeOperator($operator)
    {
        $token = array(
            'type'  => self::T_OPERATOR,
            'pos'   => $this->pos,
            'value' => $operator
        );

        $this->consume();

        if ($this->c == '=') {
            $this->consume();
            $token['value'] .= '=';
        }

        return $token;
    }

    private function consumePipe()
    {
        $this->consume();

        if ($this->c != '|') {
            $this->throwSyntax('Missing trailing | character');
        }

        $this->consume();

        return array(
            'type'  => Lexer::T_OR,
            'value' => '||',
            'pos'   => $this->pos - 2
        );
    }
}
