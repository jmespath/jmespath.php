<?php
namespace JmesPath\Tests;

use JmesPath\JmesPathableObjectInterface;

class StdClassLike implements JmesPathableObjectInterface
{
    private $values = [];

    public function __get($name)
    {
        return $this->values[$name];
    }

    public function __set($name, $value)
    {
        $this->values[$name] = $value;
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->values);
    }

    public function toArray()
    {
        $array = [];

        foreach ($this->values as $name => $value) {
            if ($value instanceof ArrayLike) {
                $array[$name] = [];
                foreach ($value as $property) {
                    $array[$name][] = self::propertyToArrayValue($property);
                }
            } else {
                $array[$name] = self::propertyToArrayValue($value);
            }
        }

        return $array;
    }

    private static function propertyToArrayValue($value)
    {
        if ($value instanceof StdClassLike) {
            return $value->toArray();
        } else {
            return $value;
        }
    }

    public function __toString()
    {
        return json_encode($this->toArray());
    }
}
