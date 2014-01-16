<?php

namespace JmesPath;

/**
 * JMESPath expression parser.
 */
interface ParserInterface
{
    /**
     * Compile a JMESPath expression into an AST
     *
     * @param string $expression JMESPath expression to compile
     *
     * @return array Returns an array based AST
     * @throws SyntaxErrorException
     */
    public function compile($expression);
}
