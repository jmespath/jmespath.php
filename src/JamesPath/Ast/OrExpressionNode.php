<?php

namespace JamesPath\Ast;

class OrExpressionNode extends AbstractNode
{
    /** @var AbstractNode */
    protected $first;

    /** @var AbstractNode */
    protected $remaining;

    /**
     * @param AbstractNode $first     First option
     * @param AbstractNode $remaining Second option
     */
    public function __construct(AbstractNode $first, AbstractNode $remaining)
    {
        $this->first = $first;
        $this->remaining = $remaining;
    }

    public function search($value)
    {
        $matched = $this->first->search($value);

        return null !== $matched ? $matched : $this->remaining->search($value);
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sORExpression(%s, %s)", $indent, $this->first, $this->remaining);
    }
}
