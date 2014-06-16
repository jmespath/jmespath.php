<?php

namespace JmesPath;

/**
 * LL(1) recursive descent JMESPath lexer
 */
class Lexer
{
    /** @var array Array of simple matches to token types */
    private static $simpleTokens = [
        ' '  => false,
        "\n" => false,
        "\t" => false,
        "\r" => false,
        '.'  => 'dot',
        '*'  => 'star',
        ','  => 'comma',
        ':'  => 'colon',
        '{'  => 'lbrace',
        '}'  => 'rbrace',
        ']'  => 'rbracket',
        '('  => 'lparen',
        ')'  => 'rparen',
        '@'  => 'current',
        '&'  => 'expref',
    ];

    /** @var array Valid identifier characters */
    private static $identifiers = [
        'a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1, 'f' => 1, 'g' => 1,
        'h' => 1, 'i' => 1, 'j' => 1, 'k' => 1, 'l' => 1, 'm' => 1, 'n' => 1,
        'o' => 1, 'p' => 1, 'q' => 1, 'r' => 1, 's' => 1, 't' => 1, 'u' => 1,
        'v' => 1, 'w' => 1, 'x' => 1, 'y' => 1, 'z' => 1, 'A' => 1, 'B' => 1,
        'C' => 1, 'D' => 1, 'E' => 1, 'F' => 1, 'G' => 1, 'H' => 1, 'I' => 1,
        'J' => 1, 'K' => 1, 'L' => 1, 'M' => 1, 'N' => 1, 'O' => 1, 'P' => 1,
        'Q' => 1, 'R' => 1, 'S' => 1, 'T' => 1, 'U' => 1, 'V' => 1, 'W' => 1,
        'X' => 1, 'Y' => 1, 'Z' => 1,  0  => 1,  1  => 1,  2  => 1,  3  => 1,
         4  => 1,  5  => 1,  6  => 1,  7  => 1,  8  => 1,  9  => 1,
        '_' => 1, '-' => 1];

    /** @var array Letters and "_" can start an identifier */
    private static $identifierStart = [
        'a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1, 'f' => 1, 'g' => 1,
        'h' => 1, 'i' => 1, 'j' => 1, 'k' => 1, 'l' => 1, 'm' => 1, 'n' => 1,
        'o' => 1, 'p' => 1, 'q' => 1, 'r' => 1, 's' => 1, 't' => 1, 'u' => 1,
        'v' => 1, 'w' => 1, 'x' => 1, 'y' => 1, 'z' => 1, 'A' => 1, 'B' => 1,
        'C' => 1, 'D' => 1, 'E' => 1, 'F' => 1, 'G' => 1, 'H' => 1, 'I' => 1,
        'J' => 1, 'K' => 1, 'L' => 1, 'M' => 1, 'N' => 1, 'O' => 1, 'P' => 1,
        'Q' => 1, 'R' => 1, 'S' => 1, 'T' => 1, 'U' => 1, 'V' => 1, 'W' => 1,
        'X' => 1, 'Y' => 1, 'Z' => 1, '_' => 1];

    /** @var array Hash of number characters */
    private static $numbers = ['0' => 1, '1' => 1, '2' => 1, '3' => 1,
        '4' => 1, '5' => 1, '6' => 1, '7' => 1, '8' => 1, '9' => 1];

    /** @var array Hash of the start of an operator token */
    private static $opStart = ['=' => 1, '<' => 1, '>' => 1, '!' => 1];

    private $input, $pos, $c;

    /**
     * Ensures that a binary relational operator is valid, and if not, throws
     * a RuntimeException.
     *
     * @param string $value Relational operator to validate
     * @throws \RuntimeException
     */
    public static function validateBinaryOperator($value)
    {
        static $valid = ['==' => true, '!=' => true, '>'  => true, '>=' => true,
            '<'  => true, '<=' => true];

        if (!isset($valid[$value])) {
            throw new \RuntimeException("Invalid relational operator: $value");
        }
    }

    /**
     * Tokenize the JMESPath expression into an array of tokens.
     *
     * Each token array contains a type, value, and pos key along with any
     * other keys that might be relevant to the particular token.
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
        $tokens = [];

        while ($this->c !== null) {
            if (isset(self::$identifierStart[$this->c])) {
                $tokens[] = $this->consumeIdentifier();
            } elseif (isset(self::$simpleTokens[$this->c])) {
                if ($type = self::$simpleTokens[$this->c]) {
                    $tokens[] = [
                        'type'  => $type,
                        'value' => $this->c,
                        'pos'   => $this->pos
                    ];
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
            } elseif (isset(self::$opStart[$this->c])) {
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
        $tokens[] = ['type' => 'eof', 'pos' => $len, 'value' => null];

        return $tokens;
    }

    private function throwSyntax($message = 'Unexpected character', $pos = null)
    {
        throw new SyntaxErrorException(
            $message,
            ['value' => $this->c, 'pos' => $pos !== null ? $pos : $this->pos],
            $this->input
        );
    }

    private function decodeJson($json)
    {
        static $jsonErrors = [
            JSON_ERROR_NONE => 'JSON_ERROR_NONE - No errors',
            JSON_ERROR_DEPTH => 'JSON_ERROR_DEPTH - Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'JSON_ERROR_STATE_MISMATCH - Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'JSON_ERROR_CTRL_CHAR - Unexpected control character found',
            JSON_ERROR_SYNTAX => 'JSON_ERROR_SYNTAX - Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'JSON_ERROR_UTF8 - Malformed UTF-8 characters, possibly incorrectly encoded'
        ];

        $value = json_decode($json, true);
        if ($error = json_last_error()) {
            $message = isset($jsonErrors[$error])
                ? $jsonErrors[$error]
                : 'Unknown error';
            $this->throwSyntax("Error decoding JSON: ({$error}) {$message}, "
                . "given \"{$json}\"", $this->pos - 1);
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
        $token = ['type' => 'number', 'pos' => $this->pos];
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

        return ['type' => 'identifier', 'value' => $value, 'pos' => $pos];
    }

    private function consumeLiteral()
    {
        // Maps common JavaScript primitives with a native PHP primitive
        static $primitives = ['true' => 0, 'false' => 1, 'null' => 2];
        static $primitiveMap = [true, false, null];
        // If a literal starts with these characters, it is JSON decoded
        static $decodeCharacters = ['"' => 1, '[' => 1, '{' => 1];

        $literal = ['type' => 'literal', 'pos' => $this->pos];

        // Consume until the closing literal is found or the end of string
        if (!preg_match(
            '/`((\\\\\\\\|\\\\`|[^`])*)`/',
            $this->input,
            $matches,
            0,
            $this->pos
        )) {
            $this->throwSyntax('Unclosed JSON literal', $this->pos);
        }

        $value = str_replace('\\`', '`', ltrim($matches[1]));
        $this->pos += strlen($matches[0]) - 1 ;
        $this->consume();

        if (isset($primitives[$value])) {
            // Fast lookups for common JSON primitives
            $value = $primitiveMap[$primitives[$value]];
        } elseif (strlen($value) == 0) {
            $this->throwSyntax('Empty JSON literal', $this->pos - 2);
        } elseif (isset($decodeCharacters[$value[0]])) {
            // Always decode the JSON directly if it starts with these chars
            $value = $this->decodeJson($value);
        } elseif (preg_match(
            '/^\-?[0-9]*(\.[0-9]+)?([e|E][+|\-][0-9]+)?$/',
            $value)
        ) {
            // If it starts with a "-" or numbers, then attempt to JSON decode
            $value = $this->decodeJson($value);
        } else {
            $value = $this->decodeJson('"' . $value . '"');
        }

        $literal['value'] = $value;

        return $literal;
    }

    private function consumeQuotedString()
    {
        if (!preg_match(
            '/"(\\\\\\\\|\\\\"|[^"])*"/',
            $this->input,
            $matches,
            0,
            $this->pos
        )) {
            $this->throwSyntax('Unclosed quote');
        }

        $token = [
            'type'  => 'quoted_identifier',
            'pos'   => $this->pos,
            'value' => $this->decodeJson($matches[0])
        ];

        $this->pos += strlen($matches[0]) - 1;
        $this->consume();

        return $token;
    }

    private function consumeLbracket()
    {
        $this->consume();

        if ($this->c == ']') {
            $this->consume();
            return [
                'type'  => 'flatten',
                'value' => '[]',
                'pos'   => $this->pos - 2
            ];
        } elseif ($this->c == '?') {
            $this->consume();
            return [
                'type'  => 'filter',
                'value' => '[?',
                'pos'   => $this->pos - 2
            ];
        }

        return ['type' => 'lbracket', 'value' => '[', 'pos' => $this->pos - 1];
    }

    private function consumeOperator($operator)
    {
        $token = [
            'type'  => 'comparator',
            'pos'   => $this->pos,
            'value' => $operator
        ];

        $this->consume();

        if ($this->c == '=') {
            $this->consume();
            $token['value'] .= '=';
        } elseif ($operator == '=') {
            $this->throwSyntax('Got "=", expected "=="');
        }

        self::validateBinaryOperator($token['value']);

        return $token;
    }

    private function consumePipe()
    {
        $this->consume();

        // Check for pipe-expression vs or-expression
        if ($this->c != '|') {
            return ['type' => 'pipe', 'value' => '|', 'pos' => $this->pos - 1];
        }

        $this->consume();

        return ['type' => 'or', 'value' => '||', 'pos' => $this->pos - 2];
    }
}
