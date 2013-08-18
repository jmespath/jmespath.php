<?php

namespace JamesPath\Ast;

class OrExpressionNode extends AbstractNode
{
    /** @var AbstractNode */
    protected $left;
    /** @var AbstractNode */
    protected $right;

    /**
     * @param AbstractNode $left     left option
     * @param AbstractNode $right Second option
     */
    public function __construct(AbstractNode $left, AbstractNode $right)
    {
        $this->left = $left;
        $this->right = $right;
    }

    public function search($value)
    {
        $matched = $this->left->search($value);

        return null !== $matched ? $matched : $this->right->search($value);
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sORExpression(%s, %s)", $indent, $this->left, $this->right);
    }
}
