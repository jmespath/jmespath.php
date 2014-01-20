<?php

namespace JmesPath\Tree;

/**
 * Tree visitor used to compile JMESPath expressions into native PHP code.
 */
class TreeCompiler implements TreeVisitorInterface
{
    private $indentation;
    private $source;
    private $cachedIndents;

    public function __call($method, $args)
    {
        throw new \RuntimeException('Invalid node encountered: ' . json_encode($args[0]));
    }

    public function visit(array $node, array $args = null)
    {
        $this->source = '';
        $this->indentation = 0;
        $this->write("function {$args['fn']}(\$value)\n{")
             ->indent();
        $this->write('$current = $value;');
        $this->dispatch($node);
        $this->write('')
             ->write('return $value;')
             ->outdent()
             ->write('}');

        return $this->source;
    }

    private function dispatch(array $node)
    {
        return $this->{"visit_{$node['type']}"}($node);
    }

    /**
     * @param string $str String to write
     * @return $this
     */
    private function write($str)
    {
        if (!isset($this->cachedIndents[$this->indentation])) {
            $this->cachedIndents[$this->indentation] = str_repeat(' ', $this->indentation * 4);
        }

        $this->source .= $this->cachedIndents[$this->indentation];
        $this->source .= $str . "\n";

        return $this;
    }

    /**
     * @return $this
     */
    private function outdent()
    {
        $this->indentation--;

        return $this;
    }

    /**
     * @return $this
     */
    private function indent()
    {
        $this->indentation++;

        return $this;
    }

    private function visit_or(array $node)
    {
        $this->dispatch($node['children'][0]);
        $this->write('');
        $this->write('if ($value === null) {')->indent();
        $this->write('$value = $current;');
        $this->dispatch($node['children'][1]);
        $this->outdent()
             ->write('}');

        return $this;
    }

    /**
     * Creates array access code for a given node
     *
     * @param array $node AST node
     * @return string Returns the array access code
     * @throws \RuntimeException if the node is not a field or index node
     */
    private function createArrayAccess($node)
    {
        if ($node['type'] == 'field') {
            return "['{$node['key']}']";
        } elseif ($node['type'] == 'index') {
            return "[{$node['index']}]";
        } else {
            throw new \RuntimeException("Invalid subexpression: {$node['type']}");
        }
    }

    /**
     * Visits a non-terminal subexpression. Subexpressions wrapping nested
     * array accessors will be combined into a single if/then block.
     */
    private function visit_subexpression(array $node)
    {
        if ($node['children'][0]['type'] != 'field' && $node['children'][0]['type'] != 'index') {
            $this->dispatch($node['children'][0]);
            $this->dispatch($node['children'][1]);
            return $this;
        }

        $key = $this->createArrayAccess($node['children'][0]);

        if ($node['children'][1]['type'] == 'subexpression') {
            // Create an optimized isset() check using a combination of
            // subexpression accessors
            $check = $node['children'][1];
            while ($check['type'] == 'subexpression') {
                $key .= $this->createArrayAccess($check['children'][0]);
                $check = $check['children'][1];
            }
            $key .= $this->createArrayAccess($check);
            $this->write("\$value = (isset(\$value$key))")
                ->indent()
                ->write("? \$value$key")
                ->write(': null;')
                ->outdent();
        } else {
            // A single accessor expression
            $this->write("if (isset(\$value$key)) {")
                ->indent()
                ->write("\$value = \$value$key;");
            $this->dispatch($node['children'][1]);
            $this->outdent()
                ->write('} else {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
                ->write('}');
        }

        return $this;
    }

    /**
     * Visits a terminal identifier
     */
    private function visit_field(array $node)
    {
        $check = '$value[\'' . $node['key'] . '\']';
        $this->write("\$value = isset($check) ? $check : null;");

        return $this;
    }

    /**
     * Visits a terminal index
     */
    private function visit_index(array $node)
    {
        $check = '$value[' . $node['index'] . ']';
        $this->write("\$value = isset($check) ? $check : null;");

        return $this;
    }

    private function visit_literal(array $node)
    {
        $this->write('$value = ' . var_export($node['value'], true) . ';');

        return $this;
    }

    private function visit_pipe(array $node)
    {
        $this->write('$current = $value;');

        return $this;
    }

    private function visit_multi_select_list(array $node)
    {
        return $this->visit_multi_select_hash($node);
    }

    private function visit_multi_select_hash(array $node)
    {
        $tmpCurrent = uniqid('cur_');
        $listVal = uniqid('list_');
        $value = uniqid('prev_');

        $this->write('if ($value !== null) {')
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
                $this->write("\${$listVal}['{$child['key']}'] = \$value;");
            } else {
                $this->dispatch($child);
                $this->write("\${$listVal}[] = \$value;");
            }
        }

        $this->write("\$value = \${$listVal};")
            ->write("\$current = \${$tmpCurrent};")
            ->outdent()
            ->write('}');

        return $this;
    }

    private function visit_function(array $node)
    {

    }

    private function visit_slice(array $node)
    {

    }

    private function visit_current_node(array $node)
    {
        return $this;
    }

    private function visit_merge(array $node)
    {
        $this->dispatch($node['children'][0]);

        $tmpMerged = uniqid('merged_');
        $tmpVal = uniqid('val_');

        $this
            ->write('if (is_array($value)) {')
            ->indent()
                ->write('$invalid = false;')
                ->write('if ($value) {')
                    ->indent()
                        ->write('$keys = array_keys($value);')
                        ->write('if ($keys[0] !== 0) {')
                        ->indent()
                            ->write('$invalid = true;')
                        ->outdent()
                        ->write('}')
                    ->outdent()
                ->write('}')
                ->write('if ($invalid) {')
                ->indent()
                    ->write('$value = null;')
                ->outdent()
                ->write('} else {')
                    ->indent()
                    ->write("\${$tmpMerged} = array();")
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
                ->write('}')
                ->outdent()
            ->write('} else {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
            ->write('}');

        return $this;
    }

    private function visit_projection(array $node)
    {
        $tempVal = uniqid('v');
        $this->dispatch($node['children'][0])
            ->write('')
            ->write('if (!is_array($value)) {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
            ->write('} elseif ($value) {')
                ->indent();

        if (isset($node['from'])) {
            $this->write('$keys = array_keys($value);');
            if ($node['from'] == 'array') {
                $this->write('if ($keys[0] === 0) {');
                $this->indent();
            } elseif ($node['from'] == 'object') {
                $this->write('if ($keys[0] !== 0) {');
                $this->indent();
            }
        }

        $this->write('$collected = array();')
            ->write('foreach ($value as $key => $' . $tempVal . ') {')
                ->indent()
                ->write("\$value = \${$tempVal};")
                ->dispatch($node['children'][1])
                ->write('if ($value !== null) {')
                    ->indent()
                    ->write('$collected[] = $value;')
                    ->outdent()
                ->write('}')
                ->outdent()
            ->write('}')
            ->write('$value = $collected;');

        if (isset($node['from'])) {
            $this->outdent()
            ->write('} else {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
            ->write('}');
        }

        $this->outdent()
            ->write('}');

        return $this;
    }

    private function visit_condition(array $node)
    {
        $this->dispatch($node['children'][0]);
        $this->write('if ($value !== null) {')
                ->indent()
                ->dispatch($node['children'][1])
                ->outdent()
            ->write('}');

        return $this;
    }

    private function visit_comparator(array $node)
    {
        $tmpValue = uniqid('val_');
        $tmpCurrent = uniqid('cur_');
        $tmpA = uniqid('left_');
        $tmpB = uniqid('right_');

        $this->write("\${$tmpValue} = \$value;")
            ->write("\${$tmpCurrent} = \$current;")
            ->dispatch($node['children'][0])
            ->write("\${$tmpA} = \$value;")
            ->dispatch($node['children'][1])
            ->write("\${$tmpB} = \$value;");

        switch ($node['relation']) {
            case '==':
                $this->write("\$result = \${$tmpA} === \${$tmpB};");
                break;
            case '!=':
                $this->write("\$result = \${$tmpA} !== \${$tmpB};");
                break;
            case '>':
                $this->write("\$result = is_int(\${$tmpA}) && is_int(\${$tmpB}) && \${$tmpA} > \${$tmpB};");
                break;
            case '>=':
                $this->write("\$result = is_int(\${$tmpA}) && is_int(\${$tmpB}) && \${$tmpA} >= \${$tmpB};");
                break;
            case '<':
                $this->write("\$result = is_int(\${$tmpA}) && is_int(\${$tmpB}) && \${$tmpA} < \${$tmpB};");
                break;
            case '<=':
                $this->write("\$result = is_int(\${$tmpA}) && is_int(\${$tmpB}) && \${$tmpA} <= \${$tmpB};");
                break;
            default:
                throw new \RuntimeException("Invalid relation: {$node['relation']}");
        }

        $this->write("\$value = \$result === true ? \${$tmpValue} : null;");
        $this->write("\$current = \${$tmpCurrent};");

        return $this;
    }
}
