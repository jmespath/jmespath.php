<?php

namespace JmesPath\Tree;

class ExprNode
{
    /** @var array */
    public $node;

    /** @var TreeInterpreter */
    public $interpreter;

    public function __construct(TreeInterpreter $interpreter, $node)
    {
        $this->interpreter = $interpreter;
        $this->node = $node;
    }
}
