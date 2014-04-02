<?php

namespace JmesPath\Tree;

class ExprNode
{
    /** @var array */
    public $children;

    /** @var TreeInterpreter */
    public $interpreter;

    public function __construct(TreeInterpreter $interpreter, array $children)
    {
        $this->interpreter = $interpreter;
        $this->children = $children;
    }
}
