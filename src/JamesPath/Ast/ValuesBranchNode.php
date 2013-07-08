<?php

namespace JamesPath\Ast;

class ValuesBranchNode extends AbstractNode
{
    /** @var AbstractNode */
    protected $node;

    /**
     * @param AbstractNode $node
     */
    public function __construct(AbstractNode $node = null)
    {
        $this->node = $node;
    }

    public function search($value)
    {
        $response = $this->node ? $this->node->search($value) : $value;

        if (is_array($response)) {
            return new MultiMatch(array_values($response));
        } elseif ($response instanceof MultiMatch) {
            $result = array();
            foreach ($response->toArray() as $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $result[] = $v;
                    }
                }
            }
            if ($result) {
                return new MultiMatch($result);
            }
        }
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sValuesBranch(%s)", $indent, $this->node ? $this->node->prettyPrint() : '');
    }
}
