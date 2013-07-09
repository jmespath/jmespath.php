<?php

namespace JamesPath\Ast;

class WildcardValuesNode extends AbstractNode
{
    public function search($value)
    {
        if (is_array($value)) {
            return new MultiMatch(array_values($value));
        } elseif ($value instanceof MultiMatch) {
            // Go down a level in the MultiMath array
            $result = array();
            foreach ($value->toArray() as $arr) {
                if (is_array($arr)) {
                    foreach ($arr as $v) {
                        $result[] = $v;
                    }
                }
            }
            if ($result) {
                return new MultiMatch($result);
            }
        } else {
            return null;
        }
    }

    public function prettyPrint($indent = '')
    {
        return sprintf("%sWildcardValues(*)", $indent);
    }
}
