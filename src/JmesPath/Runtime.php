<?php

namespace JmesPath;

use JmesPath\Tree\TreeInterpreter;
use JmesPath\Tree\TreeVisitorInterface;

/**
 * Default JMESPath runtime environment that uses an external tree visitor to
 * interpret an AST.
 */
class Runtime extends AbstractRuntime
{
    /** @var TreeVisitorInterface */
    private $interpreter;

    /** @var array */
    private $visitorOptions;

    /**
     * Factory method used to easily create a customized JMESPath runtime
     * environment
     *
     * @param array $options Options used to create the runtime
     *  'parser'            => Parser used to parse expressions into an AST
     *  'interpreter'       => Tree interpreter used to interpret the AST
     *  'cache_dir'         => If specified, the parsed AST will be compiled
     *                         to PHP code and saved to the given directory.
     *                         Specifying this option will meant that a provided
     *                         'interpreter' will be ignored.
     * @return Runtime
     */
    public static function createRuntime(array $options = array())
    {
        $parser = isset($options['parser'])
            ? $options['parser'] : new Parser(new Lexer());

        if (isset($options['cache_dir'])) {
            return new CompilingRuntime($parser, $options['cache_dir']);
        }

        return new self(
            $parser,
            isset($options['interpreter']) ? $options['interpreter'] : new TreeInterpreter()
        );
    }

    /**
     * @param Parser               $parser      Parser used to parse expressions
     * @param TreeVisitorInterface $interpreter Tree visitor used to interpret the AST
     */
    public function __construct(
        Parser $parser,
        TreeVisitorInterface $interpreter
    ) {
        $this->parser = $parser;
        $this->interpreter = $interpreter;
        $this->visitorOptions = array('runtime' => $this);
    }

    public function search($expression, $data)
    {
        return $this->interpreter->visit(
            $this->parser->parse($expression),
            $data,
            $this->visitorOptions
        );
    }

    protected function debugInterpret($expression, array $ast, $data, $out)
    {
        return $this->interpreter->visit($ast, $data, $this->visitorOptions);
    }
}
