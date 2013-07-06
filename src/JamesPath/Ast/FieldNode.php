<?php

namespace JamesPath\Ast;

class FieldNode extends AbstractNode
{
    /** @var string */
    protected $name;

    /**
     * @param string $name Search name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    public function search($value)
    {
        if (is_scalar($value)) {
            return null;
        }

        return isset($value[$this->name]) ? $value[$this->name] : null;
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sField(%s)", $indent, $this->name);
    }
}
