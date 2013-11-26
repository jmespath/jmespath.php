<?php

namespace JmesPath\Fn;

class FnSubstr extends AbstractFn
{
    protected function execute(array $args)
    {
        if (!is_string($args[0])) {
            return null;
        }

        return substr($args[0], $args[1], $args[2]);
    }
}
