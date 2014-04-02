<?php

namespace JmesPath\Tree;

class ExprNode
{
    /** @var array */
    public $expression;

    /** @var TreeInterpreter */
    public $interpreter;

    public function __construct(TreeInterpreter $interpreter, array $children)
    {
        $this->interpreter = $interpreter;
        $this->expression = $children[0];
    }
}
