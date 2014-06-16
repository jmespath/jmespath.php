<?php
namespace JmesPath\Tree;

use JmesPath\Lexer;

/**
 * Tree visitor used to compile JMESPath expressions into native PHP code.
 */
class TreeCompiler implements TreeVisitorInterface
{
    /** @var int Current level of indentation */
    private $indentation;

    /** @var string Compiled source code */
    private $source;

    /** @var array Hash of indentation level to cached str_padded value */
    private $cachedIndents;

    public function visit(array $node, $data, array $args = null)
    {
        if (!isset($args['function_name'])) {
            throw new \InvalidArgumentException('A function_name arg is required');
        }

        $this->source = '';
        $this->indentation = 0;
        $this->write("<?php\n");
        if (isset($args['expression'])) {
            $this->write("// {$args['expression']}");
        }
        $this->write("function {$args['function_name']}(JmesPath\\Runtime\\RuntimeInterface \$runtime, \$value)")
             ->write('{')
                ->indent()
                ->write('$current = $value;')
                ->dispatch($node)
                ->write('')
                ->write('return $value;')
                ->outdent()
            ->write('}');

        return $this->source;
    }

    /**
     * Handles evaluating undefined types without paying the cost of validation
     */
    public function __call($method, $args)
    {
        throw new \RuntimeException(
            sprintf('Invalid node encountered: %s', json_encode($args[0]))
        );
    }

    private function dispatch(array $node)
    {
        return $this->{"visit_{$node['type']}"}($node);
    }

    /**
     * Writes the given line of source code
     *
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
     * Decreases the indentation level of code being written
     *
     * @return $this
     */
    private function outdent()
    {
        $this->indentation--;

        return $this;
    }

    /**
     * Increases the indentation level of code being written
     *
     * @return $this
     */
    private function indent()
    {
        $this->indentation++;

        return $this;
    }

    private function visit_or(array $node)
    {
        $id = uniqid();

        return $this
            ->write('$beforeOr_' . $id . ' = $value;')
            ->dispatch($node['children'][0])
            ->write('')
            ->write('if (!$value && $value !== "0" && $value !== 0) {')
                ->indent()
                ->write('$value = $beforeOr_' . $id . ';')
                ->dispatch($node['children'][1])
                ->outdent()
            ->write('}');
    }

    /**
     * Creates array access code for a given node
     *
     * @param array $node AST node
     * @return string|bool Returns the array access code
     * @throws \RuntimeException if the node is not a field or index node
     */
    private function createArrayAccess($node)
    {
        if ($node['type'] == 'field') {
            return '[' . var_export($node['key'], true) . ']';
        } elseif ($node['type'] == 'index') {
            return $node['index'] >= 0 ? "[{$node['index']}]" : false;
        } elseif ($node['type'] == 'subexpression') {
            if ($left = $this->createArrayAccess($node['children'][0])) {
                if ($right = $this->createArrayAccess($node['children'][1])) {
                    return $left . $right;
                }
            }
        }

        return false;
    }

    /**
     * Visits a non-terminal subexpression. Subexpressions wrapping nested
     * array accessors will be combined into a single if/then block.
     */
    private function visit_subexpression(array $node)
    {
        if (($node['children'][0]['type'] == 'field' ||
            $node['children'][0]['type'] == 'index') &&
            $node['children'][1]['type'] == 'subexpression'
        ) {
            // Create an optimized isset() check using a combination of
            // subexpression accessors when it is possible. It is not possible
            // to perform this optimization if any of the subexpressions are
            // something other than field or indexes or an index with a
            // negative index.
            if ($key = $this->createArrayAccess($node)) {
                $check = '$value' . $key;
                $checkArr = substr($check, 0, strrpos($check, '['));
                $this->write("\$value = (isset($check) && is_array($checkArr))")
                    ->indent()
                    ->write("? $check")
                    ->write(': null;')
                    ->outdent();
                return $this;
            }
        }

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
        $check = '$value[' . var_export($node['key'], true) . ']';
        $this->write("\$value = is_array(\$value) && isset($check) ? $check : null;");

        return $this;
    }

    /**
     * Visits a terminal index
     */
    private function visit_index(array $node)
    {
        if ($node['index'] >= 0) {
            $check = '$value[' . $node['index'] . ']';
            $this->write("\$value = is_array(\$value) && isset($check) ? $check : null;");
        } else {
            // Account for negative indices
            $tmpCount = uniqid('count_');
            $this->write('if (is_array($value)) {')
                ->indent()
                ->write("\${$tmpCount} = count(\$value) + {$node['index']};")
                ->write("\$value = isset(\$value[\${$tmpCount}]) ? \$value[\${$tmpCount}] : null;")
                ->outdent()
                ->write('} else {')
                ->indent()
                ->write('$value = null;')
                ->outdent()
                ->write('}');
        }

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
                $key = var_export($child['key'], true);
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
        $value = uniqid('value_');
        $current = uniqid('current_');
        $args = uniqid('args_');

        $this->write("\${$value} = \$value;")
            ->write("\${$current} = \$current;")
            ->write("\${$args} = array();");

        foreach ($node['children'] as $arg) {
            $this->dispatch($arg);
            $this->write("\${$args}[] = \$value;")
                ->write("\$current = \${$current};")
                ->write("\$value = \${$value};");
        }

        return $this->write("\$value = \$runtime->callFunction('{$node['fn']}', \${$args});");
    }

    private function visit_slice(array $node)
    {
        return $this
            ->write("\$value = \$runtime->callFunction('slice', array(")
            ->indent()
                ->write(sprintf(
                    '$value, %s, %s, %s',
                    var_export($node['args'][0], true),
                    var_export($node['args'][1], true),
                    var_export($node['args'][2], true)
                ))
                ->outdent()
            ->write('));');
    }

    private function visit_current(array $node)
    {
        return $this->write('// Visiting current node (no-op)');
    }

    private function visit_expression(array $node)
    {
        $child = var_export($node['children'][0], true);

        return $this->write("\$value = new \\JmesPath\\Tree\\ExprNode("
            . "new \\JmesPath\\Tree\\TreeInterpreter(), $child);");
    }

    private function visit_flatten(array $node)
    {
        $this->dispatch($node['children'][0]);

        $tmpMerged = uniqid('merged_');
        $tmpVal = uniqid('val_');

        $this
            ->write('// Visiting merge node')
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
        $tmpVal = uniqid('v');
        $tmpCollected = uniqid('collected_');

        $this->write('// Visiting projection node')
            ->dispatch($node['children'][0])
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

        $this->write("\${$tmpCollected} = array();")
            ->write('foreach ($value as $key => $' . $tmpVal . ') {')
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
            ->write("\$value = \${$tmpCollected};");

        if (isset($node['from'])) {
            $this->outdent()
                ->write('} else {')
                    ->indent()
                    ->write('$value = null;')
                    ->outdent()
                ->write('}');
        }

        return $this
            ->outdent()
            ->write('}');
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
        $tmpValue = uniqid('val_');
        $tmpCurrent = uniqid('cur_');
        $tmpA = uniqid('left_');
        $tmpB = uniqid('right_');

        $this
            ->write('// Visiting comparator node')
            ->write("\${$tmpValue} = \$value;")
            ->write("\${$tmpCurrent} = \$current;")
            ->dispatch($node['children'][0])
            ->write("\${$tmpA} = \$value;")
            ->write("\$value = \${$tmpValue};")
            ->dispatch($node['children'][1])
            ->write("\${$tmpB} = \$value;");

        Lexer::validateBinaryOperator($node['relation']);
        if ($node['relation'] == '==' || $node['relation'] == '!=') {
            $this->write("\$result = \${$tmpA} {$node['relation']}= \${$tmpB};");
        } else {
            $this->write("\$result = is_int(\${$tmpA}) && is_int(\${$tmpB}) && \${$tmpA} {$node['relation']} \${$tmpB};");
        }

        return $this
            ->write("\$value = \$result === true ? \${$tmpValue} : null;")
            ->write("\$current = \${$tmpCurrent};");
    }
}
