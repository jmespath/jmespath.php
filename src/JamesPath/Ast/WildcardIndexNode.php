<?php

namespace JamesPath\Ast;

class WildcardIndexNode extends AbstractNode
{
    public function search($value)
    {
        if (!($value instanceof MultiMatch)) {
            return new MultiMatch((array) $value);
        }

        // Go down a level in the MultiMath array
        return new MultiMatch(array_map(function ($element) {
            return is_array($element) ? new MultiMatch($element) : $element;
        }, $value->toArray()));
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sIndex(*)", $indent);
    }
}
