<?php

namespace JmesPath\Fn;

class FnRegex extends AbstractFn
{
    protected function execute(array $args)
    {
        if (!is_string($args[0])) {
            throw new \RuntimeException('regex search must use a string regular expression pattern');
        }

        return is_string($args[1]) ? ((bool) preg_match($args[0], $args[1])) : null;
    }
}
