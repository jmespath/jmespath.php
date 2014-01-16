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
     *
     * @param array $match Associative array of acceptable next tokens
     *
     * @throws SyntaxErrorException if the next token is not acceptable
     */
    public function next(array $match = null)
    {
        if (!isset($this->tokens[$this->pos + 1])) {
            $this->token = self::$nullToken;
        } else {
            $this->token = $this->tokens[++$this->pos];
        }

        if ($match) {
            if (!isset($match[$this->token['type']])) {
                throw new SyntaxErrorException(
                    $match,
                    $this->tokens[$this->pos],
                    (string) $this
                );
            }
        }
    }
}
