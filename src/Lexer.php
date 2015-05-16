<?php
namespace JmesPath;

/**
 * Tokenizes JMESPath expressions
 */
class Lexer
{
    /** @var array Characters that can start an identifier */
    private $startIdentifier = [
        'A' => true, 'B' => true, 'C' => true, 'D' => true, 'E' => true,
        'F' => true, 'G' => true, 'H' => true, 'I' => true, 'J' => true,
        'K' => true, 'L' => true, 'M' => true, 'N' => true, 'O' => true,
        'P' => true, 'Q' => true, 'R' => true, 'S' => true, 'T' => true,
        'U' => true, 'V' => true, 'W' => true, 'X' => true, 'Y' => true,
        'Z' => true, 'a' => true, 'b' => true, 'c' => true, 'd' => true,
        'e' => true, 'f' => true, 'g' => true, 'h' => true, 'i' => true,
        'j' => true, 'k' => true, 'l' => true, 'm' => true, 'n' => true,
        'o' => true, 'p' => true, 'q' => true, 'r' => true, 's' => true,
        't' => true, 'u' => true, 'v' => true, 'w' => true, 'x' => true,
        'y' => true, 'z' => true, '_' => true,
    ];

    /** @var array Number characters */
    private $numbers = [
        0 => true, 1 => true, 2 => true, 3 => true, 4 => true, 5 => true,
        6 => true, 7 => true, 8 => true, 9 => true,
    ];

    /** @var array Characters that can start a number (ctor calculated) */
    private $startNumber;

    /** @var array Valid identifier characters (ctor calculated) */
    private $validIdentifier;

    /** @var array Map of simple single character tokens */
    private $simpleTokens = [
        '.' => 'dot',
        '*' => 'star',
        ']' => 'rbracket',
        ',' => 'comma',
        ':' => 'colon',
        '@' => 'current',
        '&' => 'expref',
        '(' => 'lparen',
        ')' => 'rparen',
        '{' => 'lbrace',
        '}' => 'rbrace',
    ];

    /** @var array Map of whitespace characters */
    private $whitespace = [
        ' '  => 'skip',
        "\t" => 'skip',
        "\n" => 'skip',
        "\r" => 'skip',
    ];

    public function __construct()
    {
        $this->validIdentifier = $this->startIdentifier + $this->numbers;
        $this->startNumber = $this->numbers;
        $this->startNumber['-'] = true;
    }

    /**
     * Tokenize the JMESPath expression into an array of tokens hashes that
     * contain a 'type', 'value', and 'key'.
     *
     * @param string $input JMESPath input
     *
     * @return array
     * @throws SyntaxErrorException
     */
    public function tokenize($input)
    {
        $tokens = [];
        $chars = str_split($input);

        if (!$chars) {
            goto eof;
        }

        consume:

        $current = current($chars);

        // Terminal condition
        if ($current === false) {
            goto eof;
        }

        if (isset($this->simpleTokens[$current])) {
            // Consume simple tokens like ".", ",", "@", etc.
            $tokens[] = [
                'type'  => $this->simpleTokens[$current],
                'pos'   => key($chars),
                'value' => $current
            ];
            next($chars);
        } elseif (isset($this->whitespace[$current])) {
            // Skip whitespace
            next($chars);
        } elseif (isset($this->startIdentifier[$current])) {
            // Consume identifiers
            $start = key($chars);
            $buffer = '';
            do {
                $buffer .= $current;
                $current = next($chars);
            } while ($current !== false && isset($this->validIdentifier[$current]));
            $tokens[] = [
                'type'  => 'identifier',
                'value' => $buffer,
                'pos'   => $start
            ];
        } elseif (isset($this->startNumber[$current])) {
            // Consume numbers
            $start = key($chars);
            $buffer = '';
            do {
                $buffer .= $current;
                $current = next($chars);
            } while ($current !== false && isset($this->numbers[$current]));
            $tokens[] = [
                'type'  => 'number',
                'value' => (int) $buffer,
                'pos'   => $start
            ];
        } elseif ($current === '|') {
            // Consume pipe and OR
            $tokens[] = $this->matchOr($chars, '|', '|', 'or', 'pipe');
        } elseif ($current === '[') {
            // Consume "[", "[?", and "[]"
            $position = key($chars);
            $actual = next($chars);
            if ($actual === ']') {
                next($chars);
                $tokens[] = [
                    'type'  => 'flatten',
                    'pos'   => $position,
                    'value' => '[]'
                ];
            } elseif ($actual === '?') {
                next($chars);
                $tokens[] = [
                    'type'  => 'filter',
                    'pos'   => $position,
                    'value' => '[?'
                ];
            } else {
                $tokens[] = [
                    'type'  => 'lbracket',
                    'pos'   => $position,
                    'value' => '['
                ];
            }
        } elseif ($current === "'") {
            // Consume raw string literals
            $tokens[] = $this->inside($chars, "'", 'literal');
        } elseif ($current === "`") {
            // Consume JSON literals
            $token = $this->inside($chars, '`', 'literal');
            if ($token['type'] === 'literal') {
                $token['value'] = str_replace('\\`', '`', $token['value']);
                $token = $this->parseJson($token);
            }
            $tokens[] = $token;
        } elseif ($current === '"') {
            // Consume quoted identifiers
            $token = $this->inside($chars, '"', 'quoted_identifier');
            if ($token['type'] === 'quoted_identifier') {
                $token['value'] = '"' . $token['value'] . '"';
                $token = $this->parseJson($token);
            }
            $tokens[] = $token;
        } elseif ($current === '!') {
            // Consume not equal
            $tokens[] = $this->matchOr($chars, '!', '=', 'comparator', 'unknown');
        } elseif ($current === '>' || $current === '<') {
            // Consume less than and greater than
            $tokens[] = $this->matchOr($chars, $current, '=', 'comparator', 'comparator');
        } elseif ($current === '=') {
            // Consume equals
            $tokens[] = $this->matchOr($chars, '=', '=', 'comparator', 'unknown');
        } else {
            $tokens[] = [
                'type'  => 'unknown',
                'pos'   => key($chars),
                'value' => $current
            ];
            next($chars);
        }

        goto consume;

        eof:

        $tokens[] = [
            'type'  => 'eof',
            'pos'   => strlen($input),
            'value' => null
        ];

        return $tokens;
    }

    /**
     * Returns a token based on whether or not the next token matches the
     * expected value. If it does, a token of "$type" is returned. Otherwise,
     * a token of "$orElse" type is returned.
     *
     * @param array  $chars    Array of characters by reference.
     * @param string $current  The current character.
     * @param string $expected Expected character.
     * @param string $type     Expected result type.
     * @param string $orElse   Otherwise return a token of this type.
     *
     * @return array Returns a conditional token.
     */
    private function matchOr(array &$chars, $current, $expected, $type, $orElse)
    {
        $position = key($chars);
        $actual = next($chars);

        if ($actual === $expected) {
            next($chars);
            return [
                'type'  => $type,
                'pos'   => $position,
                'value' => $current . $expected
            ];
        }

        return [
            'type'  => $orElse,
            'pos'   => $position,
            'value' => $current
        ];
    }

    /**
     * Returns a token the is the result of consuming inside of delimiter
     * characters. Escaped delimiters will be adjusted before returning a
     * value. If the token is not closed, "unknown" is returned.
     *
     * @param array  $chars Array of characters by reference.
     * @param string $delim The delimiter character.
     * @param string $type  Token type.
     *
     * @return array Returns the consumed token.
     */
    private function inside(array &$chars, $delim, $type)
    {
        $position = key($chars);
        $current = next($chars);
        $buffer = '';

        while ($current !== $delim) {
            if ($current === '\\') {
                $buffer .= '\\';
                $current = next($chars);
            }
            if ($current === false) {
                // Unclosed delimiter
                return [
                    'type'  => 'unknown',
                    'value' => $buffer,
                    'pos'   => $position
                ];
            }
            $buffer .= $current;
            $current = next($chars);
        }

        next($chars);

        return ['type' => $type, 'value' => $buffer, 'pos' => $position];
    }

    /**
     * Parses a JSON token or sets the token type to "unknown" on error.
     *
     * @param array $token Token that needs parsing.
     *
     * @return array Returns a token with a parsed value.
     */
    private function parseJson(array $token)
    {
        $value = json_decode($token['value'], true);

        if ($error = json_last_error()) {
            $token['type'] = 'unknown';
            return $token;
        }

        $token['value'] = $value;
        return $token;
    }
}
