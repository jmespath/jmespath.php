<?php

namespace JmesPath;

/**
 * LL(1) recursive descent JMESPath lexer
 */
class Lexer
{
    const T_EOF        = 'T_EOF';
    const T_IDENTIFIER = 'T_IDENTIFIER';
    const T_DOT        = 'T_DOT';
    const T_STAR       = 'T_STAR';
    const T_NUMBER     = 'T_NUMBER';
    const T_OR         = 'T_OR';
    const T_PIPE       = 'T_PIPE';
    const T_LBRACKET   = 'T_LBRACKET';
    const T_RBRACKET   = 'T_RBRACKET';
    const T_COMMA      = 'T_COMMA';
    const T_LBRACE     = 'T_LBRACE';
    const T_RBRACE     = 'T_RBRACE';
    const T_WHITESPACE = 'T_WHITESPACE';
    const T_UNKNOWN    = 'T_UNKNOWN';
    const T_COLON      = 'T_COLON';
    const T_OPERATOR   = 'T_OPERATOR';
    const T_FUNCTION   = 'T_FUNCTION';
    const T_LPARENS    = 'T_LPARENS';
    const T_RPARENS    = 'T_RPARENS';
    const T_MERGE      = 'T_MERGE';
    const T_LITERAL    = 'T_LITERAL';
    const T_FILTER     = 'T_FILTER';
    const T_AT         = 'T_AT';

    /** @var array Array of simple matches to token types */
    private static $simpleTokens = array(
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
        '@'  => self::T_AT,
    );

    /** @var array Valid identifier characters */
    private static $identifiers = array(
        'a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1, 'f' => 1, 'g' => 1,
        'h' => 1, 'i' => 1, 'j' => 1, 'k' => 1, 'l' => 1, 'm' => 1, 'n' => 1,
        'o' => 1, 'p' => 1, 'q' => 1, 'r' => 1, 's' => 1, 't' => 1, 'u' => 1,
        'v' => 1, 'w' => 1, 'x' => 1, 'y' => 1, 'z' => 1, 'A' => 1, 'B' => 1,
        'C' => 1, 'D' => 1, 'E' => 1, 'F' => 1, 'G' => 1, 'H' => 1, 'I' => 1,
        'J' => 1, 'K' => 1, 'L' => 1, 'M' => 1, 'N' => 1, 'O' => 1, 'P' => 1,
        'Q' => 1, 'R' => 1, 'S' => 1, 'T' => 1, 'U' => 1, 'V' => 1, 'W' => 1,
        'X' => 1, 'Y' => 1, 'Z' => 1,  0  => 1,  1  => 1,  2  => 1,  3  => 1,
         4  => 1,  5  => 1,  6  => 1,  7  => 1,  8  => 1,  9  => 1,
        '_' => 1, '-' => 1);

    /** @var array Letters and "_" can start an identifier */
    private static $identifierStart = array(
        'a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1, 'f' => 1, 'g' => 1,
        'h' => 1, 'i' => 1, 'j' => 1, 'k' => 1, 'l' => 1, 'm' => 1, 'n' => 1,
        'o' => 1, 'p' => 1, 'q' => 1, 'r' => 1, 's' => 1, 't' => 1, 'u' => 1,
        'v' => 1, 'w' => 1, 'x' => 1, 'y' => 1, 'z' => 1, 'A' => 1, 'B' => 1,
        'C' => 1, 'D' => 1, 'E' => 1, 'F' => 1, 'G' => 1, 'H' => 1, 'I' => 1,
        'J' => 1, 'K' => 1, 'L' => 1, 'M' => 1, 'N' => 1, 'O' => 1, 'P' => 1,
        'Q' => 1, 'R' => 1, 'S' => 1, 'T' => 1, 'U' => 1, 'V' => 1, 'W' => 1,
        'X' => 1, 'Y' => 1, 'Z' => 1, '_' => 1);

    /** @var array Hash of number characters */
    private static $numbers = array('0' => 1, '1' => 1, '2' => 1, '3' => 1,
        '4' => 1, '5' => 1, '6' => 1, '7' => 1, '8' => 1, '9' => 1);

    private $input;
    private $pos;
    private $c;

    /**
     * Tokenize the JMESPath expression into an array of tokens
     *
     * @param string $input JMESPath input
     *
     * @return array
     * @throws SyntaxErrorException
     */
    public function tokenize($input)
    {
        $this->input = $input;
        $len = strlen($input);
        $this->pos = 0;
        $this->c = $len ? $this->input[0] : null;
        $tokens = array();

        while ($this->c !== null) {
            if (isset(self::$identifierStart[$this->c])) {
                $tokens[] = $this->consumeIdentifier();
            } elseif (isset(self::$simpleTokens[$this->c])) {
                $type = self::$simpleTokens[$this->c];
                if ($type != self::T_WHITESPACE) {
                    $tokens[] = array(
                        'type'  => $type,
                        'value' => $this->c,
                        'pos'   => $this->pos
                    );
                }
                $this->consume();
            } elseif (isset(self::$numbers[$this->c])) {
                $tokens[] = $this->consumeNumber();
            } elseif ($this->c == '"') {
                $tokens[] = $this->consumeQuotedString();
            } elseif ($this->c == '`') {
                $tokens[] = $this->consumeLiteral();
            } elseif ($this->c == '[') {
                $tokens[] = $this->consumeLbracket();
            } elseif ($this->c == '=' || $this->c == '<' || $this->c == '>' || $this->c == '!') {
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
        $tokens[] = array('type' => self::T_EOF, 'pos' => $len, 'value' => null);

        return $tokens;
    }

    private function throwSyntax($message = 'Unexpected character', $pos = null)
    {
        throw new SyntaxErrorException(
            $message,
            array('value' => $this->c, 'pos' => $pos !== null ? $pos : $this->pos),
            $this->input
        );
    }

    private function decodeJson($json)
    {
        static $jsonErrors = array(
            JSON_ERROR_NONE => 'JSON_ERROR_NONE - No errors',
            JSON_ERROR_DEPTH => 'JSON_ERROR_DEPTH - Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'JSON_ERROR_STATE_MISMATCH - Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'JSON_ERROR_CTRL_CHAR - Unexpected control character found',
            JSON_ERROR_SYNTAX => 'JSON_ERROR_SYNTAX - Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'JSON_ERROR_UTF8 - Malformed UTF-8 characters, possibly incorrectly encoded'
        );

        $value = json_decode($json, true);
        if ($error = json_last_error()) {
            $message = isset($jsonErrors[$error]) ? $jsonErrors[$error] : 'Unknown error';
            $this->throwSyntax("Error decoding JSON: ({$error}) {$message}, given \"{$json}\"", $this->pos - 1);
        }

        return $value;
    }

    private function consume()
    {
        $this->c = isset($this->input[++$this->pos])
            ? $this->input[$this->pos]
            : null;
    }

    private function consumeNumber()
    {
        $token = array('type' => Lexer::T_NUMBER, 'pos' => $this->pos);
        $str = '';

        do {
            $str .= $this->c;
            $this->consume();
        } while (isset(self::$numbers[$this->c]));

        $token['value'] = (int) $str;

        return $token;
    }

    private function consumeIdentifier()
    {
        $pos = $this->pos;
        $value = '';

        do {
            $value .= $this->c;
            $this->consume();
        } while (isset(self::$identifiers[$this->c]));

        if ($this->c == '(') {
            $this->consume();
            return array(
                'type'  => self::T_FUNCTION,
                'value' => $value,
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
        // Maps common JavaScript primitives with a native PHP primitive
        static $primitives = array('true' => 0, 'false' => 1, 'null' => 2);
        static $primitiveMap = array(true, false, null);
        // If a literal starts with these characters, it is JSON decoded
        static $decodeCharacters = array('"' => 1, '[' => 1, '{' => 1, '-' => 1,
            0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1,
            8 => 1, 9 => 1);

        $literal = array('type' => self::T_LITERAL, 'pos' => $this->pos);
        $this->consume();

        // Consume until the closing literal is found or the end of string
        $value = '';
        while ($this->c != '`') {
            // Fix escaped literals
            if ($this->c == '\\') {
                $this->consume();
                if ($this->c != '`') {
                    $value .= '\\';
                }
            } elseif ($this->c === null) {
                $this->throwSyntax('Unclosed JSON literal', $this->pos);
            }
            $value .= $this->c;
            $this->consume();
        }

        // Consume the remaining literal character
        $this->consume();

        if (isset($primitives[$value])) {
            // Fast lookups for common JSON primitives
            $value = $primitiveMap[$primitives[$value]];
        } elseif (strlen($value) == 0) {
            $this->throwSyntax('Empty JSON literal', $this->pos - 2);
        } elseif (!isset($decodeCharacters[$value[0]])) {
            $value = $this->decodeJson('"' . $value . '"');
        } else {
            $value = $this->decodeJson($value);
        }

        $literal['value'] = $value;

        return $literal;
    }

    private function consumeQuotedString()
    {
        if (!preg_match('/"(\\\"|[^"])*"/', $this->input, $matches, 0, $this->pos)) {
            $this->throwSyntax('Unclosed quote');
        }

        $token = array(
            'type'  => self::T_IDENTIFIER,
            'pos'   => $this->pos,
            'value' => $this->decodeJson($matches[0])
        );

        $this->pos += strlen($matches[0]) - 1;
        $this->consume();

        return $token;
    }

    private function consumeLbracket()
    {
        $this->consume();

        if ($this->c == ']') {
            $this->consume();
            return array(
                'type'  => self::T_MERGE,
                'value' => '[]',
                'pos'   => $this->pos - 2
            );
        } elseif ($this->c == '?') {
            $this->consume();
            return array(
                'type'  => self::T_FILTER,
                'value' => '[?',
                'pos'   => $this->pos - 2
            );
        } else {
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
        } elseif ($operator == '=') {
            $this->throwSyntax('Got "=", expected "=="');
        }

        return $token;
    }

    private function consumePipe()
    {
        $this->consume();

        // Check for pipe-expression vs or-expression
        if ($this->c != '|') {
            return array(
                'type'  => Lexer::T_PIPE,
                'value' => '|',
                'pos'   => $this->pos - 1
            );
        }

        $this->consume();

        return array(
            'type'  => Lexer::T_OR,
            'value' => '||',
            'pos'   => $this->pos - 2
        );
    }
}
