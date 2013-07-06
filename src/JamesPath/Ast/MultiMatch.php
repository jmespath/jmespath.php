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
        foreach ($this->elements as $element) {
            $results[] = $element instanceof self ? $element->toArray() : $element;
        }

        return $results;
    }

    public function offsetExists($offset)
    {
        foreach ($this->elements as $element) {
            if (isset($element[$offset])) {
                return true;
            }
        }

        return false;
    }

    public function offsetGet($offset)
    {
        $results = array();

        foreach ($this->elements as $element) {
            if (!is_scalar($element) && isset($element[$offset])) {
                $result = $element[$offset];
                $results[] = is_scalar($result) ? $result : new self($result);
            }
        }

        return $results;
    }

    public function offsetSet($offset, $value)
    {
        $this->elements[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }
}
