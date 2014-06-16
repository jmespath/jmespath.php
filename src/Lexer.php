<?php
namespace JmesPath;

/**
 * Tokenizes JMESPath expressions
 */
class Lexer
{
    private $re, $offsetToToken, $input, $pos, $handlers;

    /** @var array Simple tokens that do not need to be in the regex */
    private $simpleTokens = [
        ' '  => 'skip',
        "\n" => 'skip',
        "\t" => 'skip',
        "\r" => 'skip',
        '.'  => 'dot',
        '*'  => 'star',
        ','  => 'comma',
        ':'  => 'colon',
        '@'  => 'current',
        '&'  => 'expref',
        ']'  => 'rbracket',
        '('  => 'lparen',
        ')'  => 'rparen',
        '{'  => 'lbrace',
        '}'  => 'rbrace',
    ];

    /** @var array Map of regular expressions to their token match value */
    private $tokenMap = [
        '-?\d+'                      => 'number',
        '[a-zA-Z_][a-zA-Z_0-9]*'     => 'identifier',
        '"(?:\\\\\\\\|\\\\"|[^"])*"' => 'quoted_identifier',
        '`(?:\\\\\\\\|\\\\`|[^`])*`' => 'literal',
        '\[\]'                       => 'flatten',
        '\|\|'                       => 'or',
        '\|'                         => 'pipe',
        '\[\?'                       => 'filter',
        '\['                         => 'lbracket',
        '!='                         => 'comparator',
        '=='                         => 'comparator',
        '<='                         => 'comparator',
        '>='                         => 'comparator',
        '<'                          => 'comparator',
        '>'                          => 'comparator',
    ];

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

    public function __construct()
    {
        $this->re = '((' .
            implode(')|(', array_keys($this->tokenMap))
            . '))AS';
        $this->offsetToToken = array_values($this->tokenMap);
        $this->handlers = array_fill_keys(get_class_methods($this), true);
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
        $this->pos = 0;
        $tokens = [];

        while (isset($input[$this->pos])) {

            if (isset($this->simpleTokens[$input[$this->pos]])) {
                $type = $this->simpleTokens[$input[$this->pos]];
                if ($type == 'skip') {
                    $this->pos += 1;
                    continue;
                }
                $value = $input[$this->pos];
            } elseif (preg_match($this->re, $input, $matches, null, $this->pos)) {
                $type = $this->offsetToToken[count($matches) - 2];
                $value = $matches[0];
            } else {
                $this->throwSyntax();
            }

            $token = ['type' => $type, 'value' => $value, 'pos' => $this->pos];

            // Check if a custom token handler is needed to process the lexeme
            if (isset($this->handlers['token_' . $token['type']])) {
                $token = $this->{'token_' . $token['type']}($token);
            }

            $tokens[] = $token;
            $this->pos += strlen($value);
        }

        // Always end the token stream with an EOF token
        $tokens[] = ['type' => 'eof', 'pos' => $this->pos, 'value' => null];

        return $tokens;
    }

    private function throwSyntax($message = 'Unexpected character')
    {
        throw new SyntaxErrorException(
            $message,
            [
                'value' => substr($this->input, $this->pos, 1),
                'pos'   => $this->pos
            ],
            $this->input
        );
    }

    private function token_number(array $token)
    {
        $token['value'] = (int) $token['value'];

        return $token;
    }

    private function token_literal(array $token)
    {
        // Maps common JavaScript primitives with a native PHP primitive
        static $primitives = ['true' => 0, 'false' => 1, 'null' => 2];
        static $primitiveMap = [true, false, null];
        // If a literal starts with these characters, it is JSON decoded
        static $decodeCharacters = ['"' => 1, '[' => 1, '{' => 1];

        $token['value'] = substr($token['value'], 1, -1);
        $token['value'] = str_replace('\\`', '`', ltrim($token['value']));

        if (isset($primitives[$token['value']])) {
            // Fast lookups for common JSON primitives
            $token['value'] = $primitiveMap[$primitives[$token['value']]];
        } elseif (strlen($token['value']) == 0) {
            $this->throwSyntax('Empty JSON literal');
        } elseif (isset($decodeCharacters[$token['value'][0]])) {
            // Always decode the JSON directly if it starts with these chars
            $token['value'] = $this->decodeJson($token['value']);
        } elseif (preg_match(
            '/^\-?[0-9]*(\.[0-9]+)?([e|E][+|\-][0-9]+)?$/',
            $token['value'])
        ) {
            // If it starts with a "-" or numbers, then attempt to JSON decode
            $token['value'] = $this->decodeJson($token['value']);
        } else {
            $token['value'] = $this->decodeJson('"' . $token['value'] . '"');
        }

        return $token;
    }

    private function token_quoted_identifier(array $token)
    {
        $token['value'] = $this->decodeJson($token['value']);

        return $token;
    }

    private function decodeJson($json)
    {
        static $jsonErrors = [
            JSON_ERROR_DEPTH => 'JSON_ERROR_DEPTH',
            JSON_ERROR_STATE_MISMATCH => 'JSON_ERROR_STATE_MISMATCH',
            JSON_ERROR_CTRL_CHAR => 'JSON_ERROR_CTRL_CHAR',
            JSON_ERROR_SYNTAX => 'JSON_ERROR_SYNTAX',
            JSON_ERROR_UTF8 => 'JSON_ERROR_UTF8'
        ];

        $value = json_decode($json, true);
        if ($error = json_last_error()) {
            $message = isset($jsonErrors[$error])
                ? $jsonErrors[$error]
                : 'Unknown error';
            $this->throwSyntax("Error decoding JSON: ({$error}) {$message}, "
                . "given \"{$json}\"");
        }

        return $value;
    }
}
