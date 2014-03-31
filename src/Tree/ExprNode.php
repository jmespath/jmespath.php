<?php

namespace JmesPath\Tree;

class ExprNode
{
    public $children;

    public function __construct(array $children)
    {
        $this->children = $children;
    }
}
