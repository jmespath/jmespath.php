<?php
namespace JmesPath;

/**
 * Tree visitor used to compile JMESPath expressions into native PHP code.
 */
class TreeCompiler
{
    private $indentation;
    private $source;
    private $vars;

    /**
     * @param array  $ast    AST to compile.
     * @param string $fnName The name of the function to generate.
     * @param string $expr   Expression being compiled.
     *
     * @return string
     */
    public function visit(array $ast, $fnName, $expr)
    {
        $this->vars = [];
        $this->source = $this->indentation = '';
        $this->write("<?php\n");
        $this->write("// {$expr}");
        $this->write("function {$fnName}(\\JmesPath\\TreeInterpreter \$interpreter, \$value) {")
            ->indent()
                ->write('$current = $value;')
                ->dispatch($ast)
                ->write('')
                ->write('return $value;')
            ->outdent()
        ->write('}');

        return $this->source;
    }

    private function dispatch(array $node)
    {
        return $this->{"visit_{$node['type']}"}($node);
    }

    private function makeVar($type)
    {
        if (!isset($this->vars[$type])) {
            $this->vars[$type] = 0;
        }

        return $type . ++$this->vars[$type];
    }

    /**
     * Writes the given line of source code
     *
     * @param string $str String to write
     * @return $this
     */
    private function write($str)
    {
        $this->source .= "{$this->indentation}{$str}\n";
        return $this;
    }

    /**
     * Decreases the indentation level of code being written
     *
     * @return $this
     */
    private function outdent()
    {
        $this->indentation = substr($this->indentation, 0, -4);
        return $this;
    }

    /**
     * Increases the indentation level of code being written
     *
     * @return $this
     */
    private function indent()
    {
        $this->indentation .= '    ';
        return $this;
    }

    private function visit_or(array $node)
    {
        $a = $this->makeVar('beforeOr');

        return $this
            ->write("\$$a = \$value;")
            ->dispatch($node['children'][0])
            ->write('if (!$value && $value !== "0" && $value !== 0) {')
                ->indent()
                ->write("\$value = \$$a;")
                ->dispatch($node['children'][1])
                ->outdent()
            ->write('}');
    }

    /**
     * Visits a non-terminal subexpression. Subexpressions wrapping nested
     * array accessors will be combined into a single if/then block.
     */
    private function visit_subexpression(array $node)
    {
        return $this
            ->dispatch($node['children'][0])
            ->write('if ($value !== null) {')
                ->indent()
                ->dispatch($node['children'][1])
                ->outdent()
            ->write('}');
    }

    /**
     * Visits a terminal identifier
     */
    private function visit_field(array $node)
    {
        $arrCheck = '$value[' . var_export($node['value'], true) . ']';
        $objCheck = '$value->{' . var_export($node['value'], true) . '}';

        $this->write("if (is_array(\$value) || \$value instanceof \\ArrayAccess) {")
                ->indent()
                ->write("\$value = isset($arrCheck) ? $arrCheck : null;")
                ->outdent()
            ->write("} elseif (\$value instanceof \\stdClass) {")
                ->indent()
                ->write("\$value = isset($objCheck) ? $objCheck : null;")
                ->outdent()
            ->write("} else {")
                ->indent()
                ->write("\$value = null;")
                ->outdent()
            ->write("}");

        return $this;
    }

    /**
     * Visits a terminal index
     */
    private function visit_index(array $node)
    {
        if ($node['value'] >= 0) {
            $check = '$value[' . $node['value'] . ']';
            $this->write("\$value = (is_array(\$value) || \$value instanceof \\ArrayAccess) && isset($check) ? $check : null;");
            return $this;
        }

        // Account for negative indices
        $a = $this->makeVar('count');

        $this
            ->write('if (is_array($value) || ($value instanceof \ArrayAccess && $value instanceof \Countable)) {')
                ->indent()
                ->write("\${$a} = count(\$value) + {$node['value']};")
                ->write("\$value = isset(\$value[\${$a}]) ? \$value[\${$a}] : null;")
                ->outdent()
            ->write('} else {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
            ->write('}');

        return $this;
    }

    private function visit_literal(array $node)
    {
        return $this->write('$value = ' . var_export($node['value'], true) . ';');
    }

    private function visit_pipe(array $node)
    {
        return $this
            ->dispatch($node['children'][0])
            ->write('$current = $value;')
            ->dispatch($node['children'][1]);
    }

    private function visit_multi_select_list(array $node)
    {
        return $this->visit_multi_select_hash($node);
    }

    private function visit_multi_select_hash(array $node)
    {
        $tmpCurrent = $this->makeVar('cur');
        $listVal = $this->makeVar('list');
        $value = $this->makeVar('prev');

        $this
            ->write('if ($value !== null) {')
                ->indent()
                ->write("\${$listVal} = array();")
                ->write("\${$tmpCurrent} = \$current;")
                ->write("\${$value} = \$value;");

        $first = true;
        foreach ($node['children'] as $child) {
            if (!$first) {
                $this->write("\$value = \${$value};");
            }
            $first = false;
            if ($node['type'] == 'multi_select_hash') {
                $this->dispatch($child['children'][0]);
                $key = var_export($child['value'], true);
                $this->write("\${$listVal}[{$key}] = \$value;");
            } else {
                $this->dispatch($child);
                $this->write("\${$listVal}[] = \$value;");
            }
        }

        return $this
            ->write("\$value = \${$listVal};")
            ->write("\$current = \${$tmpCurrent};")
            ->outdent()
            ->write('}');
    }

    private function visit_function(array $node)
    {
        $value = $this->makeVar('val');
        $current = $this->makeVar('current');
        $args = $this->makeVar('args');

        $this->write("\${$value} = \$value;")
            ->write("\${$current} = \$current;")
            ->write("\${$args} = array();");

        foreach ($node['children'] as $arg) {
            $this->dispatch($arg);
            $this->write("\${$args}[] = \$value;")
                ->write("\$current = \${$current};")
                ->write("\$value = \${$value};");
        }

        return $this->write("\$value = JmesPath\\FnDispatcher::getInstance()->__invoke('{$node['value']}', \${$args});");
    }

    private function visit_slice(array $node)
    {
        return $this
            ->write("\$value = JmesPath\\FnDispatcher::getInstance()->__invoke('slice', array(")
                ->indent()
                ->write(sprintf(
                    '$value, %s, %s, %s',
                    var_export($node['value'][0], true),
                    var_export($node['value'][1], true),
                    var_export($node['value'][2], true)
                ))
                ->outdent()
            ->write('));');
    }

    private function visit_current(array $node)
    {
        return $this->write('// Visiting current node (no-op)');
    }

    private function visit_expref(array $node)
    {
        $child = var_export($node['children'][0], true);
        return $this->write("\$value = function (\$value) use (\$interpreter) {")
            ->indent()
            ->write("return \$interpreter->visit($child, \$value);")
            ->outdent()
        ->write('};');
    }

    private function visit_flatten(array $node)
    {
        $this->dispatch($node['children'][0]);
        $tmpMerged = $this->makeVar('merged');
        $tmpVal = $this->makeVar('val');

        $this
            ->write('// Visiting merge node')
            ->write('if (!\JmesPath\TreeInterpreter::isArray($value)) {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
            ->write('} else {')
                ->indent()
                ->write("\${$tmpMerged} = [];")
                ->write("foreach (\$value as \${$tmpVal}) {")
                    ->indent()
                    ->write("if (is_array(\${$tmpVal}) && isset(\${$tmpVal}[0])) {")
                        ->indent()
                        ->write("\${$tmpMerged} = array_merge(\${$tmpMerged}, \${$tmpVal});")
                        ->outdent()
                    ->write("} elseif (\${$tmpVal} !== array()) {")
                        ->indent()
                        ->write("\${$tmpMerged}[] = \${$tmpVal};")
                        ->outdent()
                    ->write('}')
                    ->outdent()
                ->write('}')
                ->write("\$value = \${$tmpMerged};")
                ->outdent()
            ->write('}');

        return $this;
    }

    private function visit_projection(array $node)
    {
        $this->write('// Visiting projection node')
            ->dispatch($node['children'][0])
            ->write('');

        if (!isset($node['from'])) {
            $this->write('if (!is_array($value) || !($value instanceof \stdClass)) $value = null;');
        } elseif ($node['from'] == 'object') {
            $this->write('if (!\JmesPath\TreeInterpreter::isObject($value)) $value = null;');
        } elseif ($node['from'] == 'array') {
            $this->write('if (!\JmesPath\TreeInterpreter::isArray($value)) $value = null;');
        }

        $tmpVal = $this->makeVar('val');
        $tmpCollected = $this->makeVar('collected');

        $this->write('if ($value !== null) {')
            ->indent()
            ->write("\${$tmpCollected} = [];")
            ->write('foreach ((array) $value as $' . $tmpVal . ') {')
                ->indent()
                ->write("\$value = \${$tmpVal};")
                ->dispatch($node['children'][1])
                ->write('if ($value !== null) {')
                    ->indent()
                    ->write("\${$tmpCollected}[] = \$value;")
                    ->outdent()
                ->write('}')
                ->outdent()
            ->write('}')
            ->write("\$value = \${$tmpCollected};")
            ->outdent()
        ->write('}');

        return $this;
    }

    private function visit_condition(array $node)
    {
        return $this
            ->write('// Visiting projection node')
            ->dispatch($node['children'][0])
            ->write('if ($value !== null) {')
                ->indent()
                ->dispatch($node['children'][1])
                ->outdent()
            ->write('}');
    }

    private function visit_comparator(array $node)
    {
        $tmpValue = $this->makeVar('val');
        $tmpCurrent = $this->makeVar('cur');
        $tmpA = $this->makeVar('left');
        $tmpB = $this->makeVar('right');

        $this
            ->write('// Visiting comparator node')
            ->write("\${$tmpValue} = \$value;")
            ->write("\${$tmpCurrent} = \$current;")
            ->dispatch($node['children'][0])
            ->write("\${$tmpA} = \$value;")
            ->write("\$value = \${$tmpValue};")
            ->dispatch($node['children'][1])
            ->write("\${$tmpB} = \$value;");

        if ($node['value'] == '==') {
            $this->write("\$result = \\JmesPath\\TreeInterpreter::valueCmp(\${$tmpA}, \${$tmpB});");
        } elseif ($node['value'] == '!=') {
            $this->write("\$result = !\\JmesPath\\TreeInterpreter::valueCmp(\${$tmpA}, \${$tmpB});");
        } else {
            $this->write("\$result = is_int(\${$tmpA}) && is_int(\${$tmpB}) && \${$tmpA} {$node['value']} \${$tmpB};");
        }

        return $this
            ->write("\$value = \$result === true ? \${$tmpValue} : null;")
            ->write("\$current = \${$tmpCurrent};");
    }

    /** @internal */
    public function __call($method, $args)
    {
        throw new \RuntimeException(
            sprintf('Invalid node encountered: %s', json_encode($args[0]))
        );
    }
}
