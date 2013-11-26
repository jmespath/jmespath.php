<?php

namespace JmesPath\Fn;

class FnStrlen extends AbstractFn
{
    protected function execute(array $args)
    {
        return is_string($args[0]) ? strlen($args[0]) : null;
    }
}
