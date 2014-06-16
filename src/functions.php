<?php
namespace JmesPath;

use JmesPath\Runtime\AstRuntime;
use JmesPath\Runtime\RuntimeInterface;

/**
 * Returns data from the input array that matches a given JMESPath expression.
 *
 * @param string $expression JMESPath expression to evaluate
 * @param mixed  $data       JSON-like data to search
 *
 * @return mixed|null Returns the matching data or null
 */
function search($expression, $data)
{
    if (!_CachedRuntime::$runtime) {
        _CachedRuntime::$runtime = new AstRuntime();
    }

    return _CachedRuntime::$runtime->search($expression, $data);
}

/** @internal */
final class _CachedRuntime
{
    /** @var RuntimeInterface The Runtime used in \JmesPath::search. */
    public static $runtime;
}
