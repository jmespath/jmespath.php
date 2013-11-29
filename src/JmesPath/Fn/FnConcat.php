<?php

namespace JmesPath\Fn;

/**
 * This method accepts a variable number of arguments, and concatenates each
 * argument. Each argument MUST be a String. If anything other than a String
 * is passed in any argument, this function MUST return null.
 */
class FnConcat extends AbstractFn
{
    protected $rules = array(
        'arity' => array(2, -1),
        'args' => array(
            'default' => array('type' => 'string', 'failure' => 'null')
        )
    );

    protected function execute(array $args)
    {
        $result = '';
        foreach ($args as $arg) {
            $result .= $arg;
        }

        return $result;
    }
}
