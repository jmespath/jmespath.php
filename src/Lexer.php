<?php
namespace JmesPath;

/**
 * Tokenizes JMESPath expressions
 */
class Lexer
{
    private $regex, $offsetToToken;

    /** @var array Map of regular expressions to their token match value */
    private $tokenMap = [
        '[a-zA-Z_][a-zA-Z_0-9]*'     => 'identifier',
        '\.'                         => 'dot',
        '\*'                         => 'star',
        '\[\]'                       => 'flatten',
        '-?\d+'                      => 'number',
        '\|\|'                       => 'or',
        '\|'                         => 'pipe',
        '\[\?'                       => 'filter',
        '\['                         => 'lbracket',
        '\]'                         => 'rbracket',
        '"(?:\\\\\\\\|\\\\"|[^"])*"' => 'quoted_identifier',
        '`(?:\\\\\\\\|\\\\`|[^`])*`' => 'literal',
        ','                          => 'comma',
        ':'                          => 'colon',
        '@'                          => 'current',
        '&'                          => 'expref',
        '\('                         => 'lparen',
        '\)'                         => 'rparen',
        '\{'                         => 'lbrace',
        '\}'                         => 'rbrace',
        '!='                         => 'comparator',
        '=='                         => 'comparator',
        '<='                         => 'comparator',
        '>='                         => 'comparator',
        '<'                          => 'comparator',
        '>'                          => 'comparator',
        '[ \t]'                      => 'skip',
    ];

    public function __construct()
    {
        $this->regex = '((' .
            implode(')|(', array_keys($this->tokenMap)) . '))';
        $this->offsetToToken = array_values($this->tokenMap);
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
        $offset = 0;
        $tokens = [];

        if (!preg_match_all($this->regex, $input, $matches, PREG_SET_ORDER)) {
            $this->throwSyntax('Invalid expression', $offset, $input);
        }

        foreach ($matches as $match) {
            $type = $this->offsetToToken[count($match) - 2];

            if ($type !== 'skip') {
                $token = [
                    'type'  => $type,
                    'value' => $match[0],
                    'pos'   => $offset
                ];

                switch ($token['type']) {
                    case 'quoted_identifier':
                        $token['value'] = $this->decodeJson(
                            $token['value'],
                            $offset,
                            $input
                        );
                        break;
                    case 'number':
                        $token['value'] = (int) $token['value'];
                        break;
                    case 'literal':
                        $token['value'] = $this->takeLiteral(
                            $token['value'],
                            $offset,
                            $input
                        );
                        break;
                }
                $tokens[] = $token;
            }

            $offset += strlen($match[0]);
        }

        // Always end the token stream with an EOF token
        $tokens[] = ['type' => 'eof', 'pos' => $offset, 'value' => null];

        // Ensure that the expression did not contain invalid characters
        if (strlen($input) != $offset) {
            $this->invalidExpression($input);
        }

        return $tokens;
    }

    private function takeLiteral($value, $offset, $input)
    {
        // Maps common JavaScript primitives with a native PHP primitive
        static $primitives = ['true' => 0, 'false' => 1, 'null' => 2];
        static $primitiveMap = [true, false, null];
        // If a literal starts with these characters, it is JSON decoded
        static $decodeCharacters = ['"' => 1, '[' => 1, '{' => 1];

        $value = str_replace('\\`', '`', ltrim(substr($value, 1, -1)));

        if (isset($primitives[$value])) {
            // Fast lookups for common JSON primitives
            return $primitiveMap[$primitives[$value]];
        } elseif (strlen($value) == 0) {
            $this->throwSyntax('Empty JSON literal', $offset, $input);
        } elseif (isset($decodeCharacters[$value[0]])) {
            // Always decode the JSON directly if it starts with these chars
            return $this->decodeJson($value, $offset, $input);
        } elseif (preg_match(
            '/^\-?[0-9]*(\.[0-9]+)?([e|E][+|\-][0-9]+)?$/',
            $value
        )) {
            // If it starts with a "-" or numbers, then attempt to JSON decode
            return $this->decodeJson($value, $offset, $input);
        }

        return $this->decodeJson('"' . $value . '"', $offset, $input);
    }

    private function decodeJson($json, $offset, $input)
    {
        static $errs = [
            JSON_ERROR_DEPTH          => 'JSON_ERROR_DEPTH',
            JSON_ERROR_STATE_MISMATCH => 'JSON_ERROR_STATE_MISMATCH',
            JSON_ERROR_CTRL_CHAR      => 'JSON_ERROR_CTRL_CHAR',
            JSON_ERROR_SYNTAX         => 'JSON_ERROR_SYNTAX',
            JSON_ERROR_UTF8           => 'JSON_ERROR_UTF8'
        ];

        $value = json_decode($json, true);

        if ($error = json_last_error()) {
            $message = isset($errs[$error]) ? $errs[$error] : 'Unknown error';
            $this->throwSyntax(
                "Error decoding JSON: ({$error}) {$message}, given {$json}",
                $offset,
                $input
            );
        }

        return $value;
    }

    private function throwSyntax($message, $offset, $input)
    {
        throw new SyntaxErrorException(
            $message,
            ['value' => substr($input, $offset, 1), 'pos' => $offset],
            $input
        );
    }

    private function invalidExpression($input)
    {
        $offset = 0;
        $regex = $this->regex . 'A';

        while (preg_match($regex, $input, $matches, 0, $offset)) {
            $offset += strlen($matches[0]);
        }

        $this->throwSyntax('Unexpected character', $offset, $input);
    }
}
