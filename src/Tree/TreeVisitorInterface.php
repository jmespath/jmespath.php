<?php
namespace JmesPath\Tree;

/**
 * Tree visitor used to evaluate JMESPath ASTs.
 */
interface TreeVisitorInterface
{
    /**
     * Visits each node in a JMESPath AST and returns the evaluated result
     *
     * @param array $node JMESPath AST node
     * @param mixed $data Data to evaluate
     * @param array $args Optional array of arguments
     *
     * @return mixed
     */
    public function visit(array $node, $data, array $args = []);
}
