<?php

namespace JamesPath\Ast;

class ElementsBranchNode extends AbstractNode
{
    /** @var AbstractNode */
    protected $node;

    /**
     * @param AbstractNode $node
     */
    public function __construct(AbstractNode $node)
    {
        $this->node = $node;
    }

    public function search($value)
    {
        if ($result = $this->node->search($value)) {
            return $result instanceof MultiMatch ? $result : new MultiMatch((array) $result);
        }
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sElementsBranch(%s)", $indent, $this->node->prettyPrint());
    }
}
