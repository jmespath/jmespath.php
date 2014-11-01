<?php
namespace JmesPath;

/**
 * Tokenizes JMESPath expressions
 */
class Lexer
{
    private $regex, $offsetToToken;

    private $tokens = [
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
        $this->regex = '((' . implode(')|(', array_keys($this->tokens)) . '))';
        $this->offsetToToken = array_values($this->tokens);
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
        if (!preg_match_all($this->regex, $input, $matches, PREG_SET_ORDER)) {
            throw $this->throwSyntax('Invalid expression', 0, $input);
        }

        $offset = 0;
        $tokens = [];
        foreach ($matches as $match) {
            $type = $this->offsetToToken[count($match) - 2];
            if ($type !== 'skip') {
                $token = ['type' => $type, 'value' => $match[0], 'pos' => $offset];
                switch ($token['type']) {
                    case 'quoted_identifier':
                        $token['value'] = $this->decodeJson(
                            $token['value'], $offset, $input
                        );
                        break;
                    case 'number':
                        $token['value'] = (int) $token['value'];
                        break;
                    case 'literal':
                        $token['value'] = $this->literal(
                            $token['value'], $offset, $input
                        );
                        break;
                }
                $tokens[] = $token;
            }
            $offset += strlen($match[0]);
        }

        $tokens[] = ['type' => 'eof', 'pos' => $offset, 'value' => null];

        if (strlen($input) != $offset) {
            $this->invalidExpression($input);
        }

        return $tokens;
    }

    private function literal($value, $offset, $input)
    {
        // Handles true, false, null, numbers, quoted strings, "[", and "{"
        static $valid = '/(true|false|null)|(^[\["{])|(^\-?[0-9]*(\.[0-9]+)?([e|E][+|\-][0-9]+)?$)/';
        $value = str_replace('\\`', '`', ltrim(substr($value, 1, -1)));

        return preg_match($valid, $value)
            ? $this->decodeJson($value, $offset, $input)
            : $this->decodeJson('"' . $value . '"', $offset, $input);
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
            throw $this->throwSyntax(
                "Error decoding JSON: ({$error}) {$message}, given {$json}",
                $offset,
                $input
            );
        }

        return $value;
    }

    private function throwSyntax($message, $offset, $input)
    {
        return new SyntaxErrorException(
            $message,
            ['value' => substr($input, $offset, 1), 'pos' => $offset],
            $input
        );
    }

    private function invalidExpression($input)
    {
        $offset = 0;
        while (preg_match("{$this->regex}A", $input, $matches, 0, $offset)) {
            $offset += strlen($matches[0]);
        }

        throw $this->throwSyntax('Unexpected character', $offset, $input);
    }
}
