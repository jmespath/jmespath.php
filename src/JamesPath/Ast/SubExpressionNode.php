<?php

namespace JamesPath\Ast;

class SubExpressionNode extends AbstractNode
{
    /** @var AbstractNode */
    protected $left;
    /** @var AbstractNode */
    protected $right;

    /**
     * @param AbstractNode $left left node
     * @param AbstractNode $right  right node
     */
    public function __construct(AbstractNode $left, AbstractNode $right = null)
    {
        $this->left = $left;
        $this->right = $right;
    }

    public function setRight(AbstractNode $right)
    {
        $this->right = $right;
    }

    public function search($value)
    {
        if (!$this->right) {
            throw new \RuntimeException('Incomplete ' . __CLASS__);
        }

        return $this->right->search($this->left->search($value));
    }

    public function prettyPrint($indent = '')
    {
        $subIndent = str_repeat(' ', 4);

        return sprintf(
            "%sSubExpression(\n%s%s,\n%s%s)",
            $indent,
            $indent,
            $this->left->prettyPrint($subIndent),
            $indent,
            $this->right ? $this->right->prettyPrint($subIndent) : ''
        );
    }
}
