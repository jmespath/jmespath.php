<?php
namespace JmesPath;

use JmesPath\Runtime\AstRuntime;
use JmesPath\Runtime\CompilerRuntime;

const COMPILE_DIR = 'JP_PHP_COMPILE';

/**
 * Returns data from the input array that matches a given JMESPath expression.
 *
 * If the JP_PHP_COMPILE environment variable is specified, then the
 * CompilerRuntime will be utilized. If set to "on", JMESPath expressions will
 * be cached to the system's temp directory. Set the environment variable to
 * a string to cache expressions to a specific directory.
 *
 * @param string $expression JMESPath expression to evaluate
 * @param mixed  $data       JSON-like data to search
 *
 * @return mixed|null Returns the matching data or null
 */
function search($expression, $data)
{
    static $runtime;
    if (!$runtime) {
        $runtime = envRuntime();
    }

    return $runtime->search($expression, $data);
}

/**
 * Creates a JMESPath runtime based on environment variables.
 *
 * @return AstRuntime|CompilerRuntime
 */
function envRuntime()
{
    $compileDir = isset($_SERVER[COMPILE_DIR])
        ? $_SERVER[COMPILE_DIR]
        : (isset($_ENV[COMPILE_DIR]) ? $_ENV[COMPILE_DIR] : null);

    if (!$compileDir) {
        return new AstRuntime();
    }

    return $compileDir === 'on'
        ? new CompilerRuntime()
        : new CompilerRuntime(['dir' => $compileDir]);
}
