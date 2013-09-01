<?php

namespace JmesPath;

/**
 * JmesPath recursive descent lexer
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
    const T_IGNORE = 'T_IGNORE';
    const T_UNKNOWN = 'T_UNKNOWN';

    private $input;
    private $tokens;

    private $regex = '/
        (\w+)               # T_IDENTIFIER
        |("(?:\\\"|[^"])*") # T_IDENTIFIER
        |\s+                # Ignore whitespace
        |(\.)               # T_DOT
        |(\*)               # T_STAR
        |(\[)               # T_LBRACKET
        |(\])               # T_RBRACKET
        |(\-?\d+)           # T_NUMBER
        |(\|\|)             # T_OR
        |(.)                # T_UNKNOWN
    /x';

    private $simpleTokens = array(
        '.'  => self::T_DOT,
        '*'  => self::T_STAR,
        '['  => self::T_LBRACKET,
        ']'  => self::T_RBRACKET,
        '||' => self::T_OR
    );

    /**
     * Set the expression to parse and reset state
     *
     * @param string $input Input expression
     */
    public function setInput($input)
    {
        $this->input = $input;
        $this->tokenize();
    }

    /**
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->tokens);
    }

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
                $this->tokens[] = [
                    'type'  => $this->simpleTokens[$token[0]],
                    'value' => $token[0],
                    'pos'   => $token[1]
                ];
            } elseif (is_numeric($token[0])) {
                $this->tokens[] = [
                    'type'  => self::T_NUMBER,
                    'value' => (int) $token[0],
                    'pos'   => $token[1]
                ];
            } elseif (ctype_alnum($token[0])) {
                $this->tokens[] = [
                    'type'  => self::T_IDENTIFIER,
                    'value' => str_replace('\\"', '"', $token[0]),
                    'pos'   => $token[1]
                ];
            } elseif ($token[0] != '"' && substr($token[0], 0, 1) == '"' && substr($token[0], -1, 1) == '"') {
                $this->tokens[] = [
                    'type'  => self::T_IDENTIFIER,
                    'value' => str_replace('\\"', '"', substr($token[0], 1, -1)),
                    'pos'   => $token[1]
                ];
            } else {
                $this->tokens[] = $t = [
                    'type'  => self::T_UNKNOWN,
                    'value' => $token[0],
                    'pos'   => $token[1]
                ];
                // Check for an unclosed quote character
                if ($token[0] == '"') {
                    throw new SyntaxErrorException('Unclosed quote character', $t, $this);
                }
            }
        }

        $this->tokens[] = [
            'type'  => self::T_EOF,
            'value' => null,
            'pos'   => strlen($this->input)
        ];
    }
}
