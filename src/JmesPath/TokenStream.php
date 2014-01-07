<?php

namespace JmesPath;

/**
 * Represents a stream of tokens.
 *
 * The current token is retrieved using the public $token property.
 */
class TokenStream
{
    /** @var array Current token */
    public $token;

    /** @var array Array of tokens */
    private $tokens;

    /** @var int Current token position */
    private $pos;

    /** @var string Current expression */
    private $expression;

    /** @var array Null token that is reused over and over */
    private static $nullToken = array('type' => Lexer::T_EOF, 'value' => '');

    /**
     * @param array  $tokens     Tokens to stream
     * @param string $expression JMESPath expression
     */
    public function __construct(array $tokens, $expression)
    {
        $this->tokens = $tokens;
        $this->expression = $expression;
        $this->pos = -1;
        $this->next();
    }

    /**
     * Convert the stream of tokens to a string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->expression;
    }

    /**
     * Move the token stream cursor to the next token
     */
    public function next()
    {
        if (!isset($this->tokens[$this->pos + 1])) {
            $this->token = self::$nullToken;
        } else {
            $this->token = $this->tokens[++$this->pos];
        }
    }

    /**
     * Asserts that the current token is one of several types.
     *
     * @param array $types Hash of type values to true/false
     * @throws SyntaxErrorException if the token is not one of the types
     */
    public function match(array $types)
    {
        if (!isset($types[$this->tokens[$this->pos]['type']])) {
            throw new SyntaxErrorException(
                $types,
                $this->tokens[$this->pos],
                (string) $this
            );
        }
    }
}
