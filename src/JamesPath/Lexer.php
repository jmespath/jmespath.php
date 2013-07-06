<?php

namespace JamesPath;

/**
 * JamesPath recursive descent lexer
 */
class Lexer implements \Iterator
{
    const T_IDENTIFIER = 0;
    const T_DOT = 1;
    const T_STAR = 2;
    const T_LBRACKET = 3;
    const T_RBRACKET = 4;
    const T_NUMBER = 5;
    const T_OR = 6;
    const T_IGNORE = 7;
    const T_EOF = -1;

    protected $input;
    protected $tokens;
    protected $token;
    protected $pos;

    /** @var array Token names and regexps */
    protected $tokenDefinitions = array(
        array('T_IDENTIFIER', '/^[a-zA-Z_]([a-zA-Z_0-9]|\\\.)*/'),
        array('T_DOT',        '/^\./'),
        array('T_STAR',       '/^\*/'),
        array('T_LBRACKET',   '/^\[/'),
        array('T_RBRACKET',   '/^\]/'),
        array('T_NUMBER',     '/^\-?\d+/'),
        array('T_OR',         '/^\|\|/'),
        array('T_IGNORE',     '/^\s+/'),
        array('T_EOF',        null)
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
        $this->rewind();
    }

    /**
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    public function current()
    {
        return $this->token;
    }

    public function key()
    {
        return $this->pos;
    }

    public function rewind()
    {
        $this->pos = -1;
        $this->token = null;
        $this->next();
    }

    public function valid()
    {
        return $this->token !== null && $this->token->type != self::T_EOF;
    }

    public function next()
    {
        $this->token = isset($this->tokens[++$this->pos]) ? $this->tokens[$this->pos] : Token::getEof();
    }

    /**
     * Get the next token
     *
     * @return Token
     */
    public function peek()
    {
        return isset($this->tokens[$this->pos + 1]) ? $this->tokens[$this->pos + 1] : Token::getEof();
    }

    /**
     * Get the name of a token
     *
     * @param int|array $token Token integer
     * @return string|null
     */
    public function getTokenName($token)
    {
        $token = is_object($token) ? $token->type : $token;

        return isset($this->tokenDefinitions[$token]) ? $this->tokenDefinitions[$token][0] : null;
    }

    protected function tokenize()
    {
        $this->tokens = array();
        $length = strlen($this->input);
        $pos = 0;

        while ($pos < $length) {
            $remainder = substr($this->input, $pos);
            foreach ($this->tokenDefinitions as $token => $def) {
                if (null !== ($result = $this->scan($def[1], $remainder, $pos))) {
                    if ($token !== self::T_IGNORE) {
                        $this->tokens[] = new Token($token, $result, $pos);
                    }
                    $pos += strlen($result);
                    continue 2;
                }
            }
            throw new IllegalTokenException(sprintf('Illegal token "%s" at %d', substr($this->input, $pos), $pos));
        }
    }

    protected function scan($regexp, $path)
    {
        if ($regexp && preg_match($regexp, $path, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
