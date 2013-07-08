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
            if ($result instanceof MultiMatch) {
                // Go down a level in the array
                return new MultiMatch(array_map(function ($element) {
                    return is_array($element) ? new MultiMatch($element) : $element;
                }, $result->toArray()));
            } else {
                return new MultiMatch((array) $result);
            }
        }
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sElementsBranch(%s)", $indent, $this->node->prettyPrint());
    }
}
