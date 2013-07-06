<?php

namespace JamesPath\Ast;

class ValuesBranchNode extends AbstractNode
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
        $response = $this->node->search($value);

        return is_array($response)
            ? new MultiMatch(array_values($response))
            : null;
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sValuesBranch(%s)", $indent, $this->node->prettyPrint());
    }
}
