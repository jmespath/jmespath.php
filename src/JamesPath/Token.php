<?php

namespace JamesPath;

/**
 * JamesPath token
 */
class Token
{
    public $value;
    public $type;
    public $position;
    private static $eof;

    /**
     * @param int   $type     Token type
     * @param mixed $value    Token value
     * @param int   $position Position of the token
     */
    public function __construct($type, $value, $position)
    {
        // Remove escape characters
        if ($type == Lexer::T_IDENTIFIER) {
            $value = str_replace('\\', '', $value);
        }

        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }

    /**
     * Get a cached T_EOF token
     *
     * @return self
     */
    public static function getEof()
    {
        if (!self::$eof) {
            self::$eof = new self(Lexer::T_EOF, null, null);
        }

        return self::$eof;
    }
}
