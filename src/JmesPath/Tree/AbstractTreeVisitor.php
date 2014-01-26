<?php

namespace JmesPath\Tree;

abstract class AbstractTreeVisitor implements TreeVisitorInterface
{
    /**
     * Handles evaluating undefined types without paying the cost of validation
     */
    public function __call($method, $args)
    {
        throw new \RuntimeException(
            sprintf('Invalid node encountered: %s', json_encode($args[0]))
        );
    }
}
