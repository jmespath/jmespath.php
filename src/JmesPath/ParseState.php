<?php

namespace JmesPath;

/**
 * JMESPath parse state used in a stack in the JMESPath parser to determine
 * where the current state comes from (e.g., 'object', 'array') and whether or
 * not any expressions evaluated in this state requires that the 'push_current'
 * bytecode instruction is pushed onto the top of the stack.
 */
class ParseState
{
    public $pos, $push, $type, $needsPush;

    /**
     * Creates a new parser state
     *
     * @param int    $pos  Lexer position of the state
     * @param string $type Type that this state is descended from
     * @param bool   $needsPush Whether or not the state will push_current
     */
    public function __construct($pos, $type = '', $needsPush = true)
    {
        $this->pos = $pos;
        $this->type = $type;
        $this->needsPush = $needsPush;
    }
}
