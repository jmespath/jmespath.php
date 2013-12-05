<?php

namespace JmesPath;

/**
 * JMESPath lexer
 */
class Lexer implements \IteratorAggregate
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
    const T_IGNORE = 'T_IGNORE';
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

    /** @var string JMESPath expression */
    private $input;

    /** @var array Array of parsed tokens */
    private $tokens;

    /** @var string Regular expression used to split an expression */
    private $regex = '/
        |(_*"(?:\\\"|[^"])*")    # T_IDENTIFIER or T_LITERAL
        |(_\-*\d+(?:\.\d+)*)     # T_LITERAL
        |([\w]+\()               # T_FUNCTION
        |([A-Za-z0-9\-_]+)       # T_IDENTIFIER or T_LITERAL or T_NUMBER
        |(\.)                    # T_DOT
        |\s+                     # Ignore whitespace
        |(\*)                    # T_STAR
        |(\[\])                  # T_MERGE
        |(\[\?)                  # T_FILTER
        |(\])                    # T_RBRACKET
        |(\[)                    # T_LBRACKET
        |(\])                    # T_RBRACKET
        |(,)                     # T_COMMA
        |({)                     # T_LBRACE
        |(})                     # T_RBRACE
        |(:)                     # T_COLON
        |(\()                    # T_LPARENS
        |(\))                    # T_RPARENS
        |(<=|>=|>|<|!=|=)        # T_OPERATOR
        |(\|\|)                  # T_OR
        |(@)                     # T_AT
        |(.)                     # T_UNKNOWN
    /x';

    /** @var array Array of simple matches to token types */
    private $simpleTokens = array(
        '.'      => self::T_DOT,
        '*'      => self::T_STAR,
        '['      => self::T_LBRACKET,
        ']'      => self::T_RBRACKET,
        '||'     => self::T_OR,
        ','      => self::T_COMMA,
        ':'      => self::T_COLON,
        '{'      => self::T_LBRACE,
        '}'      => self::T_RBRACE,
        '('      => self::T_LPARENS,
        ')'      => self::T_RPARENS,
        '[?'     => self::T_FILTER,
        '='      => self::T_OPERATOR,
        '<'      => self::T_OPERATOR,
        '>'      => self::T_OPERATOR,
        '!='     => self::T_OPERATOR,
        '>='     => self::T_OPERATOR,
        '<='     => self::T_OPERATOR,
        '[]'     => self::T_MERGE,
        '@'      => self::T_AT,
    );

    private $primitives = array('_true' => true, '_false' => true, '_null' => true);
    private $primitiveMap = array('_true' => true, '_false' => false, '_null' => null);

    /**
     * Set the expression to parse and reset state
     *
     * @param string $input Input expression
     */
    public function setInput($input)
    {
        $this->input = $input;
        $this->tokens = null;
    }

    /**
     * Get the initial string of JMESPath input
     *
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->getTokens());
    }

    /**
     * Get an array of tokens
     *
     * @return array
     */
    public function getTokens()
    {
        if (null === $this->tokens) {
            $this->tokenize();
        }

        return $this->tokens;
    }

    /**
     * Tokenize the JMESPath expression into token arrays. The regular
     * expression of the class breaks the expression into parts. Each part is
     * then analyzed to determine the token type until finally, the EOF token
     * is added signifying the end of the token stream.
     *
     * @throws SyntaxErrorException
     */
    private function tokenize()
    {
        $this->tokens = array();
        $tokens = preg_split(
            $this->regex,
            $this->input,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE
        );

        foreach ($tokens as $token) {
            if (isset($this->simpleTokens[$token[0]])) {
                // Match simple tokens using a hash lookup
                $this->tokens[] = array(
                    'type'  => $this->simpleTokens[$token[0]],
                    'value' => $token[0],
                    'pos'   => $token[1]
                );
            } elseif (substr($token[0], 0, 1) == '_') {
                $this->parseLiteral($token);
            } elseif (is_numeric($token[0])) {
                $this->tokens[] = array(
                    'type'  => self::T_NUMBER,
                    'value' => (int) $token[0],
                    'pos'   => $token[1]
                );
            } elseif (preg_match('/^[A-Za-z0-9\-]+[A-Za-z0-9\-_]*$/', $token[0])) {
                // Match identifiers (cannot start with an underscore)
                $this->tokens[] = array(
                    'type'  => self::T_IDENTIFIER,
                    'value' => $token[0],
                    'pos'   => $token[1]
                );
            } elseif (substr($token[0], 0, 1) == '"' &&
                substr($token[0], -1, 1) == '"' &&
                $token[0] != '"'
            ) {
                // Match valid quoted strings and remove escape characters
                $this->tokens[] = array(
                    'type'  => self::T_IDENTIFIER,
                    'value' => str_replace('\\"', '"', substr($token[0], 1, -1)),
                    'pos'   => $token[1]
                );
            } elseif (substr($token[0], -1, 1) == '(') {
                // Function call
                $this->tokens[] = array(
                    'type'  => self::T_FUNCTION,
                    'value' => substr($token[0], 0, -1),
                    'pos'   => $token[1]
                );
            } else {
                // Match all other unknown characters
                $this->tokens[] = $t = array(
                    'type'  => self::T_UNKNOWN,
                    'value' => $token[0],
                    'pos'   => $token[1]
                );

                if ($token[0] == '"') {
                    throw new SyntaxErrorException('Unclosed quote character', $t, $this->input);
                }
            }
        }

        // Always end the token stream with an EOF token
        $this->tokens[] = array('type' => self::T_EOF, 'value' => null, 'pos' => strlen($this->input));
    }

    /**
     * Parses a literal token into either a T_NUMBER or T_LITERAL
     *
     * @param array $token Token to parse
     *
     * @throws SyntaxErrorException If the literal token is invalid
     */
    private function parseLiteral(array $token)
    {
        $error = false;

        if ($token[0] == '_') {
            $error = true;
        } elseif (isset($this->primitives[$token[0]])) {
            $value = $this->primitiveMap[$token[0]];
        } else {
            $value = json_decode(substr($token[0], 1));
            $error = json_last_error();
        }

        if ($error) {
            throw new SyntaxErrorException(
                'Invalid literal token',
                array('pos' => $token[1]),
                $this->input
            );
        }

        $this->tokens[] = array(
            'type'  => Lexer::T_LITERAL,
            'value' => $value,
            'pos'   => $token[1]
        );
    }
}
