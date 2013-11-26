<?php

namespace JmesPath\Fn;

class FnCount extends AbstractFn
{
    protected function execute(array $args)
    {
        return is_array($args[0]) ? count($args[0]) : null;
    }
}
