<?php

namespace JamesPath\Ast;

class IndexNode extends AbstractNode
{
    /** @var int|string */
    protected $index;

    /**
     * @param int|string $index Array index
     */
    public function __construct($index)
    {
        $this->index = $index;
    }

    public function search($value)
    {
        if (is_scalar($value)) {
            return null;
        }

        // Allow negative indices
        $index = $this->index >= 0 ? $this->index : count($value) + $this->index;

        return isset($value[$index]) ? $value[$index] : null;
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sIndex(%s)", $indent, $this->index);
    }
}
