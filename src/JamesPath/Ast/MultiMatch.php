<?php

namespace JamesPath\Ast;

/**
 * Allows multiple branches to be treated as a single node
 */
class MultiMatch implements \ArrayAccess
{
    protected $elements = array();

    /**
     * @param array $elements Possible decision branches
     */
    public function __construct(array $elements = array())
    {
        $this->elements = $elements;
    }

    /**
     * Convert the MultiMatch object to an array
     *
     * @return array
     */
    public function toArray()
    {
        $results = array();
        foreach ($this->elements as $key => $element) {
            $results[$key] = $element instanceof self ? $element->toArray() : $element;
        }

        return $results;
    }

    public function offsetExists($offset)
    {
        foreach ($this->elements as $element) {
            if ($offset < 0 && is_array($element)) {
                if (isset($element[count($element) + $offset])) {
                    return true;
                }
            } elseif (!is_scalar($element) && isset($element[$offset])) {
                return true;
            }
        }

        return false;
    }

    public function offsetGet($offset)
    {
        $results = array();

        foreach ($this->elements as $element) {
            if ($offset < 0 && is_array($element)) {
                // Handle negative offsets
                $index = count($element) + $offset;
                if (isset($element[$index])) {
                    $results[] = $element[$index];
                }
            } elseif (!is_scalar($element) && isset($element[$offset])) {
                $results[] = $element[$offset];
            }
        }

        return $results ? new MultiMatch($results) : null;
    }

    public function offsetSet($offset, $value) {}

    public function offsetUnset($offset) {}
}
