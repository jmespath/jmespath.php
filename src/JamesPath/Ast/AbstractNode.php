<?php

namespace JamesPath\Ast;

/**
 * Abstract AST node
 */
abstract class AbstractNode
{
    /**
     * Search the AST node for a given value
     *
     * @param array|\ArrayAccess $value Data to search
     *
     * @return mixed
     */
    abstract public function search($value);

    /**
     * Prints a human-readable representation of the AST
     *
     * @param string $indent String to prepend for indentation
     *
     * @return string
     */
    abstract public function prettyPrint($indent = '');

    public function __toString()
    {
        return $this->prettyPrint();
    }
}
