<?php

namespace JmesPath;

/**
 * JMESPath expression parser.
 *
 * Create bytecode for a JMESPath interpreter.
 */
interface ParserInterface
{
    /**
     * Compile a JMESPath expression into an array of opcodes
     *
     * @param string $expression JMESPath expression to compile
     *
     * @return array
     * @throws SyntaxErrorException
     */
    public function compile($expression);
}
