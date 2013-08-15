<?php

namespace JamesPath\Ast;

class SubExpressionNode extends AbstractNode
{
    /** @var AbstractNode */
    protected $parent;

    /** @var AbstractNode */
    protected $child;

    /**
     * @param AbstractNode $parent Parent node
     * @param AbstractNode $child  Child node
     */
    public function __construct(AbstractNode $parent, AbstractNode $child = null)
    {
        $this->parent = $parent;
        $this->child = $child;
    }

    public function setChild(AbstractNode $child)
    {
        $this->child = $child;
    }

    public function search($value)
    {
        return $this->child->search($this->parent->search($value));
    }

    public function prettyPrint($indent = '')
    {
        $subIndent = str_repeat(' ', 4);

        return sprintf(
            "%sSubExpression(\n%s%s,\n%s%s)",
            $indent,
            $indent,
            $this->parent->prettyPrint($subIndent),
            $indent,
            $this->child ? $this->child->prettyPrint($subIndent) : ''
        );
    }
}
